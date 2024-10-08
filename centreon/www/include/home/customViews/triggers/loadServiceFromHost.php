<?php
/**
 * Copyright 2005-2023 Centreon
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

require_once realpath(__DIR__ . "/../../../../../config/centreon.config.php");
require_once _CENTREON_PATH_ . "www/class/centreon.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonDB.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonSession.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonUser.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonXML.class.php";

session_start();
session_write_close();

if (!isset($_POST['data']) || !isset($_SESSION['centreon'])) {
    exit;
}

$centreon = $_SESSION['centreon'];
$hostId = filter_var($_POST['data'], FILTER_VALIDATE_INT);
$db = new CentreonDB();
$pearDB = $db;

if (CentreonSession::checkSession(session_id(), $db) === false) {
    exit();
}
$monitoringDb = new CentreonDB('centstorage');

$xml = new CentreonXML();

$xml->startElement('response');
try {
    $xml->startElement('options');
    if ($hostId !== false && $hostId > 0) {
        $aclString = $centreon->user->access->queryBuilder(
            'AND',
            's.service_id',
            $centreon->user->access->getServicesString('ID', $monitoringDb)
        );
        $sql = "SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr
        		WHERE hsr.host_host_id = :hostId
        		AND hsr.service_service_id = s.service_id ";
        $sql .= $aclString;
        $sql .= " UNION ";
        $sql .= " SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr, hostgroup_relation hgr
        		WHERE hsr.hostgroup_hg_id = hgr.hostgroup_hg_id
        		AND hgr.host_host_id = :hostId
        		AND hsr.service_service_id = s.service_id ";
        $sql .= $aclString;
        $sql .= " ORDER BY service_description ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $xml->startElement('option');
            $xml->writeElement('id', $row['service_id']);
            // For meta services, use display_name column instead of service_description
            $serviceDescription = (preg_match('/meta_/', $row['service_description'])) 
                ? $row['display_name'] : $row['service_description'];
            $xml->writeElement('label', $serviceDescription);
            $xml->endElement();
        }
    }
    $xml->endElement();
} catch (CentreonCustomViewException|CentreonWidgetException $e) {
    $xml->writeElement('error', $e->getMessage());
}
$xml->endElement();

header('Content-Type: text/xml');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');

$xml->output();
