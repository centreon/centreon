<?php
/*
 * Copyright 2005-2016 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
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
    exit;
}

$path = "./include/reporting/dashboard";

/*
 * Require Centreon Class
 */
require_once "./class/centreonDuration.class.php";
require_once "./class/centreonDB.class.php";

/*
 * Require centreon common lib
 */
require_once "./include/reporting/dashboard/common-Func.php";
require_once "./include/reporting/dashboard/DB-Func.php";
require_once "./include/common/common-Func.php";

/*
 * Create DB connexion
 */
$pearDBO = new CentreonDB("centstorage");

$debug = 0;

/*
 * QuickForm templates
 */
$attrsTextI        = ["size"=>"3"];
$attrsText        = ["size"=>"30"];
$attrsTextarea    = ["rows"=>"5", "cols"=>"40"];

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path, "");

$tpl->assign('o', $o);

/*
 *  Assign centreon path
 */
$tpl->assign("centreon_path", _CENTREON_PATH_);

# Status colors
$colors = ['up' => '88b917', 'down' => 'e00b3d', 'unreachable' => '818285', 'maintenance' => 'cc99ff', 'downtime' => 'cc99ff', 'ok' => '88b917', 'warning' => 'ff9a13', 'critical' => 'e00b3d', 'unknown' => 'bcbdc0', 'undetermined' => 'd1d2d4'];
$tpl->assign('colors', $colors);

$color = [];
$color["UNKNOWN"] = $colors['unknown'];
$color["UP"] = $colors['up'];
$color["DOWN"] = $colors['down'];
$color["UNREACHABLE"] = $colors['unreachable'];
$color["UNDETERMINED"] = $colors['undetermined'];
$color["OK"] = $colors['ok'];
$color["WARNING"] = $colors['warning'];
$color["CRITICAL"] = $colors['critical'];
$tpl->assign('color', $color);

$startDate = 0;
$endDate = 0;

/*
 * Translations and styles
 */

$tpl->assign('style_ok', "class='ListColCenter' style='padding:5px;background:#" . $colors['ok'] . "'");
$tpl->assign('style_ok_top', " style='color:#" . $colors['ok'] . "'");
$tpl->assign('style_ok_alert', "class='ListColCenter' style='width: 25px; background:#" . $colors['ok'] . "'");
$tpl->assign('style_warning', "class='ListColCenter' style='padding:5px;background:#" . $colors['warning'] . "'");
$tpl->assign('style_warning_top', "style='color:#" . $colors['warning'] . "'");
$tpl->assign(
    'style_warning_alert',
    "class='ListColCenter' style='width: 25px; background:#" . $colors['warning'] . "'"
);
$tpl->assign('style_critical', "class='ListColCenter' style='padding:5px;background:#" . $colors['critical'] . "'");
$tpl->assign('style_critical_top', "style='color:#" . $colors['critical'] . "'");
$tpl->assign(
    'style_critical_alert',
    "class='ListColCenter' style='width: 25px; background:#" . $colors['critical'] . "'"
);
$tpl->assign('style_unknown', "class='ListColCenter' style='padding:5px;background:#" . $colors['unknown'] . "'");
$tpl->assign('style_unknown_top', "");
$tpl->assign(
    'style_unknown_alert',
    "class='ListColCenter' style='width: 25px; background:#" . $colors['unknown'] . "'"
);
$tpl->assign('style_pending', "class='ListColCenter' style='padding:5px;background:#" . $colors['undetermined'] . "'");
$tpl->assign('style_pending_top', "");
$tpl->assign(
    'style_pending_alert',
    "class='ListColCenter' style='width: 25px; background:#" . $colors['undetermined'] . "'"
);
$tpl->assign(
    'style_maintenance',
    "class='ListColCenter' style='padding:5px;background:#" . $colors['maintenance'] . "'"
);
$tpl->assign('style_maintenance_top', "style='color:#" . $colors['maintenance'] . "'");


$tpl->assign('badge_UP', "class='ListColCenter state_badge host_up'");
$tpl->assign('badge_DOWN', "class='ListColCenter state_badge host_down'");
$tpl->assign('badge_UNREACHABLE', "class='ListColCenter state_badge host_unreachable'");
$tpl->assign('badge_UNDETERMINED', "class='ListColCenter state_badge badge_undetermined'");
$tpl->assign('badge_MAINTENANCE', "class='ListColCenter state_badge badge_downtime'");


