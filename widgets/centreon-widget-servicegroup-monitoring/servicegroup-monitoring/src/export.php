<?php

/*
 * Copyright 2005-2020 Centreon
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

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="servicegroups-monitoring.csv"');

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$res = $db->query("SELECT `key`, `value` FROM `options` WHERE `key` LIKE 'color%'");
$hostStateColors = array(
    0 => "#19EE11",
    1 => "#F91E05",
    2 => "#82CFD8",
    4 => "#2AD1D4"
);

$serviceStateColors = array(
    0 => "#13EB3A",
    1 => "#F8C706",
    2 => "#F91D05",
    3 => "#DCDADA",
    4 => "#2AD1D4"
);

while ($row = $res->fetch()) {
    switch ($row['key']) {
        case "color_up":
            $hostStateColors[0] = $row['value'];
            break;
        case "color_down":
            $hostStateColors[1] = $row['value'];
            break;
        case "color_unreachable":
            $hostStateColors[2] = $row['value'];
            break;
        case "color_pending":
            $hostStateColors[4] = $row['value'];
            break;
        case "color_ok":
            $serviceStateColors[0] = $row['value'];
            break;
        case "color_warning":
            $serviceStateColors[1] = $row['value'];
            break;
        case "color_critical":
            $serviceStateColors[2] = $row['value'];
            break;
        case "color_unknown":
            $serviceStateColors[3] = $row['value'];
            break;
        case "color_pending":
            $serviceStateColors[4] = $row['value'];
            break;
    }
}

$hostStateLabels = array(
    0 => "Up",
    1 => "Down",
    2 => "Unreachable",
    4 => "Pending"
);

$serviceStateLabels = array(
    0 => "Ok",
    1 => "Warning",
    2 => "Critical",
    3 => "Unknown",
    4 => "Pending"
);

$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT name FROM servicegroups ";
$whereConditions = [];
$whereParams = [];

if (isset($preferences['sg_name_search']) && $preferences['sg_name_search'] != "") {
    $tab = explode(" ", $preferences['sg_name_search']);
    $operand = CentreonUtils::operandToMysqlFormat($tab[0]);
    $searchString = $tab[1];

    if ($operand && $searchString) {
        $whereConditions[] = "name $operand :search_string";
        $whereParams[':search_string'] = $searchString;
    }
}

if (! $centreon->user->admin) {
    $whereConditions[] = "name IN (" . $aclObj->getServiceGroupsString("NAME") . ")";

}

if ($whereConditions) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$orderBy = "name ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderBy = $preferences['order_by'];
}

$query .= " ORDER BY $orderBy";

$stmt = $dbb->prepare($query);
// bind params
foreach ($whereParams as $key => $value) {
    $stmt->bindValue($key, $value, \PDO::PARAM_STR);
}
$stmt->execute();
$nbRows = $stmt->rowCount();
$data = array();
$detailMode = false;
if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
    $detailMode = true;
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($row as $key => $value) {
        $data[$row['name']][$key] = $value;
        $data[$row['name']]['host_state'] = $sgMonObj->getHostStates(
            $row['name'],
            $centreon->user->admin,
            $aclObj,
            $preferences,
            $detailMode
        );
        $data[$row['name']]['service_state'] = $sgMonObj->getServiceStates(
            $row['name'],
            $centreon->user->admin,
            $aclObj,
            $preferences,
            $detailMode
        );
    }
}
$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('hostStateColors', $hostStateColors);
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('serviceStateColors', $serviceStateColors);
$template->assign('centreon_web_path', $centreon->optGen['oreon_web_path']);

$template->assign('data', $data);

$template->display('export.ihtml');
