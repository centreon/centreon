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

require_once "../../require.php";
require_once "../../../../config/centreon.config.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

/* INIT */
$colors = [0 => 'service_ok', 1 => 'service_warning', 2 => 'service_critical', 3 => 'unknown', 4 => 'pending'];
$accessGroups = [];
$arrayKeysAccessGroups = [];
try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    $db_centreon = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['realtime_db'];

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $accessGroups = $access->getAccessGroups();
        $arrayKeysAccessGroups = array_keys($accessGroups);
    }

    $widgetObj = new CentreonWidget($centreon, $db_centreon);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = filter_var($preferences['refresh_interval'], FILTER_VALIDATE_INT);
    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $exception) {
    echo $exception->getMessage() . "<br/>";
    exit;
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

// Smarty template initialization
$template = SmartyBC::createSmartyTemplate(getcwd() . "/", './');

$data = [];
$data_service = [];

// Only process if a host group has been selected in the preferences
if (!empty($preferences['host_group'])) {
    $aclJoin = '';
    $aclSubRequest = '';
    $bindParams1 = [];
    $bindValuesAcl = [];
    /* ---------------------------
     * Query 1: Host Listing
     * ---------------------------
     * Uses the built conditions and filter access groups if needed.
     */
    if ($accessGroups !== []) {
        $aclJoin = $centreon->user->admin == 0 ? " INNER JOIN centreon_acl acl ON T1.host_id = acl.host_id" : "";
        [$bindValuesAcl, $bindQueryAcl] = createMultipleBindQuery(
            list: $arrayKeysAccessGroups,
            prefix: ':access_group_id_host_',
            bindType: \PDO::PARAM_INT
        );
        $aclSubRequest = ' AND acl.group_id IN (' . $bindQueryAcl . ')';
    }

    $query1 = <<<SQL
            SELECT DISTINCT 1 AS REALTIME, T1.name, T2.host_id
            FROM hosts T1
            INNER JOIN hosts_hostgroups T2 ON T1.host_id = T2.host_id
            $aclJoin
            WHERE T1.enabled = 1
                AND T2.hostgroup_id = :hostgroup_id
                $aclSubRequest
            ORDER BY T1.name
        SQL;
    $bindParams1[':hostgroup_id'] = [$preferences['host_group'], PDO::PARAM_INT];
    $bindParams1 = array_merge($bindParams1, $bindValuesAcl);

    try {
        $stmt1 = $db->prepareQuery($query1);
        $db->executePreparedQuery($stmt1, $bindParams1, true);
        while ($row = $db->fetch($stmt1)) {
            $row['details_uri'] = $useDeprecatedPages
                ? '../../main.php?p=20202&o=hd&host_name=' . $row['name']
                : $resourceController->buildHostDetailsUri($row['host_id']);
            $data[] = $row;
        }
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while fetching host listing for widget',
            [
                'query' => $query1,
                'bindParams' => $bindParams1,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ],
            ]
        );
    }

    /* ---------------------------
     * Prepare service filter conditions
     * ---------------------------
     * The preferences['service'] is a comma-separated list. We build an array of conditions
     * with proper bound parameters.
     */
    $serviceList = array_map('trim', explode(",", $preferences['service']));

    // For Query 2 (service listing)
    $bindParams2 = [];
    [$bindValues, $bindQuery] = createMultipleBindQuery(
        list: $serviceList,
        prefix: ':service_description_',
        bindType: \PDO::PARAM_STR
    );
    $whereService2 = 'T1.description IN (' . $bindQuery . ')';

    /* ---------------------------
     * Query 2: Service Listing
     * ---------------------------
     * Uses the built conditions and binds each service filter.
     */
    if ($accessGroups !== []) {
        $aclJoin = $centreon->user->admin == 0 ? " INNER JOIN centreon_acl acl ON T1.service_id = acl.service_id" : "";
    }
    $query2 = <<<SQL
            SELECT DISTINCT 1 AS REALTIME, T1.description
            FROM services T1
            $aclJoin
            WHERE T1.enabled = 1
                $aclSubRequest
                AND $whereService2
        SQL;
    $bindParams2 = array_merge($bindValues, $bindValuesAcl);

    try {
        $stmt2 = $db->prepareQuery($query2);
        $db->executePreparedQuery($stmt2, $bindParams2, true);
        while ($row = $db->fetch($stmt2)) {
            $data_service[$row['description']] = [
                'description' => $row['description'],
                'hosts' => [],
                'hostsStatus' => [],
                'details_uri' => []
            ];
        }
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while fetching service listing for widget',
            [
                'query' => $query2,
                'bindParams' => $bindParams2,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ],
            ]
        );
    }

    /* ---------------------------
     * Query 3: Host Service Statuses
     * ---------------------------
     * Almost the same filter as Query 2 but with additional conditions on description.
     */
    $whereService3 = $whereService2;

    $query3 = <<<SQL
            SELECT DISTINCT 1 AS REALTIME, T1.service_id, T1.description, T1.state, T1.host_id, T2.name
            FROM services T1
            INNER JOIN hosts T2 ON T1.host_id = T2.host_id
            $aclJoin
            WHERE T1.enabled = 1
                AND T1.description NOT LIKE 'ba\_%'
                AND T1.description NOT LIKE 'meta\_%'
                $aclSubRequest
                AND $whereService3
        SQL;
    $bindParams3 = $bindParams2;

    try {
        $stmt3 = $db->prepareQuery($query3);
        $db->executePreparedQuery($stmt3, $bindParams3, true);
        while ($row = $db->fetch($stmt3)) {
            if (isset($data_service[$row['description']])) {
                $data_service[$row['description']]['hosts'][] = $row['host_id'];
                $data_service[$row['description']]['hostsStatus'][$row['host_id']] = $colors[$row['state']];
                $data_service[$row['description']]['details_uri'][$row['host_id']] = $useDeprecatedPages
                    ? '../../main.php?p=20201&o=svcd&host_name=' . $row['name'] . '&service_description=' . $row['description']
                    : $resourceController->buildServiceDetailsUri($row['host_id'], $row['service_id']);
            }
        }
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while fetching host service statuses for widget',
            [
                'query' => $query3,
                'bindParams' => $bindParams3,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ],
            ]
        );
    }
}

$template->assign('theme', $variablesThemeCSS);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('data', $data);
$template->assign('data_service', $data_service);

/* Display */
$template->display('table.ihtml');
