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
header('Content-Disposition: attachment; filename="hostgroups-monitoring.csv"');

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';
require_once $centreon_path . 'www/widgets/hostgroup-monitoring/src/class/HostgroupMonitoring.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/hostgroup-monitoring/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
if ($widgetId === false) {
    throw new InvalidArgumentException('Widget ID must be an integer');
}

$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new HostgroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$hostStateLabels = [0 => "Up", 1 => "Down", 2 => "Unreachable", 4 => "Pending"];

$serviceStateLabels = [0 => "Ok", 1 => "Warning", 2 => "Critical", 3 => "Unknown", 4 => "Pending"];

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';

try {
    $columns = 'SELECT DISTINCT 1 AS REALTIME, name ';
    $baseQuery = ' FROM hostgroups';

    $bindParams = [];
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
            $bindParams[':search'] = [$search, PDO::PARAM_STR];
        }
    }

    if (!$centreon->user->admin) {
        [$bindValues, $bindQuery] = createMultipleBindQuery($aclObj->getHostGroups(), ':hostgroup_name_', PDO::PARAM_STR);
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, "name IN ($bindQuery)");
        $bindParams = array_merge($bindParams, $bindValues);
    }
    $orderby = "name " . ORDER_DIRECTION_ASC;


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

    // Query to count total rows
    $countQuery = "SELECT COUNT(*) " . $baseQuery;

    // Main SELECT query
    $query = $columns . $baseQuery;
    $query .= " ORDER BY $orderby";

    // Execute count query
    if (! empty($bindParams)) {
        $countStatement = $dbb->prepareQuery($countQuery);
        $dbb->executePreparedQuery($countStatement, $bindParams, true);
    } else {
        $countStatement= $dbb->executeQuery($countQuery);
    }

    $nbRows = (int) $dbb->fetchColumn($countStatement);

    // Execute main query
    if (! empty($bindParams)) {
        $statement = $dbb->prepareQuery($query);
        $dbb->executePreparedQuery($statement, $bindParams, true);
    } else {
        $statement = $dbb->executeQuery($query);
    }

    $detailMode = false;
    if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
        $detailMode = true;
    }
    $data = [];
    while ($row = $dbb->fetch($statement)) {
        $name = HtmlSanitizer::createFromString($row['name'])->sanitize()->getString();
        $data[$name]['name'] = $name;
    }
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: "Error fetching hostgroup monitoring usage data for export: " . $e->getMessage(),
        exception: $e
    );

    throw new \Exception("Error fetching hostgroup monitoring usage data for export: " . $e->getMessage());
}

$sgMonObj->getHostStates($data, $centreon->user->admin, $aclObj, $preferences, $detailMode);
$sgMonObj->getServiceStates($data, $centreon->user->admin, $aclObj, $preferences, $detailMode);

$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('data', $data);

$template->display('export.ihtml');