$tpl->assign('badge_ok', "class='ListColCenter state_badge service_ok'");
$tpl->assign('badge_warning', "class='ListColCenter state_badge service_warning'");
$tpl->assign('badge_critical', "class='ListColCenter state_badge service_critical'");
$tpl->assign('badge_unknown', "class='ListColCenter state_badge service_unknown'");
$tpl->assign('badge_pending', "class='ListColCenter state_badge badge_undetermined'");
$tpl->assign('badge_maintenance', "class='ListColCenter state_badge badge_downtime'");

$tpl->assign('actualTitle', _("Actual"));

$tpl->assign('serviceTitle', _("Service"));
$tpl->assign('hostTitle', _("Host name"));
$tpl->assign("allTilte", _("All"));
$tpl->assign("averageTilte", _("Average"));

$tpl->assign('OKTitle', _("OK"));
$tpl->assign('WarningTitle', _("Warning"));
$tpl->assign('UnknownTitle', _("Unknown"));
$tpl->assign('CriticalTitle', _("Critical"));
$tpl->assign('PendingTitle', _("Undetermined"));
$tpl->assign('MaintenanceTitle', _("Scheduled downtime"));

$tpl->assign('stateLabel', _("State"));
$tpl->assign('totalLabel', _("Total"));
$tpl->assign('durationLabel', _("Duration"));
$tpl->assign('totalTimeLabel', _("Total Time"));
$tpl->assign('meanTimeLabel', _("Mean Time"));
$tpl->assign('alertsLabel', _("Alerts"));

$tpl->assign('DateTitle', _("Date"));
$tpl->assign('EventTitle', _("Event"));
$tpl->assign('InformationsTitle', _("Info"));

$tpl->assign('periodTitle', _("Reporting Period"));
$tpl->assign('periodORlabel', _("or"));
$tpl->assign('logTitle', _("Today's Host log"));
$tpl->assign('svcTitle', _("State Breakdowns For Host Services"));

/*
 * Definition of status
 */
 $state["UP"] = _("UP");
 $state["DOWN"] = _("DOWN");
 $state["UNREACHABLE"] = _("UNREACHABLE");
 $state["UNDETERMINED"] = _("UNDETERMINED");
 $state["MAINTENANCE"] = _("SCHEDULED DOWNTIME");
 $tpl->assign('states', $state);

 /*
  * CSS Definition for status colors
  */
$style["UP"] = "style='padding:5px;color:#" . $colors['up'] . "'";
$style["UP_BOTTOM"] = "style='padding:5px;background-color:#" . $colors['up'] . "'";
$style["DOWN"] = "style='padding:5px;color:#" . $colors['down'] . "'";
$style["DOWN_BOTTOM"] = "style='padding:5px;background-color:#" . $colors['down'] . "'";
$style["UNREACHABLE"] = "style='padding:5px'";
$style["UNREACHABLE_BOTTOM"] = "style='padding:5px;background-color:#" . $colors['unreachable'] . "'";
$style["UNDETERMINED"] = "style='padding:5px'";
$style["UNDETERMINED_BOTTOM"] = "style='padding:5px;background-color:#" . $colors['undetermined'] . "'";
$style["MAINTENANCE"] = "style='padding:5px;color:#" . $colors['maintenance'] . "'";
$style["MAINTENANCE_BOTTOM"] = "style='padding:5px;background-color:#" . $colors['maintenance'] . "'";
$tpl->assign('style', $style);

/*
 * Init Timeperiod List
 */

/*
 * Getting period table list to make the form period selection (today, this week etc.)
 */
$periodList = getPeriodList();

/*
 * Getting timeperiod by day (example : 9:30 to 19:30 on monday,tue,wed,thu,fri)
 */
$reportingTimePeriod = getreportingTimePeriod();

/*
 * CSV export parameters
 */
 $var_url_export_csv = "";

/*
 * LCA
 */
$lcaHoststr = $centreon->user->access->getHostsString("ID", $pearDBO);
$lcaHostGroupstr = $centreon->user->access->getHostGroupsString();
$lcaSvcstr    = $centreon->user->access->getServicesString("ID", $pearDBO);

/*
 * setting variables for link with services
 */
$period_choice = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST["period_choice"] ?? "preset"
);

$period = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['period'] ?? $_GET['period'] ?? ''
);

$get_date_start = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['start'] ?? $_POST['StartDate'] ?? ''
);

$get_date_end = \HtmlAnalyzer::sanitizeAndRemoveTags(
    $_GET['end'] ?? $_POST['EndDate'] ?? ''
);

