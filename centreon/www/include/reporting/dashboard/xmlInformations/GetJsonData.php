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
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['centreon'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$centreon = $_SESSION['centreon'];
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

// Session check
$sid = session_id();
$DBRESULT = $pearDB->query("SELECT * FROM session WHERE session_id = '" . $pearDB->escape($sid) . "'");
if (!$DBRESULT->rowCount()) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$type = $_GET['type'] ?? '';
$id = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT);

if ($id === false) {
    echo json_encode(['error' => 'Bad id format']);
    exit;
}

// ACL check
$isAdmin = $centreon->user->admin;
$userId = $centreon->user->user_id;
$acl = null;
if (!$isAdmin) {
    $acl = new CentreonACL($userId, $isAdmin);
}

$result = [];

switch ($type) {
    case 'Host':
        if (!$isAdmin && !$acl->checkHost($id)) {
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        $query = 'SELECT date_start, date_end, UPTimeScheduled, DOWNTimeScheduled, '
            . 'UNREACHABLETimeScheduled, UNDETERMINEDTimeScheduled, MaintenanceTime, '
            . 'UPnbEvent, DOWNnbEvent, UNREACHABLEnbEvent '
            . 'FROM log_archive_host WHERE host_id = :id ORDER BY date_start ASC';
        $stmt = $pearDBO->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $total = $row['UPTimeScheduled'] + $row['DOWNTimeScheduled']
                + $row['UNREACHABLETimeScheduled'] + $row['UNDETERMINEDTimeScheduled'];
            $result[] = [
                'date' => date('Y-m-d', $row['date_start']),
                'up' => $total ? round($row['UPTimeScheduled'] / $total * 100, 2) : 0,
                'down' => $total ? round($row['DOWNTimeScheduled'] / $total * 100, 2) : 0,
                'unreachable' => $total ? round($row['UNREACHABLETimeScheduled'] / $total * 100, 2) : 0,
                'undetermined' => $total ? round($row['UNDETERMINEDTimeScheduled'] / $total * 100, 2) : 0,
                'maintenance' => $total ? round($row['MaintenanceTime'] / $total * 100, 2) : 0,
                'alerts_up' => (int) $row['UPnbEvent'],
                'alerts_down' => (int) $row['DOWNnbEvent'],
                'alerts_unreachable' => (int) $row['UNREACHABLEnbEvent'],
                'alerts_total' => (int) ($row['UPnbEvent'] + $row['DOWNnbEvent'] + $row['UNREACHABLEnbEvent']),
            ];
        }
        break;

    case 'Service':
        $hostId = filter_var($_GET['host_id'] ?? false, FILTER_VALIDATE_INT);
        if ($hostId === false) {
            echo json_encode(['error' => 'Bad host_id format']);
            exit;
        }
        $query = 'SELECT date_start, date_end, OKTimeScheduled, WARNINGTimeScheduled, '
            . 'CRITICALTimeScheduled, UNKNOWNTimeScheduled, UNDETERMINEDTimeScheduled, MaintenanceTime, '
            . 'OKnbEvent, WARNINGnbEvent, CRITICALnbEvent, UNKNOWNnbEvent '
            . 'FROM log_archive_service WHERE host_id = :host_id AND service_id = :id ORDER BY date_start ASC';
        $stmt = $pearDBO->prepare($query);
        $stmt->bindValue(':host_id', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $total = $row['OKTimeScheduled'] + $row['WARNINGTimeScheduled']
                + $row['CRITICALTimeScheduled'] + $row['UNKNOWNTimeScheduled']
                + $row['UNDETERMINEDTimeScheduled'];
            $result[] = [
                'date' => date('Y-m-d', $row['date_start']),
                'ok' => $total ? round($row['OKTimeScheduled'] / $total * 100, 2) : 0,
                'warning' => $total ? round($row['WARNINGTimeScheduled'] / $total * 100, 2) : 0,
                'critical' => $total ? round($row['CRITICALTimeScheduled'] / $total * 100, 2) : 0,
                'unknown' => $total ? round($row['UNKNOWNTimeScheduled'] / $total * 100, 2) : 0,
                'undetermined' => $total ? round($row['UNDETERMINEDTimeScheduled'] / $total * 100, 2) : 0,
                'maintenance' => $total ? round($row['MaintenanceTime'] / $total * 100, 2) : 0,
                'alerts_ok' => (int) $row['OKnbEvent'],
                'alerts_warning' => (int) $row['WARNINGnbEvent'],
                'alerts_critical' => (int) $row['CRITICALnbEvent'],
                'alerts_unknown' => (int) $row['UNKNOWNnbEvent'],
                'alerts_total' => (int) ($row['OKnbEvent'] + $row['WARNINGnbEvent'] + $row['CRITICALnbEvent'] + $row['UNKNOWNnbEvent']),
            ];
        }
        break;

    case 'HostGroup':
        // Step 1: Get host IDs belonging to this hostgroup from centreon config DB
        $hgStmt = $pearDB->prepare(
            'SELECT host_host_id FROM hostgroup_relation WHERE hostgroup_hg_id = :id'
        );
        $hgStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $hgStmt->execute();
        $hostIdsInGroup = [];
        while ($hgRow = $hgStmt->fetch()) {
            $hostIdsInGroup[] = (int) $hgRow['host_host_id'];
        }

        // Apply ACL filtering
        if (!$isAdmin) {
            $aclHostIds = array_keys($acl->getHostAclConf(null, 'broker'));
            $hostIdsInGroup = array_intersect($hostIdsInGroup, array_map('intval', $aclHostIds));
        }

        if (empty($hostIdsInGroup)) {
            break;
        }

        // Step 2: Query log_archive_host in centstorage for these host IDs
        $hostIdList = implode(',', $hostIdsInGroup);
        $query = 'SELECT date_start, '
            . 'SUM(UPTimeScheduled) as UPTimeScheduled, SUM(DOWNTimeScheduled) as DOWNTimeScheduled, '
            . 'SUM(UNREACHABLETimeScheduled) as UNREACHABLETimeScheduled, '
            . 'SUM(UNDETERMINEDTimeScheduled) as UNDETERMINEDTimeScheduled, '
            . 'SUM(MaintenanceTime) as MaintenanceTime, '
            . 'SUM(UPnbEvent) as UPnbEvent, SUM(DOWNnbEvent) as DOWNnbEvent, '
            . 'SUM(UNREACHABLEnbEvent) as UNREACHABLEnbEvent '
            . 'FROM log_archive_host '
            . 'WHERE host_id IN (' . $hostIdList . ') '
            . 'GROUP BY date_start ORDER BY date_start ASC';

        $stmt = $pearDBO->query($query);

        while ($row = $stmt->fetch()) {
            $total = $row['UPTimeScheduled'] + $row['DOWNTimeScheduled']
                + $row['UNREACHABLETimeScheduled'] + $row['UNDETERMINEDTimeScheduled'];
            $result[] = [
                'date' => date('Y-m-d', $row['date_start']),
                'up' => $total ? round($row['UPTimeScheduled'] / $total * 100, 2) : 0,
                'down' => $total ? round($row['DOWNTimeScheduled'] / $total * 100, 2) : 0,
                'unreachable' => $total ? round($row['UNREACHABLETimeScheduled'] / $total * 100, 2) : 0,
                'undetermined' => $total ? round($row['UNDETERMINEDTimeScheduled'] / $total * 100, 2) : 0,
                'maintenance' => $total ? round($row['MaintenanceTime'] / $total * 100, 2) : 0,
                'alerts_up' => (int) $row['UPnbEvent'],
                'alerts_down' => (int) $row['DOWNnbEvent'],
                'alerts_unreachable' => (int) $row['UNREACHABLEnbEvent'],
                'alerts_total' => (int) ($row['UPnbEvent'] + $row['DOWNnbEvent'] + $row['UNREACHABLEnbEvent']),
            ];
        }
        break;

    case 'ServiceGroup':
        // Step 1: Get host_id + service_id pairs from centreon config DB
        $sgStmt = $pearDB->prepare(
            'SELECT host_host_id, service_service_id FROM servicegroup_relation WHERE servicegroup_sg_id = :id'
        );
        $sgStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $sgStmt->execute();
        $svcPairs = [];
        $hostIdsInGroup = [];
        while ($sgRow = $sgStmt->fetch()) {
            $hid = (int) $sgRow['host_host_id'];
            $sid = (int) $sgRow['service_service_id'];
            $svcPairs[] = '(las.host_id = ' . $hid . ' AND las.service_id = ' . $sid . ')';
            $hostIdsInGroup[] = $hid;
        }

        // Apply ACL filtering
        if (!$isAdmin) {
            $aclHostIds = array_map('intval', array_keys($acl->getHostAclConf(null, 'broker')));
            $filteredPairs = [];
            $sgStmt->execute();
            while ($sgRow = $sgStmt->fetch()) {
                $hid = (int) $sgRow['host_host_id'];
                $sid = (int) $sgRow['service_service_id'];
                if (in_array($hid, $aclHostIds)) {
                    $filteredPairs[] = '(las.host_id = ' . $hid . ' AND las.service_id = ' . $sid . ')';
                }
            }
            $svcPairs = $filteredPairs;
        }

        if (empty($svcPairs)) {
            break;
        }

        // Step 2: Query log_archive_service in centstorage
        $query = 'SELECT date_start, '
            . 'SUM(OKTimeScheduled) as OKTimeScheduled, SUM(WARNINGTimeScheduled) as WARNINGTimeScheduled, '
            . 'SUM(CRITICALTimeScheduled) as CRITICALTimeScheduled, '
            . 'SUM(UNKNOWNTimeScheduled) as UNKNOWNTimeScheduled, '
            . 'SUM(UNDETERMINEDTimeScheduled) as UNDETERMINEDTimeScheduled, '
            . 'SUM(MaintenanceTime) as MaintenanceTime, '
            . 'SUM(OKnbEvent) as OKnbEvent, SUM(WARNINGnbEvent) as WARNINGnbEvent, '
            . 'SUM(CRITICALnbEvent) as CRITICALnbEvent, SUM(UNKNOWNnbEvent) as UNKNOWNnbEvent '
            . 'FROM log_archive_service las '
            . 'WHERE (' . implode(' OR ', $svcPairs) . ') '
            . 'GROUP BY date_start ORDER BY date_start ASC';

        $stmt = $pearDBO->query($query);

        while ($row = $stmt->fetch()) {
            $total = $row['OKTimeScheduled'] + $row['WARNINGTimeScheduled']
                + $row['CRITICALTimeScheduled'] + $row['UNKNOWNTimeScheduled']
                + $row['UNDETERMINEDTimeScheduled'];
            $result[] = [
                'date' => date('Y-m-d', $row['date_start']),
                'ok' => $total ? round($row['OKTimeScheduled'] / $total * 100, 2) : 0,
                'warning' => $total ? round($row['WARNINGTimeScheduled'] / $total * 100, 2) : 0,
                'critical' => $total ? round($row['CRITICALTimeScheduled'] / $total * 100, 2) : 0,
                'unknown' => $total ? round($row['UNKNOWNTimeScheduled'] / $total * 100, 2) : 0,
                'undetermined' => $total ? round($row['UNDETERMINEDTimeScheduled'] / $total * 100, 2) : 0,
                'maintenance' => $total ? round($row['MaintenanceTime'] / $total * 100, 2) : 0,
                'alerts_ok' => (int) $row['OKnbEvent'],
                'alerts_warning' => (int) $row['WARNINGnbEvent'],
                'alerts_critical' => (int) $row['CRITICALnbEvent'],
                'alerts_unknown' => (int) $row['UNKNOWNnbEvent'],
                'alerts_total' => (int) ($row['OKnbEvent'] + $row['WARNINGnbEvent'] + $row['CRITICALnbEvent'] + $row['UNKNOWNnbEvent']),
            ];
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown type']);
        exit;
}

echo json_encode($result);
