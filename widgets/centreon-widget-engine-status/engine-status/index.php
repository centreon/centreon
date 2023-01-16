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
$widgetId = filter_var($_REQUEST['widgetId'], FILTER_VALIDATE_INT);

try {
    if ($widgetId === false) {
        throw new \InvalidArgumentException('Widget ID must be an integer');
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

    $autoRefresh = filter_var($preferences['autoRefresh'], FILTER_VALIDATE_INT);
    if ($autoRefresh === false || $autoRefresh < 5) {
        $autoRefresh = 30;
    }
    $variablesThemeCSS = match ($centreon->user->theme) {
        'light' => "Generic-theme",
        'dark' => "Centreon-Dark",
        default => throw new \Exception('Unknown user theme : ' . $centreon->user->theme),
    };
} catch (InvalidArgumentException $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$path = $centreon_path . 'www/widgets/engine-status/src/';
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, './', $centreon_path);

$dataLat = array();
$dataEx = array();
$dataSth = array();
$dataSts = array();
$db = new CentreonDB("centstorage");

$idP = (int) $preferences['poller'];

$sql = "SELECT 1 as REALTIME,
    Max(T1.latency) as h_max,
    AVG(T1.latency) as h_moy,
    Max(T2.latency) as s_max,
    AVG(T2.latency) as s_moy
    FROM hosts T1, services T2
    WHERE T1.instance_id = :idP AND T1.host_id = T2.host_id AND T2.enabled = '1' and T2.check_type = '0'";

$res = $db->prepare($sql);
$res->bindValue(':idP', $idP, \PDO::PARAM_INT);
$res->execute();
while ($row = $res->fetch()) {
    $row['h_max'] = round($row['h_max'], 3);
    $row['h_moy'] = round($row['h_moy'], 3);
    $row['s_max'] = round($row['s_max'], 3);
    $row['s_moy'] = round($row['s_moy'], 3);
    $dataLat[] = $row;
}

$sql = "SELECT 1 as REALTIME, 
    Max(T1.execution_time) as h_max,
    AVG(T1.execution_time) as h_moy,
    Max(T2.execution_time) as s_max,
    AVG(T2.execution_time) as s_moy
    FROM hosts T1, services T2
    WHERE T1.instance_id = :idP AND T1.host_id = T2.host_id AND T2.enabled = '1' and T2.check_type = '0'";

$res = $db->prepare($sql);
$res->bindValue(':idP', $idP, \PDO::PARAM_INT);
$res->execute();
while ($row = $res->fetch()) {
    $row['h_max'] = round($row['h_max'], 3);
    $row['h_moy'] = round($row['h_moy'], 3);
    $row['s_max'] = round($row['s_max'], 3);
    $row['s_moy'] = round($row['s_moy'], 3);
    $dataEx[] = $row;
}

$sql = "SELECT 1 as REALTIME,
    SUM(CASE WHEN h.state = 1 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Dow,
    SUM(CASE WHEN h.state = 2 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Un,
    SUM(CASE WHEN h.state = 0 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Up,
    SUM(CASE WHEN h.state = 4 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Pend
    FROM hosts h
    WHERE h.instance_id = :idP";

$res = $db->prepare($sql);
$res->bindValue(':idP', $idP, \PDO::PARAM_INT);
$res->execute();
while ($row = $res->fetch()) {
    $dataSth[] = $row;
}

$sql = "SELECT 1 as REALTIME,
    SUM(CASE WHEN s.state = 2 AND s.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Cri,
    SUM(CASE WHEN s.state = 1 AND s.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Wa,
    SUM(CASE WHEN s.state = 0 AND s.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Ok,
    SUM(CASE WHEN s.state = 4 AND s.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Pend,
    SUM(CASE WHEN s.state = 3 AND s.enabled = 1 AND h.name not like '%Module%' then 1 else 0 end) as Unk
    FROM services s, hosts h
    WHERE h.host_id = s.host_id AND h.instance_id = :idP";

$res = $db->prepare($sql);
$res->bindValue(':idP', $idP, \PDO::PARAM_INT);
$res->execute();
while ($row = $res->fetch()) {
    $dataSts[] = $row;
}

$avg_l = $preferences['avg-l'];
$avg_e = $preferences['avg-e'];
$max_e = $preferences['max-e'];
$template->assign('avg_l', $avg_l);
$template->assign('avg_e', $avg_e);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('max_e', $max_e);
$template->assign('dataSth', $dataSth);
$template->assign('dataSts', $dataSts);
$template->assign('dataEx', $dataEx);
$template->assign('dataLat', $dataLat);
$template->assign(
    'theme',
    $variablesThemeCSS === 'Generic-theme' ? $variablesThemeCSS . '/Variables-css' : $variablesThemeCSS
);
$template->display('engine-status.ihtml');
