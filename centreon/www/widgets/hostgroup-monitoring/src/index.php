<?php

/*
 * Copyright 2005-2021 Centreon
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
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;

require_once '../../require.php';
require_once '../../widget-error-handling.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/widgets/hostgroup-monitoring/src/class/HostgroupMonitoring.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

/**
 * @var CentreonDB $configurationDatabase
 */
$configurationDatabase = $dependencyInjector['configuration_db'];

if (CentreonSession::checkSession(session_id(), $configurationDatabase) == 0) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/hostgroup-monitoring/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
$page = filter_var($_REQUEST['page'], FILTER_VALIDATE_INT);
try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
    if ($page === false) {
        throw new InvalidArgumentException('Page must be an integer');
    }

    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => 'Generic-theme',
        'dark' => 'Centreon-Dark',
        default => throw new Exception('Unknown user theme : ' . $centreon->user->theme),
    };

    $theme = $variablesThemeCSS === 'Generic-theme'
        ? $variablesThemeCSS . '/Variables-css'
        : $variablesThemeCSS;
} catch (Exception $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: 'Error while using hostgroup-monitoring widget: ' . $exception->getMessage(),
        customContext: [
            'widget_id' => $widgetId,
        ],
        exception: $exception
    );
    showError($exception->getMessage(), $theme ?? 'Generic-theme/Variables-css');

    exit;
}

/**
 * @var CentreonDB $realtimeDatabase
 */
$realtimeDatabase = $dependencyInjector['realtime_db'];

$widgetObj = new CentreonWidget($centreon, $configurationDatabase);
$hostGroupMonitoringService = new HostgroupMonitoring($realtimeDatabase);
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

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';
const DEFAULT_ENTRIES_PER_PAGE= 10;

$accessGroups = new AccessGroupCollection();

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups->mergeWith($acls->getAccessGroups());
}

