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
require_once "/etc/centreon/centreon.conf.php";
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';

//load smarty

require_once $centreon_path . 'GPL_LIB/Smarty/libs/Smarty.class.php';

session_start();

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

try {
    global $pearDB;

    $db_centreon = new CentreonDB();
    $db = new CentreonDB("centstorage");
    $pearDB = $db;

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
    }

    $widgetObj = new CentreonWidget($centreon, $db_centreon);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = 0;
    if (isset($preferences['refresh_interval'])) {
        $autoRefresh = $preferences['refresh_interval'];
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

$path = $centreon_path . "www/widgets/Grid-map/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$data = array();
$data_service = array();
$data_check = array();
$inc = 0;

if (isset($preferences['etendu']) && $preferences['etendu'] == "1") {
$query1 = "select distinct T1.name, T2.host_id
from hosts T1, hosts_hostgroups T2 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
where T1.host_id = T2.host_id
and T2.hostgroup_id = ".$preferences['host_group']."
".($centreon->user->admin == 0 ? " AND T1.host_id = acl.host_id AND T2.host_id = acl.host_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

error_log($query1);

$services_pref = explode(",", $preferences['service']);
$query2 = "select distinct T1.description
From services T1  " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
".($centreon->user->admin == 0 ? " WHERE  T1.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). "";

foreach ($services_pref as $elem) {
  if ($inc == "O") {
    if ($centreon->user->admin == 0) {
      $query2 .= "and T1.description like '";
    }
    else if ($centreon->user->admin != 0) {
      $query2 .= "where T1.description like '";
    }
    $query2 .= "%";
    $query2 .= $elem;
    $query2 .= "%";
    $query2 .= "'";
    $inc = $inc + 1;
  }
  else {
    $query2 .= " or T1.description like '";
    $query2 .= "%";
    $query2 .= $elem;
    $query2 .= "%";
    $query2 .= "'";
    $inc = $inc + 1;
  }
}
$query2 .= ";";

error_log($query2);

$query3 = "SELECT distinct T1.service_id, T1.description, T1.state, T1.host_id
           from services T1 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
           where T1.enabled = 1 and (T1.description not like 'ba_%'
           and T1.description not like 'meta_%'
   ".($centreon->user->admin == 0 ? " AND T1.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). "";

foreach ($services_pref as $elem) {
  $query3 .= " or T1.description like '";
  $query3 .= "%";
  $query3 .= $elem;
  $query3 .= "%";
  $query3 .= "'";
}
$query3 .= ");";

} else {

$query1 = "select distinct T1.name, T2.host_id
from hosts T1, hosts_hostgroups T2 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
where T1.host_id = T2.host_id
and T2.hostgroup_id = ".$preferences['host_group']."
".($centreon->user->admin == 0 ? " AND T1.host_id = acl.host_id AND T2.host_id = acl.host_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

error_log($query1);

$services_pref = explode(",", $preferences['service']);
$query2 = "select distinct T1.description
From services T1  " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
".($centreon->user->admin == 0 ? " WHERE  T1.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). "";

foreach ($services_pref as $elem) {
  if ($inc == "O") {
    if ($centreon->user->admin == 0) {
      $query2 .= "and T1.description like '";
    }
    else if ($centreon->user->admin != 0) {
      $query2 .= "where T1.description like '";
    }
    $query2 .= "%";
    $query2 .= $elem;
    $query2 .= "'";
    $inc = $inc + 1;
  }
  else {
    $query2 .= " or T1.description like '";
    $query2 .= "%";
    $query2 .= $elem;
    $query2 .= "'";
    $inc = $inc + 1;
  }
}
$query2 .= ";";

error_log($query2);

 $query3 = "SELECT distinct T1.service_id, T1.description, T1.state, T1.host_id
           from services T1 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
           where T1.enabled = 1 and (T1.description not like 'ba_%'
           and T1.description not like 'meta_%'
   ".($centreon->user->admin == 0 ? " AND T1.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). "";

 foreach ($services_pref as $elem) {
   $query3 .= " or T1.description like '";
   $query3 .= "%";
   $query3 .= $elem;
   $query3 .= "'";
 }
 $query3 .= ");";
}

error_log($query3);


$res = $db->query($query1);
while ($row = $res->fetchRow()) {
  $data[] = $row;
}

$res2 = $db->query($query2);
while ($row = $res2->fetchRow()) {
  $data_service[$row['description']] = array(
					     'description' => $row['description'],
					     'hosts' => array(),
					     'hostsStatus' => array()
					     );
}


$colors = array(
		0 => '#8FCF3C',
		1 => '#ff9a13',
		2 => '#e00b3d',
		3 => '#bcbdc0',
		4 => '#2AD1D4'
		);

$res3 = $db->query($query3);
while ($row = $res3->fetchRow()) {
  if (isset($data_service[$row['description']])) {
    $data_service[$row['description']]['hosts'][] = $row['host_id'];
    $data_service[$row['description']]['hostsStatus'][$row['host_id']] = $colors[$row['state']];
  }
}


error_log(json_encode($data));
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('data', $data);
$template->assign('data_service', $data_service);
$template->display('table.ihtml');
