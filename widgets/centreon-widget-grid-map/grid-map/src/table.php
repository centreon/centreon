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
$colors = array(
    0 => 'service_ok',
    1 => 'service_warning',
    2 => 'service_critical',
    3 => 'unknown',
    4 => 'pending'
);

try {
    if ($widgetId === false) {
        throw new InvalidArgumentException('Widget ID must be an integer');
    }

    $db_centreon = $dependencyInjector['configuration_db'];
    $db = $dependencyInjector['realtime_db'];

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
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
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

/* Start Smarty Init */
$template = new Smarty();
$template = initSmartyTplForPopup(getcwd() . "/", $template, "./", $centreon_path);

$data = array();
$data_service = array();
$data_check = array();
$inc = 0;

if ($preferences['host_group']) {
    /* Query 1 */
    $query1 = "SELECT DISTINCT T1.name, T2.host_id
        FROM hosts T1, hosts_hostgroups T2 " . ($centreon->user->admin == 0 ? ", centreon_acl acl " : "") . "
        WHERE T1.host_id = T2.host_id
            AND T1.enabled = 1
            AND T2.hostgroup_id = " . $preferences['host_group'] .
        ($centreon->user->admin == 0
            ? " AND T1.host_id = acl.host_id AND T2.host_id = acl.host_id AND acl.group_id IN (" .
            ($grouplistStr != "" ? $grouplistStr : 0) . ")"
            : ""
        ) . "
        ORDER BY T1.name";

    /* Query 2 */
    $query2 = "SELECT distinct T1.description
        FROM services T1 " . ($centreon->user->admin == 0 ? ", centreon_acl acl " : "") . "
        WHERE T1.enabled = 1 " . ($centreon->user->admin == 0
            ? " AND T1.service_id = acl.service_id AND acl.group_id IN (" .
            ($grouplistStr != "" ? $grouplistStr : 0) . ") AND ("
            : " AND ("
        );
    foreach (explode(",", $preferences['service']) as $elem) {
        if (!$inc) {
            $query2 .= "T1.description LIKE '$elem'";
        } else {
            $query2 .= " OR T1.description like '$elem'";
        }
        $inc++;
    }
    $query2 .= ");";

    /* Query 3 */
    $query3 = "SELECT DISTINCT T1.service_id, T1.description, T1.state, T1.host_id, T2.name, T2.host_id
        FROM services T1, hosts T2" . ($centreon->user->admin == 0 ? ", centreon_acl acl " : "") . "
        WHERE T1.enabled = 1 AND T1.host_id = T2.host_id
            AND T1.description NOT LIKE 'ba_%' AND T1.description NOT LIKE 'meta_%' " .
        ($centreon->user->admin == 0
            ? " AND T1.service_id = acl.service_id AND acl.group_id IN (" .
            ($grouplistStr != "" ? $grouplistStr : 0) . ")"
            : ""
        );
    $inc = 0;

    $services = explode(",", $preferences['service']);
    if (count($services)) {
        $query3 .= " AND (";
        foreach ($services as $elem) {
            if (!$inc) {
                $query3 .= "T1.description LIKE '$elem'";
            } else {
                $query3 .= " OR T1.description like '$elem'";
            }
            $inc++;
        }
        $query3 .= ")";
    }

    /* Get host listing */
    $res = $db->query($query1);
    while ($row = $res->fetch()) {
        $row['details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=20202&o=hd&host_name=' . $row['name']
        : $resourceController->buildHostDetailsUri($row['host_id']);
        $data[] = $row;
    }

    /* Get service listing */
    $res2 = $db->query($query2);
    while ($row = $res2->fetch()) {
        $data_service[$row['description']] = array(
            'description' => $row['description'],
            'hosts' => array(),
            'hostsStatus' => [],
            'details_uri' => []
        );
    }

    /* Get host service statuses */
    $res3 = $db->query($query3);
    while ($row = $res3->fetch()) {
        if (isset($data_service[$row['description']])) {
            $data_service[$row['description']]['hosts'][] = $row['host_id'];
            $data_service[$row['description']]['hostsStatus'][$row['host_id']] = $colors[$row['state']];
            $data_service[$row['description']]['details_uri'][$row['host_id']] = $useDeprecatedPages
                ? '../../main.php?p=20201&o=svcd&host_name=' . $row['name']
                    . '&service_description=' . $row['description']
                : $resourceController->buildServiceDetailsUri($row['host_id'], $row['service_id']);
        }
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
