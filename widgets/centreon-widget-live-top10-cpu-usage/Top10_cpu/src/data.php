<?php
/*
 * Copyright 2005-2015 CENTREON
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

ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

//require_once "../../require.php";
require_once "/usr/share/centreon/www/widgets/require.php";
require_once "./DB-Func.php";
require_once "/etc/centreon/centreon.conf.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';

 // Load specific Smarty class //
require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";

error_log("Après require");

// check if session is alive //
session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}


$db_centreon = new CentreonDB("centreon");
$pearDB = $db_centreon;
if (CentreonSession::checkSession(session_id(), $db_centreon) == 0) {
    exit;
}

error_log("Debut widget claire");

// Configure new smarty object
$path = $centreon_path . "www/widgets/Top10_cpu/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

// Get widgets info & parameters
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

$widgetObj = new CentreonWidget($centreon, $db_centreon);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

// Beginning of the specific widget code

if (isset($preferences['ba_id']) && $preferences['ba_id']!='') {
    $baID = $preferences['ba_id'];
    $reportingPeriod = $preferences['reporting_period'];
}else{
   $baID = 0;
    $reportingPeriod= 0;
}


if ($centreon->user->admin == 0) {
  $access = new CentreonACL($centreon->user->get_id());
  $grouplist = $access->getAccessGroups();
  $grouplistStr = $access->getAccessGroupsString();
}

// Get the right date regarding the parameter

$reportingPeriodStart = 0;
$reportingPeriodEnd = 0;
$periodName = "defaultName";
$orderBy = 'start_time';

$data = array();
$db = new CentreonDB("centstorage");

if ($preferences['host_group'] == ''){
  $query = "SELECT DISTINCT T2.host_name, T2.service_description, T2.service_id, T2.host_id, AVG(T3.current_value) as current_value, state as status
FROM services T1, index_data T2, metrics T3 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "
WHERE T2.service_description LIKE '%".$preferences['service_description']."%'
AND T3.index_id = T2.id
AND T3.metric_name LIKE '%".$preferences['metric_name']."%'
and T2.host_id = T1.host_id
and T2.service_id = T1.service_id
and current_value <= 100
".($centreon->user->admin == 0 ? " AND T2.host_id = acl.host_id AND T2.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). "
group by T2.host_id  ORDER BY current_value DESC LIMIT ".$preferences['nb_lin'].";";

} else {

$query = "SELECT T2.host_name, T2.service_description, T2.service_id, T2.host_id, AVG(T3.current_value) as current_value, state as status, T5.hostgroup_id 
FROM services T1, index_data T2, metrics T3, hosts_hostgroups T5 " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). "                                                               
WHERE T2.service_description LIKE '%".$preferences['service_description']."%' 
AND T3.index_id = id 
AND T3.metric_name LIKE '%".$preferences['metric_name']."%' 
and T2.host_id = T1.host_id 
and T2.service_id = T1.service_id 
and T5.hostgroup_id = ".$preferences['host_group']."
and T1.host_id = T5.host_id 
and current_value <= 100
" .($centreon->user->admin == 0 ? " AND T2.host_id = acl.host_id AND T2.service_id = acl.service_id AND acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")" : ""). "
group by T2.host_id  ORDER BY current_value DESC LIMIT ".$preferences['nb_lin'].";";
}

error_log($query);
$title ="Default Title";
$title = $preferences['title'];
$numLine = 1;

$res = $db->query($query);
while ($row = $res->fetchRow()) {
 $row['numLin'] = $numLine; 
 $row['current_value'] = ceil($row['current_value']); 
 $data[] = $row;
 $numLine++;
}
error_log(json_encode($data));
$template->assign('title', $title);
$template->assign('data', $data);
$template->display('table_top10cpu.ihtml');
?>
