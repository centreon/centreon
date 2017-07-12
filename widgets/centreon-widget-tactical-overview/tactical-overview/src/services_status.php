<?php
/**
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis AND Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License AS published by the Free Software
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
 * combined work based on this program. Thus, the terms AND conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, AND to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  AND conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

$dataCRI = array();
$dataWA = array();
$dataOK = array();
$dataUNK = array();
$dataPEND = array();
$db = new CentreonDB("centstorage");

$queryCRI = "SELECT SUM(CASE WHEN s.state = 2 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS status,
         SUM(CASE WHEN s.acknowledged = 1 AND s.state = 2 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS ack,
         SUM(CASE WHEN s.scheduled_downtime_depth = 1 AND s.state = 2 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS down,
         SUM(CASE WHEN s.state = 2 AND (h.state = 1 or h.state = 4 or h.state = 2) AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 END) AS pb,
         SUM(CASE WHEN s.state = 2 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0 AND h.state = 0 THEN 1 ELSE 0 END) AS un
         FROM services AS s
         LEFT JOIN hosts AS h ON h.host_id = s.host_id "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id,service_id) x ON x.host_id = h.host_id AND x.service_id = s.service_id" : "") . ";";

$queryWA = "SELECT SUM(CASE WHEN s.state = 1 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS status,
         SUM(CASE WHEN s.acknowledged = 1 AND s.state = 1 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS ack,
         SUM(CASE WHEN s.scheduled_downtime_depth > 0 AND s.state = 1 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS down,
         SUM(CASE WHEN s.state = 1 AND (h.state = 1 or h.state = 4 or h.state = 2) AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 END) AS pb,
         SUM(CASE WHEN s.state = 1 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0 AND h.state = 0 THEN 1 ELSE 0 END) AS un
         FROM services AS s
         LEFT JOIN hosts AS h ON h.host_id = s.host_id "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id,service_id) x ON x.host_id = h.host_id AND x.service_id = s.service_id" : "") . ";";

$queryOK = "SELECT SUM(CASE WHEN s.state = 0 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS status
         FROM services AS s
         LEFT JOIN hosts AS h ON h.host_id = s.host_id "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id,service_id) x ON x.host_id = h.host_id AND x.service_id = s.service_id" : "") . ";";

$queryPEND = "SELECT SUM(CASE WHEN s.state = 4 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS status                                                   
         FROM services AS s
         LEFT JOIN hosts AS h ON h.host_id = s.host_id "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0). ")
         GROUP BY host_id,service_id) x ON x.host_id = h.host_id AND x.service_id = s.service_id" : "") . ";";

$queryUNK = "SELECT SUM(CASE WHEN s.state = 3 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS status,
         SUM(CASE WHEN s.acknowledged = 1 AND s.state = 3 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS ack,
         SUM(CASE WHEN s.scheduled_downtime_depth > 0 AND s.state = 3 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' THEN 1 ELSE 0 END) AS down,
         SUM(CASE WHEN s.state = 3 AND (h.state = 1 or h.state = 4 or h.state = 2) AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' then 1 else 0 END) AS pb,
         SUM(CASE WHEN s.state = 3 AND s.enabled = 1 AND h.enabled = 1 AND h.name not like '%Module%' AND s.acknowledged = 0 AND s.scheduled_downtime_depth = 0 AND h.state = 0 THEN 1 ELSE 0 END) AS un
         FROM services AS s
         LEFT JOIN hosts AS h ON h.host_id = s.host_id "
         .($centreon->user->admin == 0 ? "JOIN (SELECT acl.host_id, acl.service_id FROM centreon_acl AS acl WHERE acl.group_id IN (" .($grouplistStr != "" ? $grouplistStr : 0).")
         GROUP BY host_id,service_id) x ON x.host_id = h.host_id AND x.service_id = s.service_id" : "") . ";";

$numLine = 1;

$res = $db->query($queryCRI);
while ($row = $res->fetchRow()) {
  $dataCRI[] = $row;
}

$res = $db->query($queryWA);
while ($row = $res->fetchRow()) {
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
  $dataUNK[] = $row;
}

$autoRefresh = $preferences['autoRefresh'];

$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $dataPEND);
$template->assign('dataOK', $dataOK);
$template->assign('dataWA', $dataWA);
$template->assign('dataCRI', $dataCRI);
$template->assign('dataUNK', $dataUNK);

$template->display('services_status.ihtml');
