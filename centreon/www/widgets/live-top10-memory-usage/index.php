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

require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'bootstrap.php';
require_once __DIR__ . '/src/function.php';

session_start();

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
$grouplistStr = '';

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
    $db_centreon = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['realtime_db'];

    $widgetObj = new CentreonWidget($centreon, $db_centreon);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = (int)$preferences['refresh_interval'];
    if ($autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

if ($centreon->user->admin == 0) {
    $access = new CentreonACL($centreon->user->get_id());
    $grouplist = $access->getAccessGroups();
    $grouplistStr = $access->getAccessGroupsString();
}

// Smarty template initialization
$path = $centreon_path . "www/widgets/live-top10-memory-usage/src/";
$template = SmartyBC::createSmartyTemplate($path, './');

$data = [];
try {
    $query = "SELECT
            1 AS REALTIME,
            i.host_name,
            i.service_description,
            i.service_id, i.host_id,
            m.current_value/max AS ratio,
            max-current_value AS remaining_space,
            s.state AS status
        FROM
            metrics m,
            hosts h "
        . ($preferences['host_group'] ? ", hosts_hostgroups hg " : "")
        . ($centreon->user->admin == 0 ? ", centreon_acl acl " : "")
        . " , index_data i
        LEFT JOIN services s ON s.service_id  = i.service_id AND s.enabled = 1
        WHERE i.service_description LIKE :service_description
            AND i.id = m.index_id
            AND m.metric_name LIKE :metric_name
            AND i.host_id = h.host_id "
        . ($preferences['host_group']
            ? "AND hg.hostgroup_id = :host_group AND i.host_id = hg.host_id " : "");
    if ($centreon->user->admin == 0) {
        $query .= "AND i.host_id = acl.host_id
            AND i.service_id = acl.service_id
            AND acl.group_id IN (" . ($grouplistStr != "" ? $grouplistStr : 0) . ")";
    }
    $query .= "AND s.enabled = 1
            AND h.enabled = 1
        GROUP BY i.host_id,
            i.service_id,
            i.host_name,
            i.service_description,
            s.state,
            m.current_value,
            m.max
        ORDER BY ratio DESC
        LIMIT :nb_lin;";

    $numLine = 1;
    $in = 0;

    $statement = $db->prepareQuery($query);

    $bindParams = [
        ':service_description' => ['%' . $preferences['service_description'] . '%', PDO::PARAM_STR],
        ':metric_name' => ['%' . $preferences['metric_name'] . '%', PDO::PARAM_STR],
        ':nb_lin' => [$preferences['nb_lin'], PDO::PARAM_INT],
    ];

    if ($preferences['host_group']) {
        $bindParams[':host_group'] = [$preferences['host_group'], PDO::PARAM_INT];
    }

    $db->executePreparedQuery($statement, $bindParams, true);

    while ($row = $db->fetch($statement)) {
        $row['numLin'] = $numLine;
        while ($row['remaining_space'] >= 1024) {
            $row['remaining_space'] = $row['remaining_space'] / 1024;
            $in = $in + 1;
        }
        $row['unit'] = getUnit($in);
        $in = 0;
        $row['remaining_space'] = round($row['remaining_space']);
        $row['ratio'] = ceil($row['ratio'] * 100);
        $row['details_uri'] = $useDeprecatedPages
            ? '../../main.php?p=20201&o=svcd&host_name='
                . $row['host_name']
                . '&service_description='
                . $row['service_description']
            : $resourceController->buildServiceDetailsUri(
                $row['host_id'],
                $row['service_id']
            );
        $data[] = $row;
        $numLine++;
    }
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
        message: "Error fetching memory usage data: " . $e->getMessage(),
        exception: $e
    );

    throw $e;
}

$template->assign('preferences', $preferences);
$template->assign('theme', $variablesThemeCSS);
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('data', $data);
$template->display('table_top10memory.ihtml');
