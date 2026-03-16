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

/**
 * JSON API endpoint for the reporting heatmap.
 *
 * Returns daily availability percentages and alert counts for a given entity.
 * Called via AJAX (jQuery.getJSON) from ajaxReporting_js.php.
 *
 * GET parameters:
 *   - type    (string)  One of: Host, Service, HostGroup, ServiceGroup
 *   - id      (int)     Entity ID (host_id, service_id, hostgroup_hg_id, servicegroup_sg_id)
 *   - host_id (int)     Required only for type=Service
 *
 * Response format (JSON array):
 *   [
 *     {
 *       "date": "2025-03-15",
 *       "up": 99.5,          // or "ok" for services
 *       "down": 0.3,         // or "warning", "critical", "unknown" for services
 *       "unreachable": 0.1,
 *       "undetermined": 0.1,
 *       "maintenance": 0.0,
 *       "alerts_up": 2,      // or "alerts_ok" etc. for services
 *       "alerts_down": 1,
 *       "alerts_unreachable": 0,
 *       "alerts_total": 3
 *     },
 *     ...
 *   ]
 *
 * Security:
 *   - Session authentication required
 *   - CSRF protection via X-Requested-With header (sent by jQuery automatically)
 *   - ACL checks per entity type (CentreonACL)
 *   - All SQL queries use prepared statements
 */

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';

session_start();

header('Content-Type: application/json');
header('Referrer-Policy: same-origin');

/*
 * ──────────────────────────────────────────────
 *  Authentication & authorization checks
 * ──────────────────────────────────────────────
 */