if ($get_date_start == "" && $get_date_end == "" && $period == "") {
    $period = "yesterday";
}
$tpl->assign("get_date_start", $get_date_start);
$tpl->assign("get_date_end", $get_date_end);
$tpl->assign("get_period", $period);
$tpl->assign("link_csv_url", null);
$tpl->assign("infosTitle", null);
// Settings default variables for all state.
$tpl->assign("name", null);
$tpl->assign("totalAlert", null);
$tpl->assign("totalTime", null);
$initialStates = [
    "OK_TP" => null,
    "OK_MP" => null,
    "OK_A" => null,
    "WARNING_TP" => null,
    "WARNING_MP" => null,
    "WARNING_A" => null,
    "CRITICAL_TP" => null,
    "CRITICAL_MP" => null,
    "CRITICAL_A" => null,
    "UNKNOWN_TP" => null,
    "UNKNOWN_MP" => null,
    "UNKNOWN_A" => null,
    "UP_TF" => null,
    "UP_TP" => null,
    "UP_MP" => null,
    "UP_A" => null,
    "DOWN_TF" => null,
    "DOWN_TP" => null,
    "DOWN_MP" => null,
    "DOWN_A" => null,
    "UNREACHABLE_TF" => null,
    "UNREACHABLE_TP" => null,
    "UNREACHABLE_MP" => null,
    "UNREACHABLE_A" => null,
    "UNDETERMINED_TF" => null,
    "UNDETERMINED_TP" => null,
    "MAINTENANCE_TF" => null,
    "MAINTENANCE_TP" => null,
    "NAME" => null,
    "ID" => null,
    "DESCRIPTION" => null,
    "HOST_ID" => null,
    "HOST_NAME" => null,
    "SERVICE_ID" => null,
    "SERVICE_DESC" => null
];
$tpl->assign("summary", $initialStates);
$tpl->assign("components_avg", $initialStates);
$tpl->assign("components", ["tb" => $initialStates]);
$tpl->assign("period_name", _("From"));
$tpl->assign("date_start", null);
$tpl->assign("to", _("to"));
$tpl->assign("date_end", null);
$tpl->assign("period", null);
$tpl->assign("host_id", null);
$tpl->assign("hostgroup_id", null);
$tpl->assign("servicegroup_id", null);
$tpl->assign("Alert", _("Alert"));

$tpl->assign("host_up", null);
$tpl->assign("host_down", null);
$tpl->assign("host_unreachable", null);
$tpl->assign("host_undetermined", null);
$tpl->assign("host_maintenance", null);
$tpl->assign("service_ok", null);
$tpl->assign("service_warning", null);
$tpl->assign("service_critical", null);
$tpl->assign("service_unknown", null);
$tpl->assign("service_undetermined", null);
$tpl->assign("service_maintenance", null);
$tpl->assign("hostgroup_up", null);
$tpl->assign("hostgroup_down", null);
$tpl->assign("hostgroup_unreachable", null);
$tpl->assign("hostgroup_undetermined", null);
$tpl->assign("hostgroup_maintenance", null);
$tpl->assign("servicegroup_ok", null);
$tpl->assign("servicegroup_warning", null);
$tpl->assign("servicegroup_critical", null);
$tpl->assign("servicegroup_unknown", null);
$tpl->assign("servicegroup_undetermined", null);
$tpl->assign("servicegroup_maintenance", null);

$tpl->assign('period_choice', $period_choice);
/*
 * Period Selection form
 */
$formPeriod = new HTML_QuickFormCustom('FormPeriod', 'post', "?p=".$p);
$formPeriod->addElement('select', 'period', "", $periodList, ["id" => "presetPeriod"]);
$formPeriod->addElement('hidden', 'timeline', "1");
$formPeriod->addElement(
    'text',
    'StartDate',
    _("From"),
    ["id"=>"StartDate", "size"=>10, "class"=>"datepicker", "onClick" => "javascript: togglePeriodType();"]
);
$formPeriod->addElement(
    'text',
    'EndDate',
    _("to"),
    ["id"=>"EndDate", "size"=>10, "class"=>"datepicker", "onClick" => "javascript: togglePeriodType();"]
);
/* adding hidden fields to get the result of datepicker in an unlocalized format */
$formPeriod->addElement(
    'hidden',
    'alternativeDateStartDate',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);
$formPeriod->addElement(
    'hidden',
    'alternativeDateEndDate',
    'test',
    ['size' => 10, 'class' => 'alternativeDate']
);
$formPeriod->addElement('submit', 'button', _("Apply period"), ['class' => 'btc bt_success ml-2']);
$formPeriod->setDefaults(
    [
        'period' => $period,
        'StartDate' => $get_date_start,
        'EndDate' => $get_date_end,
    ]
);

?>
<script type='text/javascript'>
function togglePeriodType()
{
    document.getElementById("presetPeriod").selectedIndex = 0;
}
</script>
