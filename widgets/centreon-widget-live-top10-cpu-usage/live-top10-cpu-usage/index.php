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

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];

$centreonWebPath = trim($centreon->optGen['oreon_web_path'], '/');

$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);
$grouplistStr = '';

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }
    $db_centreon = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['realtime_db'];

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
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

//configure smarty

if ($centreon->user->admin == 0) {
    $access = new CentreonACL($centreon->user->get_id());
    $grouplist = $access->getAccessGroups();
    $grouplistStr = $access->getAccessGroupsString();
}

$path = $centreon_path . "www/widgets/live-top10-cpu-usage/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$data = array();

$query = "SELECT 1 as REALTIME,
        i.host_name,
        i.service_description,
        i.service_id,
        i.host_id,
        AVG(m.current_value) AS current_value,
        s.state AS status
    FROM
        metrics m,
        hosts h "
    . ($preferences['host_group'] ? ", hosts_hostgroups hg " : "")
    . ($centreon->user->admin == 0 ? ", centreon_acl acl " : "")
    . " , index_data i
    LEFT JOIN services s ON s.service_id  = i.service_id AND s.enabled = 1
    WHERE i.service_description LIKE '%" . $preferences['service_description'] . "%'
    AND i.id = m.index_id
    AND m.metric_name LIKE '%" . $preferences['metric_name'] . "%'
    AND current_value <= 100
    AND i.host_id = h.host_id "
    . ($preferences['host_group']
        ? "AND hg.hostgroup_id = " . $preferences['host_group'] . " AND i.host_id = hg.host_id " : "");
if ($centreon->user->admin == 0) {
    $query .= "AND i.host_id = acl.host_id
        AND i.service_id = acl.service_id
        AND acl.group_id IN (" . ($grouplistStr != "" ? $grouplistStr : 0) . ")";
}
$query .= "AND s.enabled = 1
        AND h.enabled = 1
    GROUP BY i.host_id
    ORDER BY current_value DESC
    LIMIT " . $preferences['nb_lin'] . ";";

$numLine = 1;

$res = $db->query($query);
while ($row = $res->fetch()) {
    $row['numLin'] = $numLine;
    $row['current_value'] = ceil($row['current_value']);
    $row['details_uri'] = $useDeprecatedPages
        ? '/' . $centreonWebPath . '/main.php?p=20201&o=svcd&host_name='
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

$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('data', $data);
$template->assign('theme', $variablesThemeCSS);
$template->display('table_top10cpu.ihtml');
