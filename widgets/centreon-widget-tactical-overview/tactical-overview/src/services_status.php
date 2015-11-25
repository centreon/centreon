<?php
/**
 * Copyright 2005-2015 Centreon
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

$path = $centreon_path . "www/widgets/tactical-overview/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$db_centreon = new CentreonDB("centreon");
$pearDB = $db_centreon;
if (CentreonSession::checkSession(session_id(), $db_centreon) == 0) {
  exit;
}

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

$widgetObj = new CentreonWidget($centreon, $db_centreon);
$preferences = $widgetObj->getWidgetPreferences($widgetId);


if ($centreon->user->admin == 0) {
  $access = new CentreonACL($centreon->user->get_id());
  $grouplist = $access->getAccessGroups();
  $grouplistStr = $access->getAccessGroupsString();
}

$dataCRI = array();
$dataWA = array();
$dataOK = array();
$dataUNK = array();
$dataPEND = array();
$db = new CentreonDB("centstorage");

$queryCRI = "SELECT SUM(CASE WHEN s.state = 2 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as statue,
         SUM(CASE WHEN s.acknowledged = 1 AND s.state = 2 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as ack,                                                                                                      
         SUM(CASE WHEN s.scheduled_downtime_depth = 1 AND s.state = 2 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as down,
         SUM(CASE WHEN s.state = 2 and (h.state = 1 or h.state = 4 or h.state = 2) and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 END) as pb
         FROM services s, hosts h " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). " where h.host_id = s.host_id ".($centreon->user->admin == 0 ? " AND h.host_id = acl.host_id AND s.service_id = acl.service_id AND s.host_id = acl.host_id and acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

$queryWA = "SELECT SUM(CASE WHEN s.state = 1 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as statue,                                                                                                               
         SUM(CASE WHEN s.acknowledged = 1 AND s.state = 1 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as ack,                                                                                                     
         SUM(CASE WHEN s.scheduled_downtime_depth = 1 AND s.state = 1 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as down,
         SUM(CASE WHEN s.state = 1 and (h.state = 1 or h.state = 4 or h.state = 2) and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 END) as pb                                                                               
         FROM services s, hosts h " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). " where h.host_id = s.host_id ".($centreon->user->admin == 0 ? " AND h.host_id = acl.host_id AND s.service_id = acl.service_id AND s.host_id = acl.host_id and acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

$queryOK = "SELECT SUM(CASE WHEN s.state = 0 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as statue                                                                                                                
            FROM services s, hosts h " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). " where h.host_id = s.host_id ".($centreon->user->admin == 0 ? " AND h.host_id = acl.host_id AND s.service_id = acl.service_id AND s.host_id = acl.host_id and acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

$queryPEND = "SELECT SUM(CASE WHEN s.state = 4 and s.enabled and h.name not like '%Module%' THEN 1 ELSE 0 END) as statue                                                                                                              
            FROM services s, hosts h " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). " where h.host_id = s.host_id ".($centreon->user->admin == 0 ? " AND h.host_id = acl.host_id AND s.service_id = acl.service_id AND s.host_id = acl.host_id and acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";

$queryUNK = "SELECT SUM(CASE WHEN s.state = 3 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as statue,
             SUM(CASE WHEN s.acknowledged = 1 AND s.state = 3 and s.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as ack,                                                                                                  
             SUM(CASE WHEN s.state = 3 and s.enabled = 1 and h.name not like '%Module%' and (s.scheduled_downtime_depth = 1 or h.scheduled_downtime_depth = 1) THEN 1 ELSE 0 END) as down,
             SUM(CASE WHEN s.state = 3 and (h.state = 1 or h.state = 4 or h.state = 2) and s.enabled = 1 and h.name not like '%Module%' then 1 else 0 END) as pb
             FROM services s, hosts h " .($centreon->user->admin == 0 ? ", centreon_acl acl" : ""). " where h.host_id = s.host_id ".($centreon->user->admin == 0 ? " AND h.host_id = acl.host_id AND s.service_id = acl.service_id AND s.host_id = acl.host_id and acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")" : ""). ";";


$title ="Default Title";
$numLine = 1;

$res = $db->query($queryCRI);
while ($row = $res->fetchRow()) {
  $row['un'] = $row['statue'] - ($row['ack'] + $row['down'] + $row['pb']);
  $dataCRI[] = $row;
}

$res = $db->query($queryWA);
while ($row = $res->fetchRow()) {
  $row['un'] = $row['statue'] - ($row['ack'] + $row['down'] + $row['pb']);
  $dataWA[] = $row;
}

$res = $db->query($queryOK);
while ($row = $res->fetchRow()) {
  $dataOK[] = $row;
}

$res = $db->query($queryPEND);
while ($row = $res->fetchRow()) {
  $dataPEND[] = $row;
}

$res = $db->query($queryUNK);
while ($row = $res->fetchRow()) {
  $row['un'] = $row['statue'] - ($row['ack'] + $row['down'] + $row['pb']);
  $dataUNK[] = $row;
}

$template->assign('title', $title);
$template->assign('dataPEND', $dataPEND);
$template->assign('dataOK', $dataOK);
$template->assign('dataWA', $dataWA);
$template->assign('dataCRI', $dataCRI);
$template->assign('dataUNK', $dataUNK);
$template->display('services_status.ihtml');
?>
