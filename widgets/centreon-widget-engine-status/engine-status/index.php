<?php
/**
 * Copyright 2005-2011 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
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
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';

//load smarty
require_once $centreon_path . 'GPL_LIB/Smarty/libs/Smarty.class.php';

CentreonSession::start(1);

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

try {
    global $pearDB;

    $db_centreon = new CentreonDB();
    $db = new CentreonDB("centstorage");
    $pearDB = $db_centreon;

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
    }

    $widgetObj = new CentreonWidget($centreon, $db_centreon);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = 0;
    $autoRefresh = $preferences['autoRefresh'];
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$path = $centreon_path . "www/widgets/engine-status/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$dataLat = array();
$dataEx = array();
$dataSth = array();
$dataSts = array();
$db = new CentreonDB("centstorage");

$queryName = "Select T1.name, T1.instance_id as instance, T2.instance_id
             FROM instances T1, hosts T2
             WHERE T1.name like '".$preferences['poller']."';";

$res = $db->query($queryName);
while ($row = $res->fetchRow()) {
  $idP = $row['instance'];
}

$queryLat = "Select Max(T1.latency) as h_max, AVG(T1.latency) as h_moy, Max(T2.latency) as s_max, AVG(T2.latency) as s_moy 
             from hosts T1, services T2  
             where T1.instance_id = ".$idP." and T1.host_id = T2.host_id and T2.enabled = '1';";

$res = $db->query($queryLat);
while ($row = $res->fetchRow()) {
  $row['h_max'] = round($row['h_max'], 3);
  $row['h_moy'] = round($row['h_moy'], 3);
  $row['s_max'] = round($row['s_max'], 3);
  $row['s_moy'] = round($row['s_moy'], 3);
  $dataLat[] = $row;
}

$queryEx = "Select Max(T1.execution_time) as h_max, AVG(T1.execution_time) as h_moy, Max(T2.execution_time) as s_max, AVG(T2.execution_time) as s_moy 
            from hosts T1, services T2  
            where T1.instance_id = ".$idP." and T1.host_id = T2.host_id and T2.enabled = '1';";

$res = $db->query($queryEx);
while ($row = $res->fetchRow()) {
  $row['h_max'] = round($row['h_max'], 3);
  $row['h_moy'] = round($row['h_moy'], 3);
  $row['s_max'] = round($row['s_max'], 3);
  $row['s_moy'] = round($row['s_moy'], 3);
  $dataEx[] = $row;
}

$querySth = "Select SUM(CASE WHEN h.state = 1 and h.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Dow,
                   SUM(CASE WHEN h.state = 2 and h.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Un,
                   SUM(CASE WHEN h.state = 0 and h.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Up,
                   SUM(CASE WHEN h.state = 4 and h.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Pend
            From hosts h where h.instance_id = ".$idP.";";

$querySts = "Select SUM(CASE WHEN s.state = 2 and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Cri,
                    SUM(CASE WHEN s.state = 1 and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Wa, 
                    SUM(CASE WHEN s.state = 0 and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Ok,
                    SUM(CASE WHEN s.state = 4 and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Pend, 
                    SUM(CASE WHEN s.state = 3 and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 end) as Unk
             From services s, hosts h where h.host_id = s.host_id and h.instance_id = ".$idP.";";

$res = $db->query($querySth);
while ($row = $res->fetchRow()) {
  $dataSth[] = $row;
}

$res = $db->query($querySts);
while ($row = $res->fetchRow()) {
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
$template->display('engine-status.ihtml');
