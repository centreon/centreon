<?php
/*
* Copyright 2005-2018 Centreon
* Centreon is developed by : Julien Mathis and Romain Le Merlus under
* GPL Licence 2.0.
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License as published by the Free Software
* Foundation ; either version 2 of the License.
*
* This program is distributed in the hope that it will be useful, but WITHOUT ANY
* WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
* PARTICULAR PURPOSE. See the GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License along with
* this program; if not, see <http://www.gnu.org/licenses>.
*
* Linking this program statically or dynamically with other modules is making a
* combined work based on this program. Thus, the terms and conditions of the GNU
* General Public License cover the whole combination.
*
* As a special exception, the copyright holders of this program give Centreon
* permission to link this program with independent modules to produce an executable,
* regardless of the license terms of these independent modules, and to copy and
* distribute the resulting executable under terms of Centreon choice, provided that
* Centreon also meet, for each linked independent module, the terms  and conditions
* of the license of that module. An independent module is a module which is not
* derived from this program. If you modify this program, you may extend this
* exception to your version of the program, but you are not obliged to do so. If you
* do not wish to do so, delete this exception statement from your version.
*
* For more information : contact@centreon.com
*
*/

if (!isset($centreon)) {
    exit();
}

require_once(_CENTREON_PATH_ . '/www/class/centreonService.class.php');

$serviceObj = new CentreonService($pearDB);
$gmtObj = new CentreonGMT($pearDB);

/**
 * Notice that this timestamp is actually the server's time and not the UNIX time
 * In the future this behaviour should be changed and UNIX timestamps should be used
 *
 * date('Z') is the offset in seconds between the server's time and UTC
 * The problem remains that on some servers that do not use UTC based timezone, leap seconds are taken in
 * considerations while all other dates are in comparison wirh GMT so there will be an offset of some seconds
 */
$currentServerMicroTime = time() * 1000 + date('Z') * 1000;
$userGmt = 0;

$useGmt = 1;
$userGmt = $oreon->user->getMyGMT();
$gmtObj->setMyGMT($userGmt);
$sMyTimezone = $gmtObj->getMyTimezone();
$sDate = new DateTime();
if (empty($sMyTimezone)) {
    $sMyTimezone = date_default_timezone_get();
}
$sDate->setTimezone(new DateTimeZone($sMyTimezone));
$currentServerMicroTime = $sDate->getTimestamp();


/*
 * Path to the configuration dir
 */
$path = "./include/views/graphs/";


// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);


$defaultGraphs = [];

function getGetPostValue($str)
{
    $value = null;
    if (isset($_GET[$str]) &&
        $_GET[$str]
    ) {
        $value = $_GET[$str];
    }
    if (isset($_POST[$str]) &&
        $_POST[$str]
    ) {
        $value = $_POST[$str];
    }
    return rawurldecode($value);
}

// Init
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
$centreonPerformanceServiceGraphObj = new CentreonPerformanceService($pearDBO, $aclObj);

/*
 * Get Arguments
 */

$svc_id = getGetPostValue("svc_id");

$DBRESULT = $pearDB->query("SELECT * FROM options WHERE `key` = 'maxGraphPerformances' LIMIT 1");
$data = $DBRESULT->fetch();
$graphsPerPage = $data['value'];
if (empty($graphsPerPage)) {
    $graphsPerPage = '18';
}

if (isset($svc_id) &&
    $svc_id
) {
    $tab_svcs = explode(",", $svc_id);
    foreach ($tab_svcs as $svc) {
        $graphId = null;
        $graphTitle = null;
        if (is_numeric($svc)) {
            $fullName = $serviceObj->getMonitoringFullName($svc);
            $serviceParameters = $serviceObj->getParameters($svc, ['host_id'], true);
            $hostId = $serviceParameters['host_id'];
            $graphId = $hostId . '-' . $svc;
            $graphTitle = $fullName;
        } elseif (preg_match('/^(\d+);(\d+)/', $svc, $matches)) {
            $hostId = $matches[1];
            $serviceId = $matches[2];
            $graphId = $hostId . '-' . $serviceId;
            $graphTitle = $serviceObj->getMonitoringFullName($serviceId, $hostId);
        } elseif (preg_match('/^(.+);(.+)/', $svc, $matches)) {
            [$hostname, $serviceDescription] = explode(";", $svc);
            $hostId = getMyHostID($hostname);
            $serviceId = getMyServiceID($serviceDescription, $hostId);
            $graphId = $hostId . '-' . $serviceId;
            $graphTitle = $serviceObj->getMonitoringFullName($serviceId, $hostId);
        } else {
            $hostId = getMyHostID($svc);
            $serviceList = $centreonPerformanceServiceGraphObj->getList(['host' => [$hostId]]);
            $defaultGraphs = array_merge($defaultGraphs, $serviceList);
        }

        if (!is_null($graphId) &&
            !is_null($graphTitle) &&
            $graphTitle != ''
        ) {
            $defaultGraphs[] = ['id' => $graphId, 'text' => $graphTitle];
        }
    }
}

