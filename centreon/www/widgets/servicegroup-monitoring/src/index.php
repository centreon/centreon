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

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonLog.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';

CentreonSession::start(1);

if (! isset($_SESSION['centreon']) || ! isset($_REQUEST['widgetId']) || ! isset($_REQUEST['page'])) {
    exit();
}
$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/servicegroup-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
$page = filter_var($_REQUEST['page'], FILTER_VALIDATE_INT);

if ($widgetId === false) {
    throw new InvalidArgumentException('Widget ID must be an integer');
}
if ($page === false) {
    throw new InvalidArgumentException('page must be an integer');
}

/**
 * @var CentreonDB $dbb
 */
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$aColorHost = [0 => 'host_up', 1 => 'host_down', 2 => 'host_unreachable', 4 => 'host_pending'];

$aColorService = [0 => 'service_ok', 1 => 'service_warning', 2 => 'service_critical', 3 => 'service_unknown', 4 => 'pending'];

$hostStateLabels = [0 => 'Up', 1 => 'Down', 2 => 'Unreachable', 4 => 'Pending'];

$serviceStateLabels = [0 => 'Ok', 1 => 'Warning', 2 => 'Critical', 3 => 'Unknown', 4 => 'Pending'];

// Prepare the base query
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

$orderby = 'name ASC';