try {
    $columns = 'SELECT DISTINCT 1 AS REALTIME, name, hostgroup_id ';
    $baseQuery = ' FROM hostgroups';

    $queryParameters = [];

    if (! $centreon->user->admin) {
        // Shortcut the request and make it return nothing if user has no accessgroups.
        if ($accessGroups->isEmpty()) {
            $baseQuery .= ' WHERE 1 = 0';
        } else {
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

    }

    if (isset($preferences['hg_name_search']) && trim($preferences['hg_name_search']) !== '') {
        $tab = explode(" ", $preferences['hg_name_search']);
        $op = $tab[0];
        if (isset($tab[1])) {
            $search = $tab[1];
        }
        if ($op && isset($search) && trim($search) !== '') {
            $baseQuery = CentreonUtils::conditionBuilder(
                $baseQuery,
                "name " . CentreonUtils::operandToMysqlFormat($op) . " :search "
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
        $orderByToAnalyse .= " $defaultDirection";
        [$column, $direction] = explode(' ', $orderByToAnalyse);

        if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
            $orderby = $column . ' ' . $direction;
        }
    }

    // Sanitize and validate input
    $entriesPerPage = filter_var($preferences['entries'], FILTER_VALIDATE_INT);
    if ($entriesPerPage === false || $entriesPerPage < 1) {
        $entriesPerPage = DEFAULT_ENTRIES_PER_PAGE; // Default value
    }
    $offset = max(0, $page) * $entriesPerPage;

    // Query to count total rows
    $countQuery = 'SELECT COUNT(DISTINCT hostgroups.hostgroup_id) ' . $baseQuery;

    $nbRows = (int) $realtimeDatabase->fetchOne($countQuery, QueryParameters::create($queryParameters));

    // Main SELECT query with LIMIT
    $query = $columns . $baseQuery;
    $query .= " ORDER BY $orderby";
    $query .= " LIMIT :offset, :entriesPerPage";

    $queryParameters[] = QueryParameter::int('offset', $offset);
    $queryParameters[] = QueryParameter::int('entriesPerPage', $entriesPerPage);

    $data = [];
    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }

    $kernel = \App\Kernel::createForWeb();
    $resourceController = $kernel->getContainer()->get(
        \Centreon\Application\Controller\MonitoringResourceController::class
    );

    $buildHostgroupUri = function (array $hostgroup, array $types, array $statuses) use ($resourceController) {
        $filter = [
            'criterias' => [
                [
                    'name' => 'host_groups',
                    'value' => $hostgroup,
                ],
                [
                    'name' => 'resource_types',
                    'value' => $types,
                ],
                [
                    'name' => 'statuses',
                    'value' => $statuses,
                ]
            ],
        ];

        try {
             $encodedFilter = json_encode($filter, JSON_THROW_ON_ERROR);

            return $resourceController->buildListingUri(
                [
                    'filter' => $encodedFilter,
                ]
            );
        } catch (JsonException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Error while handling hostgroup monitoring data: " . $e->getMessage(),
                exception: $e
            );
            throw new \Exception('Error while handling hostgroup monitoring data: ' . $e->getMessage());
        }
    };

    $buildParameter = function (string $id, string $name) {
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
    $upStatus = $buildParameter('UP', 'Up');
    $downStatus = $buildParameter('DOWN', 'Down');
    $unreachableStatus = $buildParameter('UNREACHABLE', 'Unreachable');

    foreach ($realtimeDatabase->fetchAllAssociative($query, QueryParameters::create($queryParameters)) as $row) {
        $hostgroup = [
            'id' => (int) $row['hostgroup_id'],
            'name' => HtmlSanitizer::createFromString($row['name'])->sanitize()->getString(),
        ];

        $hostgroupServicesUri = $useDeprecatedPages
            ? '../../main.php?p=20201&search=&o=svc&hg=' . $hostgroup['id']
            : $buildHostgroupUri([$hostgroup], [$serviceType], []);

        $hostgroupOkServicesUri = $useDeprecatedPages
            ? $hostgroupServicesUri . '&statusFilter=ok'
            : $buildHostgroupUri([$hostgroup], [$serviceType], [$okStatus]);

        $hostgroupWarningServicesUri = $useDeprecatedPages
            ? $hostgroupServicesUri . '&statusFilter=warning'
            : $buildHostgroupUri([$hostgroup], [$serviceType], [$warningStatus]);

        $hostgroupCriticalServicesUri = $useDeprecatedPages
            ? $hostgroupServicesUri . '&statusFilter=critical'
            : $buildHostgroupUri([$hostgroup], [$serviceType], [$criticalStatus]);

        $hostgroupUnknownServicesUri = $useDeprecatedPages
            ? $hostgroupServicesUri . '&statusFilter=unknown'
            : $buildHostgroupUri([$hostgroup], [$serviceType], [$unknownStatus]);

        $hostgroupPendingServicesUri = $useDeprecatedPages
            ? $hostgroupServicesUri . '&statusFilter=pending'
            : $buildHostgroupUri([$hostgroup], [$serviceType], [$pendingStatus]);

        $hostgroupHostsUri = $useDeprecatedPages
            ? '../../main.php?p=20202&search=&hostgroups=' . $hostgroup['id'] . '&o=h_'
            : $buildHostgroupUri([$hostgroup], [$hostType], []);

        $hostgroupUpHostsUri = $useDeprecatedPages
            ? $hostgroupHostsUri . 'up'
            : $buildHostgroupUri([$hostgroup], [$hostType], [$upStatus]);

        $hostgroupDownHostsUri = $useDeprecatedPages
            ? $hostgroupHostsUri . 'down'
            : $buildHostgroupUri([$hostgroup], [$hostType], [$downStatus]);

        $hostgroupUnreachableHostsUri = $useDeprecatedPages
            ? $hostgroupHostsUri . 'unreachable'
            : $buildHostgroupUri([$hostgroup], [$hostType], [$unreachableStatus]);

        $hostgroupPendingHostsUri = $useDeprecatedPages
            ? $hostgroupHostsUri . 'pending'
            : $buildHostgroupUri([$hostgroup], [$hostType], [$pendingStatus]);

        $data[$row['name']] = [
            'name' => $row['name'],
            'hg_id' => $row['hostgroup_id'],
            'hg_uri' => $hostgroupServicesUri,
            'hg_service_uri' => $hostgroupServicesUri,
            'hg_service_ok_uri' => $hostgroupOkServicesUri,
            'hg_service_warning_uri' => $hostgroupWarningServicesUri,
            'hg_service_critical_uri' => $hostgroupCriticalServicesUri,
            'hg_service_unknown_uri' => $hostgroupUnknownServicesUri,
            'hg_service_pending_uri' => $hostgroupPendingServicesUri,
            'hg_host_uri' => $hostgroupHostsUri,
            'hg_host_up_uri' => $hostgroupUpHostsUri,
            'hg_host_down_uri' => $hostgroupDownHostsUri,
            'hg_host_unreachable_uri' => $hostgroupUnreachableHostsUri,
            'hg_host_pending_uri' => $hostgroupPendingHostsUri,
            'host_state' => [],
            'service_state' => [],
        ];
    }
} catch (ConnectionException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: "Error while fetching hostgroup monitoring data: " . $e->getMessage(),
        exception: $e
    );

    throw new \Exception("Error while fetching hostgroup monitoring data: " . $e->getMessage());
}

$hostGroupMonitoringService->getHostStates($data, (int) $centreon->user->admin === 1, $accessGroups, $detailMode);
$hostGroupMonitoringService->getServiceStates($data, (int) $centreon->user->admin === 1, $accessGroups, $detailMode);

if ($detailMode === true) {
    foreach ($data as $hostgroupName => &$properties) {
        foreach ($properties['host_state'] as $hostName => &$hostProperties) {
            $hostProperties['details_uri'] = $useDeprecatedPages
                ? '../../main.php?p=20202&o=hd&host_name=' . $hostProperties['name']
                : $resourceController->buildHostDetailsUri($hostProperties['host_id']);
        }
        foreach ($properties['service_state'] as $hostId => &$services) {
            foreach ($services as &$serviceProperties) {
                $serviceProperties['details_uri'] = $useDeprecatedPages
                    ? '../../main.php?o=svcd&p=20201'
                        . '&host_name=' . $serviceProperties['name']
                        . '&service_description=' . $serviceProperties['description']
                    : $resourceController->buildServiceDetailsUri($hostId, $serviceProperties['service_id']);
            }
        }
    }
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
$template->assign('data', $data);

$template->display('table.ihtml');