/* Get Period if is in url */
$period_start = 'undefined';
$period_end = 'undefined';
if (isset($_REQUEST['start']) &&
    is_numeric($_REQUEST['start'])
) {
    $period_start = $_REQUEST['start'];
}

if (isset($_REQUEST['end']) &&
    is_numeric($_REQUEST['end'])
) {
    $period_end = $_REQUEST['end'];
}

/*
 * Form begin
 */
$form = new HTML_QuickFormCustom('FormPeriod', 'get', "?p=" . $p);
$form->addElement(
    'header',
    'title',
    _("Choose the source to graph")
);

$periods = [
    "" => "",
    "3h" => _("Last 3 hours"),
    "6h" => _("Last 6 hours"),
    "12h" => _("Last 12 hours"),
    "1d" => _("Last 24 hours"),
    "2d" => _("Last 2 days"),
    "3d" => _("Last 3 days"),
    "4d" => _("Last 4 days"),
    "5d" => _("Last 5 days"),
    "7d" => _("Last 7 days"),
    "14d" => _("Last 14 days"),
    "28d" => _("Last 28 days"),
    "30d" => _("Last 30 days"),
    "31d" => _("Last 31 days"),
    "2M" => _("Last 2 months"),
    "4M" => _("Last 4 months"),
    "6M" => _("Last 6 months"),
    "1y" => _("Last year")
];
$sel = $form->addElement(
    'select',
    'period',
    _("Graph Period"),
    $periods,
    ["onchange"=>"changeInterval()"]
);
$form->addElement(
    'text',
    'StartDate',
    '',
    ["id" => "StartDate", "class" => "datepicker-iso", "size" => 10, "onchange" => "changePeriod()"]
);
$form->addElement(
    'text',
    'StartTime',
    '',
    ["id" => "StartTime", "class" => "timepicker", "size" => 5, "onchange" => "changePeriod()"]
);
$form->addElement(
    'text',
    'EndDate',
    '',
    ["id" => "EndDate", "class" => "datepicker-iso", "size" => 10, "onchange" => "changePeriod()"]
);
$form->addElement(
    'text',
    'EndTime',
    '',
    ["id" => "EndTime", "class" => "timepicker", "size" => 5, "onchange" => "changePeriod()"]
);

/* adding hidden fields to get the result of datepicker in an unlocalized format */
$form->addElement(
    'hidden',
    'alternativeDateStartDate',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);
$form->addElement(
    'hidden',
    'alternativeDateEndDate',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);

$form->addElement(
    'button',
    'graph',
    _("Apply Period"),
    ["onclick"=>"apply_period()", "class"=>"btc bt_success"]
);

if ($period_start != 'undefined' && $period_end != 'undefined') {
    $startDay = date('Y-m-d', $period_start);
    $startTime = date('H:i', $period_start);
    $endDay = date('Y-m-d', $period_end);
    $endTime = date('H:i', $period_end);
    $form->setDefaults(
        ['StartDate' => $startDay, 'StartTime' => $startTime, 'EndTime' => $endTime, 'EndDate' => $endDay]
    );
} else {
    $form->setDefaults(
        ['period' => '3h']
    );
}

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);

$tpl->assign('form', $renderer->toArray());
$tpl->assign('periodORlabel', _("or"));
$tpl->assign('nbDisplayedCharts', $graphsPerPage);
$tpl->assign('from', _("From"));
$tpl->assign('to', _("to"));
$tpl->assign('displayStatus', _("Display Status"));
$tpl->assign('Apply', _("Apply"));
$tpl->assign('defaultCharts', json_encode($defaultGraphs));
$tpl->assign("admin", $centreon->user->admin);
$tpl->assign("topologyAccess", $centreon->user->access->topology);
$tpl->assign("timerDisabled", returnSvg("www/img/icons/timer.svg", "var(--icons-disabled-fill-color)", 14, 14));
$tpl->assign("timerEnabled", returnSvg("www/img/icons/timer.svg", "var(--icons-fill-color)", 14, 14));
$tpl->assign("removeIcon", returnSvg("www/img/icons/circle-crossed.svg", "var(--icons-fill-color)", 20, 20));
$tpl->assign("csvIcon", returnSvg("www/img/icons/csv.svg", "var(--icons-fill-color)", 19, 19));
$tpl->assign("pictureIcon", returnSvg("www/img/icons/picture.svg", "var(--icons-fill-color)", 19, 19));
$tpl->display("graphs.html");

$multi = 1;
