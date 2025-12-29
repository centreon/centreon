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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonLog.class.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit();
}

/**
 * @var CentreonDB $configurationDatabase
 */
$configurationDatabase = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $configurationDatabase) == 0) {
    exit();
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_var(
    $_REQUEST['widgetId'],
    FILTER_VALIDATE_INT,
);

$page = filter_var(
    $_REQUEST['page'],
    FILTER_VALIDATE_INT,
);

if ($widgetId === false) {
    throw new InvalidArgumentException('Widget ID must be an integer');
}
if ($page === false) {
    throw new InvalidArgumentException('page must be an integer');
}

/**
 * @var CentreonDB $realtimeDatabase
 */
$realtimeDatabase = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $configurationDatabase);
$sgMonObj = new ServicegroupMonitoring($realtimeDatabase);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$aColorHost = [
    0 => 'host_up',
    1 => 'host_down',
    2 => 'host_unreachable',
    4 => 'host_pending',
];

$aColorService = [
    0 => 'service_ok',
    1 => 'service_warning',
    2 => 'service_critical',
    3 => 'service_unknown',
    4 => 'pending',
];

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

// Prepare the base query
$baseQuery = 'FROM servicegroups ';
$queryParameters = [];

$accessGroups = new AccessGroupCollection();

if (! $centreon->user->admin) {
    $acl = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups = $acl->getAccessGroups();

    ['parameters' => $queryParameters, 'placeholderList' => $accessGroupList] = createMultipleBindParameters(
        $accessGroups->getIds(),
        'access_group',
        QueryParameterTypeEnum::INTEGER
    );

    $configurationDatabaseName = $configurationDatabase->getConnectionConfig()->getDatabaseNameConfiguration();
    $baseQuery .= <<<SQL
            INNER JOIN {$configurationDatabaseName}.acl_resources_sg_relations arsr
                ON servicegroups.servicegroup_id = arsr.sg_id
            INNER JOIN {$configurationDatabaseName}.acl_resources res
                ON arsr.acl_res_id = res.acl_res_id
            INNER JOIN {$configurationDatabaseName}.acl_res_group_relations argr
                ON res.acl_res_id = argr.acl_res_id
            INNER JOIN {$configurationDatabaseName}.acl_groups ag
                ON argr.acl_group_id = ag.acl_group_id
            WHERE ag.acl_group_id IN ({$accessGroupList})
        SQL;
}

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
        $queryParameters[] = QueryParameter::string('search', $search);
    }
}

$orderby = 'name ASC';

$allowedOrderColumns = ['name'];

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';
const DEFAULT_ENTRIES_PER_PAGE= 10;

$allowedDirections = [ORDER_DIRECTION_ASC, ORDER_DIRECTION_DESC];
$defaultDirection = ORDER_DIRECTION_ASC;

$orderByToAnalyse = isset($preferences['order_by'])
    ? trim($preferences['order_by'])
    : null;

if ($orderByToAnalyse !== null) {
    $orderByToAnalyse .= " $defaultDirection";
    [$column, $direction] = explode(' ', $orderByToAnalyse);

    if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
        $orderby = $column . ' ' . $direction;
    }
}

try {
    // Query to count total rows
    $countQuery = 'SELECT COUNT(*) ' . $baseQuery;

    $nbRows = (int) $realtimeDatabase->fetchOne($countQuery, QueryParameters::create($queryParameters));

    // Sanitize and validate input
    $entriesPerPage = filter_var($preferences['entries'], FILTER_VALIDATE_INT);
    if ($entriesPerPage === false || $entriesPerPage < 1) {
        $entriesPerPage = DEFAULT_ENTRIES_PER_PAGE; // Default value
    }

    $offset = max(0, $page) * $entriesPerPage;

    // Main SELECT query with LIMIT
    $query = "SELECT name, servicegroup_id " . $baseQuery;
    $query .= " ORDER BY $orderby";
    $query .= " LIMIT :offset, :entriesPerPage";

    $queryParameters[] = QueryParameter::int('offset', $offset);
    $queryParameters[] = QueryParameter::int('entriesPerPage', $entriesPerPage);

    $kernel = \App\Kernel::createForWeb();
    $resourceController = $kernel->getContainer()->get(
        \Centreon\Application\Controller\MonitoringResourceController::class
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
                                'value' => $servicegroups
                            ],
                            [
                                'name' => 'resource_types',
                                'value' => $types
                            ],
                            [
                                'name' => 'statuses',
                                'value' => $statuses
                            ],
                            [
                                'name' => 'search',
                                'value' => $search
                            ]
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
    foreach ($realtimeDatabase->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
        $servicegroup = [
            'id' => (int) $record['servicegroup_id'],
            'name' => $record['name'],
        ];

        $hostStates = $sgMonObj->getHostStates(
            $record['name'],
            $centreon->user->admin === '1',
            $accessGroups,
            $detailMode
        );

        $serviceStates = $sgMonObj->getServiceStates(
            $record['name'],
            $centreon->user->admin === '1',
            $accessGroups,
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

        $data[$record['name']] = [
            'name' => $record['name'],
            'svc_id' => $record['servicegroup_id'],
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
} catch (CentreonDbException $e){
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        "Error while fetching service group monitoring",
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
