<?php
/**
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

ini_set("log_errors",1);
ini_set("error_log", "/tmp/php-error.log");


require_once "../../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}
$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

require_once $centreon_path . "GPL_LIB/Smarty/libs/Smarty.class.php";
$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = new CentreonDB("centstorage");
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
$nbRows = $preferences['entries'];


$hostStateColors = array(
    0 => "#88B917",
    1 => "#F91E05",
    2 => "#82CFD8",
    4 => "#2AD1D4");

$serviceStateColors = array(
    0 => "#88B917",
    1 => "#FF9A13",
    2 => "#E00B3D",
    3 => "#DCDADA",
    4 => "#2AD1D4");

$aColorHost = array(0 => 'host_up', 1 => 'host_down', 2 => 'host_unreachable', 4 => 'host_pending');
$aColorService = array(0 => 'service_ok', 1 => 'service_warning', 2 => 'service_critical', 3 => 'service_unknown', 4 => 'pending');
$hostStateLabels = array(0 => "Up",
    1 => "Down",
    2 => "Unreachable",
    4 => "Pending");

$serviceStateLabels = array(0 => "Ok",
    1 => "Warning",
    2 => "Critical",
    3 => "Unknown",
    4 => "Pending");

$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT name, servicegroup_id ";
$query .= "FROM servicegroups ";
if (isset($preferences['sg_name_search']) && $preferences['sg_name_search'] != "") {
    $tab = split(" ", $preferences['sg_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "name " . CentreonUtils::operandToMysqlFormat($op) . " '" . $dbb->escape($search) . "' ");
    }
}
if (!$centreon->user->admin) {
    $query = CentreonUtils::conditionBuilder($query, "name IN (" . $aclObj->getServiceGroupsString("NAME") . ")");
}


$orderby = "name ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}
$query .= "ORDER BY $orderby";
$query .= " LIMIT " . ($page * $preferences['entries']) . "," . $preferences['entries'];
$res = $dbb->query($query);

error_log($query);
$nbRows = $dbb->numberRows();
$data = array();
$detailMode = false;
if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
    $detailMode = true;
}
while ($row = $res->fetchRow()) {
    $data[$row['name']] = array('name'          => $row['name'],
                                'svc_id'        => $row['servicegroup_id'],
                                'sgurl'         => "main.php?p=20201&search=0&host_search=0&output_search=0&hg=0&sg=" .$row['servicegroup_id'],
                                'hosturl'       => "main.php?p=20202",
                                'host_state'    => $sgMonObj->getHostStates($row['name'], $detailMode, $centreon->user->admin, $aclObj, $preferences),
                                'service_state' => $sgMonObj->getServiceStates($row['name'], $detailMode, $centreon->user->admin, $aclObj, $preferences));
}

$autoRefresh = $preferences['refresh_interval'];
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('nbRows', $nbRows);
$template->assign('page', $page);
$template->assign('orderby', $orderby);
$template->assign('data', $data);
$template->assign('dataJS', count($data));
$template->assign('aColorHost', $aColorHost);
$template->assign('aColorService', $aColorService);
$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('hostStateColors', $hostStateColors);
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('serviceStateColors', $serviceStateColors);
$template->assign('centreon_web_path', trim($centreon->optGen['oreon_web_path'], "/"));
$template->assign('centreon_path', $centreon_path);

$bMoreViews = 0;
if ($preferences['more_views']) {
    $bMoreViews = $preferences['more_views'];
}
$template->assign('more_views', $bMoreViews);

$template->display('table.ihtml');
?>
