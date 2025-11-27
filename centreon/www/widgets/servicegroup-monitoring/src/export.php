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

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="servicegroups-monitoring.csv"');

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$configurationDatabase = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $configurationDatabase) == 0) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$realtimeDatabase = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $configurationDatabase);
$serviceGroupService = new ServicegroupMonitoring($realtimeDatabase);
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

if (! $centreon->user->admin) {
    [$bindValues, $bindQuery] = createMultipleBindQuery($aclObj->getServiceGroups(), ':servicegroup_name_', PDO::PARAM_STR);
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, "name IN ($bindQuery)");
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
    $countQuery = 'SELECT COUNT(DISTINCT servicegroups.servicegroup_id) ' . $baseQuery;
    $nbRows = (int) $realtimeDatabase->fetchOne($countQuery, QueryParameters::create($queryParameters));

    // Main SELECT query
    $query = "SELECT DISTINCT 1 AS REALTIME, name, servicegroup_id " . $baseQuery;
    $query .= " ORDER BY $orderBy";

    $data = [];
    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }

    foreach ($realtimeDatabase->iterateAssociative($query, QueryParameters::create($queryParameters)) as $row) {
        $data[$row['name']]['name'] = $row['name'];

        $data[$row['name']]['host_state'] = $serviceGroupService->getHostStates(
            $row['name'],
            (int) $centreon->user->admin === 1,
            $accessGroups,
            $detailMode
        );
        $data[$row['name']]['service_state'] = $serviceGroupService->getServiceStates(
            $row['name'],
            (int) $centreon->user->admin === 1,
            $accessGroups,
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
