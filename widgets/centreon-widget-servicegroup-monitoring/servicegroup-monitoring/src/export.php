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

$baseQuery = "FROM servicegroups ";

$bindParams = [];

if (isset($preferences['sg_name_search']) && trim($preferences['sg_name_search']) != "") {
    $tab = explode(" ", $preferences['sg_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && trim($search) != "") {
        $baseQuery = CentreonUtils::conditionBuilder(
            $baseQuery,
            "name " . CentreonUtils::operandToMysqlFormat($op) . " :search "
        );
        $bindParams[':search'] = [$search, PDO::PARAM_STR];
    }
}

if (! $centreon->user->admin) {
    [$bindValues, $bindQuery] = createMultipleBindQuery($aclObj->getServiceGroups(), ':servicegroup_name_', PDO::PARAM_STR);
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, "name IN ($bindQuery)");
    $bindParams = array_merge($bindParams, $bindValues);
}

$orderBy = "name ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderBy = $preferences['order_by'];
}

try {
    // Query to count total rows
    $countQuery = "SELECT COUNT(*) " . $baseQuery;
    if ($bindParams !== []) {
        $countStatement = $dbb->prepareQuery($countQuery);
        $dbb->executePreparedQuery($countStatement, $bindParams, true);
    } else {
        $countStatement = $dbb->executeQuery($countQuery);
    }
    $nbRows = (int) $dbb->fetchColumn($countStatement);

    // Main SELECT query
    $query = "SELECT DISTINCT 1 AS REALTIME, name, servicegroup_id " . $baseQuery;
    $query .= " ORDER BY $orderBy";

    // Prepare the query
    $statement = $dbb->prepareQuery($query);

    // Execute the query
    $dbb->executePreparedQuery($statement, $bindParams, true);
    $data = [];
    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
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
} catch (CentreonDbException $e){
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        "Error while exporting service group monitoring",
        [
            'message' => $e->getMessage(),
            'parameters' => [
                'entries_per_page' => $entriesPerPage,
                'page' => $page,
                'orderby' => $orderby
            ]
        ],
        $e
    );
}

$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('serviceStateLabels', $serviceStateLabels);

$template->assign('data', $data);

$template->display('export.ihtml');