if (!isset($_SESSION['centreon'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$centreon = $_SESSION['centreon'];
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

// Verify session is still valid in database
$sessionStmt = $pearDB->prepare('SELECT session_id FROM session WHERE session_id = :sid');
$sessionStmt->bindValue(':sid', session_id(), PDO::PARAM_STR);
$sessionStmt->execute();
if (!$sessionStmt->fetch()) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

// CSRF protection — jQuery sends this header automatically on $.getJSON / $.ajax
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

/*
 * ──────────────────────────────────────────────
 *  Input validation
 * ──────────────────────────────────────────────
 */

$type = $_GET['type'] ?? '';
$allowedTypes = ['Host', 'Service', 'HostGroup', 'ServiceGroup'];
if (!in_array($type, $allowedTypes, true)) {
    echo json_encode(['error' => 'Unknown type']);
    exit;
}

$id = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT);
if ($id === false) {
    echo json_encode(['error' => 'Bad id format']);
    exit;
}

// ACL setup — admin users bypass all checks
$isAdmin = $centreon->user->admin;
$acl = null;
if (!$isAdmin) {
    $acl = new CentreonACL($centreon->user->user_id, $isAdmin);
}

/*
 * ──────────────────────────────────────────────
 *  Helper functions to convert DB rows to JSON
 * ──────────────────────────────────────────────
 */

/**
 * Convert a log_archive_host row into a normalized result entry.
 *
 * Computes availability percentages from scheduled time columns
 * and extracts alert event counts.
 *
 * @param array $row PDO fetch result from log_archive_host
 * @return array Normalized entry with date, percentages, and alert counts
 */
function buildHostEntry(array $row): array
{
    $total = $row['UPTimeScheduled'] + $row['DOWNTimeScheduled']
        + $row['UNREACHABLETimeScheduled'] + $row['UNDETERMINEDTimeScheduled'];

    return [
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

/**
 * Convert a log_archive_service row into a normalized result entry.
 *
 * Same principle as buildHostEntry() but for service status columns
 * (OK/WARNING/CRITICAL/UNKNOWN instead of UP/DOWN/UNREACHABLE).
 *
 * @param array $row PDO fetch result from log_archive_service
 * @return array Normalized entry with date, percentages, and alert counts
 */
function buildServiceEntry(array $row): array
{
    $total = $row['OKTimeScheduled'] + $row['WARNINGTimeScheduled']
        + $row['CRITICALTimeScheduled'] + $row['UNKNOWNTimeScheduled']
        + $row['UNDETERMINEDTimeScheduled'];

    return [
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
        'alerts_total' => (int) ($row['OKnbEvent'] + $row['WARNINGnbEvent']
            + $row['CRITICALnbEvent'] + $row['UNKNOWNnbEvent']),
    ];
}

/*
 * ──────────────────────────────────────────────
 *  Data retrieval by entity type
 * ──────────────────────────────────────────────
 *
 * Each case:
 *  1. Validates ACL permissions
 *  2. Queries the log_archive_* tables in centstorage (via $pearDBO)
 *  3. For groups: first resolves member IDs from config DB ($pearDB),
 *     then aggregates (SUM) the archive data
 */

$result = [];

switch ($type) {

    // ── Single Host ──────────────────────────
    case 'Host':
        if (!$isAdmin && !$acl->checkHost($id)) {
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $stmt = $pearDBO->prepare(
            'SELECT date_start, date_end, UPTimeScheduled, DOWNTimeScheduled, '
            . 'UNREACHABLETimeScheduled, UNDETERMINEDTimeScheduled, MaintenanceTime, '
            . 'UPnbEvent, DOWNnbEvent, UNREACHABLEnbEvent '
            . 'FROM log_archive_host WHERE host_id = :id ORDER BY date_start ASC'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $result[] = buildHostEntry($row);
        }
        break;

    // ── Single Service ───────────────────────
    case 'Service':
        $hostId = filter_var($_GET['host_id'] ?? false, FILTER_VALIDATE_INT);
        if ($hostId === false) {
            echo json_encode(['error' => 'Bad host_id format']);
            exit;
        }

        if (!$isAdmin) {
            if (!$acl->checkHost($hostId) || !$acl->checkService($id)) {
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
        }

        $stmt = $pearDBO->prepare(
            'SELECT date_start, date_end, OKTimeScheduled, WARNINGTimeScheduled, '
            . 'CRITICALTimeScheduled, UNKNOWNTimeScheduled, UNDETERMINEDTimeScheduled, '
            . 'MaintenanceTime, OKnbEvent, WARNINGnbEvent, CRITICALnbEvent, UNKNOWNnbEvent '
            . 'FROM log_archive_service '
            . 'WHERE host_id = :host_id AND service_id = :id ORDER BY date_start ASC'
        );
        $stmt->bindValue(':host_id', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $result[] = buildServiceEntry($row);
        }
        break;

    // ── Host Group (aggregated) ──────────────
    case 'HostGroup':
        // Step 1: Resolve host IDs from config DB
        $hgStmt = $pearDB->prepare(
            'SELECT host_host_id FROM hostgroup_relation WHERE hostgroup_hg_id = :id'
        );
        $hgStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $hgStmt->execute();

        $hostIdsInGroup = [];
        while ($hgRow = $hgStmt->fetch()) {
            $hostIdsInGroup[] = (int) $hgRow['host_host_id'];
        }

        // ACL: keep only hosts the user is allowed to see
        if (!$isAdmin) {
            $aclHostIds = array_map('intval', array_keys($acl->getHostAclConf(null, 'broker')));
            $hostIdsInGroup = array_intersect($hostIdsInGroup, $aclHostIds);
        }

        if (empty($hostIdsInGroup)) {
            break;
        }

        // Step 2: Aggregate availability data across all group members
        $placeholders = implode(',', array_fill(0, count($hostIdsInGroup), '?'));
        $stmt = $pearDBO->prepare(
            'SELECT date_start, '
            . 'SUM(UPTimeScheduled) as UPTimeScheduled, '
            . 'SUM(DOWNTimeScheduled) as DOWNTimeScheduled, '
            . 'SUM(UNREACHABLETimeScheduled) as UNREACHABLETimeScheduled, '
            . 'SUM(UNDETERMINEDTimeScheduled) as UNDETERMINEDTimeScheduled, '
            . 'SUM(MaintenanceTime) as MaintenanceTime, '
            . 'SUM(UPnbEvent) as UPnbEvent, '
            . 'SUM(DOWNnbEvent) as DOWNnbEvent, '
            . 'SUM(UNREACHABLEnbEvent) as UNREACHABLEnbEvent '
            . 'FROM log_archive_host '
            . 'WHERE host_id IN (' . $placeholders . ') '
            . 'GROUP BY date_start ORDER BY date_start ASC'
        );
        $stmt->execute(array_values($hostIdsInGroup));

        while ($row = $stmt->fetch()) {
            $result[] = buildHostEntry($row);
        }
        break;

    // ── Service Group (aggregated) ───────────
    case 'ServiceGroup':
        // Step 1: Resolve (host_id, service_id) pairs from config DB
        $sgStmt = $pearDB->prepare(
            'SELECT host_host_id, service_service_id '
            . 'FROM servicegroup_relation WHERE servicegroup_sg_id = :id'
        );
        $sgStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $sgStmt->execute();

        // Collect pairs as parallel arrays:
        //   $svcPairClauses[i] = '(las.host_id = ? AND las.service_id = ?)'
        //   $svcPairValues[i*2] = host_id, $svcPairValues[i*2+1] = service_id
        $svcPairClauses = [];
        $svcPairValues = [];
        while ($sgRow = $sgStmt->fetch()) {
            $hid = (int) $sgRow['host_host_id'];
            $sid = (int) $sgRow['service_service_id'];
            $svcPairClauses[] = '(las.host_id = ? AND las.service_id = ?)';
            $svcPairValues[] = $hid;
            $svcPairValues[] = $sid;
        }

        // ACL: filter out pairs whose host_id is not in user's allowed hosts
        if (!$isAdmin) {
            $aclHostIds = array_map('intval', array_keys($acl->getHostAclConf(null, 'broker')));
            $filteredClauses = [];
            $filteredValues = [];
            for ($i = 0; $i < count($svcPairClauses); $i++) {
                if (in_array($svcPairValues[$i * 2], $aclHostIds, true)) {
                    $filteredClauses[] = $svcPairClauses[$i];
                    $filteredValues[] = $svcPairValues[$i * 2];
                    $filteredValues[] = $svcPairValues[$i * 2 + 1];
                }
            }
            $svcPairClauses = $filteredClauses;
            $svcPairValues = $filteredValues;
        }

        if (empty($svcPairClauses)) {
            break;
        }

        // Step 2: Aggregate availability data across all group members
        $stmt = $pearDBO->prepare(
            'SELECT date_start, '
            . 'SUM(OKTimeScheduled) as OKTimeScheduled, '
            . 'SUM(WARNINGTimeScheduled) as WARNINGTimeScheduled, '
            . 'SUM(CRITICALTimeScheduled) as CRITICALTimeScheduled, '
            . 'SUM(UNKNOWNTimeScheduled) as UNKNOWNTimeScheduled, '
            . 'SUM(UNDETERMINEDTimeScheduled) as UNDETERMINEDTimeScheduled, '
            . 'SUM(MaintenanceTime) as MaintenanceTime, '
            . 'SUM(OKnbEvent) as OKnbEvent, '
            . 'SUM(WARNINGnbEvent) as WARNINGnbEvent, '
            . 'SUM(CRITICALnbEvent) as CRITICALnbEvent, '
            . 'SUM(UNKNOWNnbEvent) as UNKNOWNnbEvent '
            . 'FROM log_archive_service las '
            . 'WHERE (' . implode(' OR ', $svcPairClauses) . ') '
            . 'GROUP BY date_start ORDER BY date_start ASC'
        );
        $stmt->execute($svcPairValues);

        while ($row = $stmt->fetch()) {
            $result[] = buildServiceEntry($row);
        }
        break;
}

echo json_encode($result);
