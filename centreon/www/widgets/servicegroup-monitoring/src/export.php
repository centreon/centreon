<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="servicegroups-monitoring.csv"');

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';

session_start();
if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}
$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/servicegroup-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$hostStateLabels = [0 => 'Up', 1 => 'Down', 2 => 'Unreachable', 4 => 'Pending'];

$serviceStateLabels = [0 => 'Ok', 1 => 'Warning', 2 => 'Critical', 3 => 'Unknown', 4 => 'Pending'];

$baseQuery = 'FROM servicegroups ';

$bindParams = [];

if (isset($preferences['sg_name_search']) && trim($preferences['sg_name_search']) != '') {
    $tab = explode(' ', $preferences['sg_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && trim($search) != '') {
        $baseQuery = CentreonUtils::conditionBuilder(
            $baseQuery,
            'name ' . CentreonUtils::operandToMysqlFormat($op) . ' :search '
        );
        $bindParams[':search'] = [$search, PDO::PARAM_STR];
    }
}

if (! $centreon->user->admin) {
    [$bindValues, $bindQuery] = createMultipleBindQuery($aclObj->getServiceGroups(), ':servicegroup_name_', PDO::PARAM_STR);
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, "name IN ({$bindQuery})");
    $bindParams = array_merge($bindParams, $bindValues);
}

$orderBy = 'name ASC';

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

try {
    // Query to count total rows
    $countQuery = 'SELECT COUNT(*) ' . $baseQuery;
    if ($bindParams !== []) {
        $countStatement = $dbb->prepareQuery($countQuery);
        $dbb->executePreparedQuery($countStatement, $bindParams, true);
    } else {
        $countStatement = $dbb->executeQuery($countQuery);
    }
    $nbRows = (int) $dbb->fetchColumn($countStatement);

    // Main SELECT query
    $query = 'SELECT DISTINCT 1 AS REALTIME, name, servicegroup_id ' . $baseQuery;
    $query .= " ORDER BY {$orderBy}";

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
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while exporting service group monitoring',
        [
            'message' => $e->getMessage(),
            'parameters' => [
                'entries_per_page' => $entriesPerPage,
                'page' => $page,
                'orderby' => $orderby,
            ],
        ],
        $e
    );
}

$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('serviceStateLabels', $serviceStateLabels);

$template->assign('data', $data);

$template->display('export.ihtml');
