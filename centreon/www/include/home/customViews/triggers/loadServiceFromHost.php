<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';

session_start();
session_write_close();

if (! isset($_POST['data']) || ! isset($_SESSION['centreon'])) {
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
        $sql = 'SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr
        		WHERE hsr.host_host_id = :hostId
        		AND hsr.service_service_id = s.service_id ';
        $sql .= $aclString;
        $sql .= ' UNION ';
        $sql .= ' SELECT service_id, service_description, display_name
        		FROM service s, host_service_relation hsr, hostgroup_relation hgr
        		WHERE hsr.hostgroup_hg_id = hgr.hostgroup_hg_id
        		AND hgr.host_host_id = :hostId
        		AND hsr.service_service_id = s.service_id ';
        $sql .= $aclString;
        $sql .= ' ORDER BY service_description ';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
