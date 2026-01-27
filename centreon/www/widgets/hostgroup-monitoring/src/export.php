<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="hostgroups-monitoring.csv"');

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';
require_once $centreon_path . 'www/widgets/hostgroup-monitoring/src/class/HostgroupMonitoring.class.php';

session_start();
if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId'])) {
    exit;
}

/**
 * @var CentreonDB $configurationDatabase
 */
$configurationDatabase = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $configurationDatabase) == 0) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/hostgroup-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
if ($widgetId === false) {
    throw new InvalidArgumentException('Widget ID must be an integer');
}

/**
 * @var CentreonDB $realtimeDatabase
 */
$realtimeDatabase = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $configurationDatabase);
$hostGroupService = new HostgroupMonitoring($realtimeDatabase);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$hostStateLabels = [
    0 => 'Up',
    1 => 'Down',
    2 => 'Unreachable',
    4 => 'Pending',
];

$serviceStateLabels = [
    0 => 'Ok',
    1 => 'Warning',
    2 => 'Critical',
    3 => 'Unknown',
    4 => 'Pending',
];

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';

$accessGroups = new AccessGroupCollection();

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups->mergeWith($acls->getAccessGroups());
}

try {
    $columns = 'SELECT DISTINCT 1 AS REALTIME, name ';
    $baseQuery = ' FROM hostgroups';

    $queryParameters = [];

    if (! $centreon->user->admin) {
        $accessGroupsList = implode(',', $accessGroups->getIds());
        $configurationDatabaseName = $configurationDatabase->getConnectionConfig()->getDatabaseNameConfiguration();
        $baseQuery .= <<<SQL
                INNER JOIN {$configurationDatabaseName}.acl_resources_hg_relations arhr
                    ON hostgroups.hostgroup_id = arhr.hg_hg_id
                INNER JOIN {$configurationDatabaseName}.acl_resources res
                    ON arhr.acl_res_id = res.acl_res_id
                INNER JOIN {$configurationDatabaseName}.acl_res_group_relations argr
                    ON res.acl_res_id = argr.acl_res_id
                INNER JOIN {$configurationDatabaseName}.acl_groups ag
                    ON argr.acl_group_id = ag.acl_group_id
                    AND ag.acl_group_id IN ({$accessGroupsList})
            SQL;

    }

    if (isset($preferences['hg_name_search']) && trim($preferences['hg_name_search']) !== '') {
        $tab = explode(' ', $preferences['hg_name_search']);
        $op = $tab[0];
        if (isset($tab[1])) {
            $search = $tab[1];
        }
        if ($op && isset($search) && trim($search) !== '') {
            $baseQuery = CentreonUtils::conditionBuilder(
                $baseQuery,
                'name ' . CentreonUtils::operandToMysqlFormat($op) . ' :search '
            );
            $queryParameters[] = QueryParameter::string('search', $search);
        }
    }

    $orderby = 'name ' . ORDER_DIRECTION_ASC;

    $allowedOrderColumns = ['name'];
    $allowedDirections = [ORDER_DIRECTION_ASC, ORDER_DIRECTION_DESC];
    $defaultDirection = ORDER_DIRECTION_ASC;

    $orderByToAnalyse = isset($preferences['order_by'])
        ? trim($preferences['order_by'])
        : null;

    if ($orderByToAnalyse !== null) {
        $orderByToAnalyse .= " {$defaultDirection}";
        [$column, $direction] = explode(' ', $orderByToAnalyse);

        if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
            $orderby = $column . ' ' . $direction;
        }
    }

    // Query to count total rows
    $countQuery = 'SELECT COUNT(*) ' . $baseQuery;

    // Main SELECT query
    $query = $columns . $baseQuery;
    $query .= " ORDER BY {$orderby}";

    $nbRows = (int) $realtimeDatabase->fetchOne($countQuery, QueryParameters::create($queryParameters));

    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }
    $data = [];
    foreach ($realtimeDatabase->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
        $name = HtmlSanitizer::createFromString($record['name'])->sanitize()->getString();
        $data[$name]['name'] = $name;
    }
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error fetching hostgroup monitoring usage data for export: ' . $e->getMessage(),
        exception: $e
    );

    throw new Exception('Error fetching hostgroup monitoring usage data for export: ' . $e->getMessage());
}

$hostGroupService->getHostStates(
    $data,
    $centreon->user->admin === '1',
    $accessGroups,
    $detailMode,
);
$hostGroupService->getServiceStates(
    $data,
    $centreon->user->admin === '1',
    $accessGroups,
    $detailMode,
);

$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('data', $data);

$template->display('export.ihtml');
