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

$dataDO = array();
$dataUN = array();
$dataUP = array();
$dataPEND = array();
$dataList = array();
$db = new CentreonDB("centstorage");

$queryDO = "SELECT SUM(CASE WHEN h.state = 1 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as status,
         SUM(CASE WHEN h.acknowledged = 1 AND h.state = 1 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as ack,
         SUM(CASE WHEN h.scheduled_downtime_depth = 1 AND h.state = 1 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as down                
         FROM hosts AS h "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ") 
         GROUP BY host_id) x ON x.host_id = h.host_id AND x.service_id IS NULL" : "") . ";";
$queryUN = "SELECT SUM(CASE WHEN h.state = 2 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as status,
         SUM(CASE WHEN h.acknowledged = 1 AND h.state = 2 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as ack,
         SUM(CASE WHEN h.state = 2 and h.enabled = 1 and h.name not like '%Module% 'and h.scheduled_downtime_depth = 1 THEN 1 ELSE 0 END) as down                 
         FROM hosts AS h "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id) x ON x.host_id = h.host_id AND x.service_id IS NULL" : "") . ";";
$queryUP = "SELECT SUM(CASE WHEN h.state = 0 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as status
         FROM hosts AS h "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id) x ON x.host_id = h.host_id AND x.service_id IS NULL" : "") . ";";
$queryPEND = "SELECT SUM(CASE WHEN h.state = 4 and h.enabled = 1 and h.name not like '%Module%' THEN 1 ELSE 0 END) as status                   
         FROM hosts AS h "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id) x ON x.host_id = h.host_id AND x.service_id IS NULL" : "") . ";";

$numLine = 1;

$res = $db->query($queryDO);
while ($row = $res->fetchRow()) {
  $row['un'] = $row['status'] - ($row['ack'] + $row['down']);
  $dataDO[] = $row;
}

$res = $db->query($queryUN);
while ($row = $res->fetchRow()) {
  $row['un'] = $row['status'] - ($row['ack'] + $row['down']);
  $dataUN[] = $row;
}

$res = $db->query($queryUP);
while ($row = $res->fetchRow()) {
  $dataUP[] = $row;
}

$res = $db->query($queryPEND);
while ($row = $res->fetchRow()) {
  $dataPEND[] = $row;
}

$autoRefresh = $preferences['autoRefresh'];

$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $dataPEND);
$template->assign('dataUP', $dataUP);
$template->assign('dataUN', $dataUN);
$template->assign('dataDO', $dataDO);

$template->display('hosts_status.ihtml');
