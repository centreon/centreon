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

// Smarty template initialization
$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$hostStateLabels = [0 => "Up", 1 => "Down", 2 => "Unreachable", 4 => "Pending"];

$serviceStateLabels = [0 => "Ok", 1 => "Warning", 2 => "Critical", 3 => "Unknown", 4 => "Pending"];

$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT 1 AS REALTIME, name FROM servicegroups ";
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

$allowedOrderColumns = ['name'];

$allowedDirections = ['ASC', 'DESC'];

if (isset($preferences['order_by']) && trim($preferences['order_by']) !== '') {
    $aOrder = explode(' ', trim($preferences['order_by']));
    $column = $aOrder[0] ?? '';
    $direction = isset($aOrder[1]) ? strtoupper($aOrder[1]) : 'ASC';

    if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
        $orderBy = $column . ' ' . $direction;
    }
}

$query .= " ORDER BY $orderBy";

$stmt = $dbb->prepare($query);
// bind params
foreach ($whereParams as $key => $value) {
    $stmt->bindValue($key, $value, \PDO::PARAM_STR);
}
$stmt->execute();

$nbRows = (int) $dbb->query('SELECT FOUND_ROWS() AS REALTIME')->fetchColumn();
$data = [];
$detailMode = false;
if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
    $detailMode = true;
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[$row['name']]['name'] = $row['name'];

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
$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('serviceStateLabels', $serviceStateLabels);

$template->assign('data', $data);

$template->display('export.ihtml');