$allowedOrderColumns = ['name'];

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';
const DEFAULT_ENTRIES_PER_PAGE = 10;

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

    // Sanitize and validate input
    $entriesPerPage = filter_var($preferences['entries'], FILTER_VALIDATE_INT);
    if ($entriesPerPage === false || $entriesPerPage < 1) {
        $entriesPerPage = DEFAULT_ENTRIES_PER_PAGE; // Default value
    }

    $offset = max(0, $page) * $entriesPerPage;

    // Main SELECT query with LIMIT
    $query = 'SELECT name, servicegroup_id ' . $baseQuery;
    $query .= " ORDER BY {$orderby}";
    $query .= ' LIMIT :offset, :entriesPerPage';

    $statement = $dbb->prepareQuery($query);

    // Bind parameters
    $bindParams = array_merge(
        $bindParams,
        [
            ':offset' => [$offset, PDO::PARAM_INT],
            ':entriesPerPage' => [$entriesPerPage, PDO::PARAM_INT],
        ]
    );

    // Execute the query
    $dbb->executePreparedQuery($statement, $bindParams, true);

    $kernel = App\Kernel::createForWeb();
    $resourceController = $kernel->getContainer()->get(
        Centreon\Application\Controller\MonitoringResourceController::class
    );

    $buildServicegroupUri = function (
        $servicegroups = [],
        $types = [],
        $statuses = [],
        $search = ''
    ) use ($resourceController) {
        return $resourceController->buildListingUri(
            [
                'filter' => json_encode(
                    [
                        'criterias' => [
                            [
                                'name' => 'service_groups',
                                'value' => $servicegroups,
                            ],
                            [
                                'name' => 'resource_types',
                                'value' => $types,
                            ],
                            [
                                'name' => 'statuses',
                                'value' => $statuses,
                            ],
                            [
                                'name' => 'search',
                                'value' => $search,
                            ],
                        ],
                    ]
                ),
            ]
        );
    };

    $buildParameter = function ($id, $name) {
        return [
            'id' => $id,
            'name' => $name,
        ];
    };

    $hostType = $buildParameter('host', 'Host');
    $serviceType = $buildParameter('service', 'Service');
    $okStatus = $buildParameter('OK', 'Ok');
    $warningStatus = $buildParameter('WARNING', 'Warning');
    $criticalStatus = $buildParameter('CRITICAL', 'Critical');
    $unknownStatus = $buildParameter('UNKNOWN', 'Unknown');
    $pendingStatus = $buildParameter('PENDING', 'Pending');

    $data = [];
    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }
    while ($row = $dbb->fetch($statement)) {
        $servicegroup = [
            'id' => (int) $row['servicegroup_id'],
            'name' => $row['name'],
        ];

        $hostStates = $sgMonObj->getHostStates(
            $row['name'],
            $centreon->user->admin,
            $aclObj,
            $preferences,
            $detailMode
        );

        $serviceStates = $sgMonObj->getServiceStates(
            $row['name'],
            $centreon->user->admin,
            $aclObj,
            $preferences,
            $detailMode
        );

        if ($detailMode === true) {
            foreach ($hostStates as $hostName => &$properties) {
                $properties['details_uri'] = $useDeprecatedPages
                    ? '../../main.php?p=20202&o=hd&host_name=' . $hostName
                    : $resourceController->buildHostDetailsUri($properties['host_id']);
                $properties['services_uri'] = $useDeprecatedPages
                    ? '../../main.php?p=20201&host_search=' . $hostName . '&sg=' . $servicegroup['id']
                    : $buildServicegroupUri(
                        [$servicegroup],
                        [$serviceType],
                        [],
                        'h.name:' . $hostName
                    );
            }

            foreach ($serviceStates as $hostId => &$serviceState) {
                foreach ($serviceState as $serviceId => &$properties) {
                    $properties['details_uri'] = $useDeprecatedPages
                        ? '../../main.php?p=20201&o=svcd&host_name=' . $properties['name']
                            . '&service_description=' . $properties['description']
                        : $resourceController->buildServiceDetailsUri(
                            $hostId,
                            $serviceId
                        );
                }
            }
        }

        $serviceGroupDeprecatedUri = '../../main.php?p=20201&search=0'
            . '&host_search=0&output_search=0&hg=0&sg=' . $servicegroup['id'];

        $serviceGroupUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri
            : $buildServicegroupUri([$servicegroup]);

        $serviceGroupServicesOkUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri . '&o=svc&statusFilter=ok'
            : $buildServicegroupUri([$servicegroup], [$serviceType], [$okStatus]);

        $serviceGroupServicesWarningUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri . '&o=svc&statusFilter=warning'
            : $buildServicegroupUri([$servicegroup], [$serviceType], [$warningStatus]);

        $serviceGroupServicesCriticalUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri . '&o=svc&statusFilter=critical'
            : $buildServicegroupUri([$servicegroup], [$serviceType], [$criticalStatus]);

        $serviceGroupServicesPendingUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri . '&o=svc&statusFilter=pending'
            : $buildServicegroupUri([$servicegroup], [$serviceType], [$pendingStatus]);

        $serviceGroupServicesUnknownUri = $useDeprecatedPages
            ? $serviceGroupDeprecatedUri . '&o=svc&statusFilter=unknown'
            : $buildServicegroupUri([$servicegroup], [$serviceType], [$unknownStatus]);

        $data[$row['name']] = [
            'name' => $row['name'],
            'svc_id' => $row['servicegroup_id'],
            'sgurl' => $serviceGroupUri,
            'host_state' => $hostStates,
            'service_state' => $serviceStates,
            'sg_service_ok_uri' => $serviceGroupServicesOkUri,
            'sg_service_warning_uri' => $serviceGroupServicesWarningUri,
            'sg_service_critical_uri' => $serviceGroupServicesCriticalUri,
            'sg_service_unknown_uri' => $serviceGroupServicesUnknownUri,
            'sg_service_pending_uri' => $serviceGroupServicesPendingUri,
        ];
    }
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while fetching service group monitoring',
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
$autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);
if ($autoRefresh === false || $autoRefresh < 5) {
    $autoRefresh = 30;
}

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
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('centreon_path', $centreon_path);

$bMoreViews = 0;
if ($preferences['more_views']) {
    $bMoreViews = $preferences['more_views'];
}
$template->assign('more_views', $bMoreViews);

$template->display('table.ihtml');
