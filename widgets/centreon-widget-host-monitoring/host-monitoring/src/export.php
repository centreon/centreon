<?php

/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

require_once '../../require.php';
require_once './DB-Func.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

session_start();
if (!isset($_SESSION['centreon'], $_GET['widgetId'], $_GET['list'])) {
    // As the header is already defined, if one of these parameters is missing, an empty CSV is exported
    exit;
}

$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}
$dbb = $dependencyInjector['realtime_db'];

/* Init Objects */
$criticality = new CentreonCriticality($db);
$aStateType = ['1' => 'H', '0' => 'S'];

$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

/**
 * Sanitize and concatenate selected resources for the query
 */
// Check returned list and make an array of it
if (false !== strpos($_GET['list'], ',')) {
    $exportList = explode(',', $_GET['list']);
} else {
    $exportList[] = $_GET['list'];
}
$mainQueryParameters = [];
$hostQuery = '';
// Check consistency, sanitize and bind values
foreach ($exportList as $key => $hostId) {
    if (0 === (int)$hostId) {
        // skip non consistent dat
        continue;
    }
    if (!empty($hostQuery)) {
        $hostQuery .= ', ';
    }
    $hostQuery .= ':' . $key . 'host' . $hostId;
    $mainQueryParameters[] = [
        'parameter' => ':' . $key . 'host' . $hostId,
        'value' => (int)$hostId,
        'type' => \PDO::PARAM_INT
    ];
}

$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

// Get status labels
$stateLabels = getLabels();

// Request
$query = 'SELECT SQL_CALC_FOUND_ROWS
    h.host_id,
    h.name,
    h.alias,
    h.flapping,
    state,
    state_type,
    address,
    last_hard_state,
    output,
    scheduled_downtime_depth,
    acknowledged,
    notify,
    active_checks,
    passive_checks,
    last_check,
    last_state_change,
    last_hard_state_change,
    check_attempt,
    max_check_attempts,
    action_url,
    notes_url,
    cv.value AS criticality,
    h.icon_image,
    h.icon_image_alt,
    cv2.value AS criticality_id,
    cv.name IS NULL as isnull
    FROM hosts h
    LEFT JOIN `customvariables` cv
        ON (cv.host_id = h.host_id AND cv.service_id IS NULL AND cv.name = \'CRITICALITY_LEVEL\')
    LEFT JOIN `customvariables` cv2
        ON (cv2.host_id = h.host_id AND cv2.service_id IS NULL AND cv2.name = \'CRITICALITY_ID\')
    WHERE enabled = 1
    AND h.name NOT LIKE \'_Module_%\' ';

if (!empty($hostQuery)) {
    $query .= 'AND h.host_id IN (' . $hostQuery . ') ';
}

if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != "") {
    $tab = explode(' ', $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = [
            'parameter' => ':host_name_search',
            'value' => $search,
            'type' => \PDO::PARAM_STR
        ];
        $hostNameCondition = 'h.name ' . CentreonUtils::operandToMysqlFormat($op) . ' :host_name_search ';
        $query = CentreonUtils::conditionBuilder($query, $hostNameCondition);
    }
}

$stateTab = [];
if (isset($preferences['host_up']) && $preferences['host_up']) {
    $stateTab[] = 0;
}
if (isset($preferences['host_down']) && $preferences['host_down']) {
    $stateTab[] = 1;
}
if (isset($preferences['host_unreachable']) && $preferences['host_unreachable']) {
    $stateTab[] = 2;
}
if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, ' state IN (' . implode(',', $stateTab) . ')');
}

if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == 'ack') {
        $query = CentreonUtils::conditionBuilder($query, ' acknowledged = 1');
    } elseif ($preferences['acknowledgement_filter'] == 'nack') {
        $query = CentreonUtils::conditionBuilder($query, ' acknowledged = 0');
    }
}

if (isset($preferences['notification_filter']) && $preferences['notification_filter']) {
    if ($preferences['notification_filter'] == "enabled") {
        $query = CentreonUtils::conditionBuilder($query, " notify = 1");
    } elseif ($preferences['notification_filter'] == "disabled") {
        $query = CentreonUtils::conditionBuilder($query, " notify = 0");
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == 'downtime') {
        $query = CentreonUtils::conditionBuilder($query, ' scheduled_downtime_depth	> 0 ');
    } elseif ($preferences['downtime_filter'] == 'ndowntime') {
        $query = CentreonUtils::conditionBuilder($query, ' scheduled_downtime_depth	= 0 ');
    }
}

if (isset($preferences['poller_filter']) && $preferences['poller_filter']) {
    $query = CentreonUtils::conditionBuilder($query, ' instance_id = ' . $preferences['poller_filter'] . ' ');
}

if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == 'hardonly') {
        $query = CentreonUtils::conditionBuilder($query, ' state_type = 1 ');
    } elseif ($preferences['state_type_filter'] == 'softonly') {
        $query = CentreonUtils::conditionBuilder($query, ' state_type = 0 ');
    }
}

if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $results = explode(',', $preferences['hostgroup']);
    $queryHg = '';
    foreach ($results as $result) {
        if ($queryHg != '') {
            $queryHg .= ', ';
        }
        $queryHg .= ":id_" . $result;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $result,
            'value' => (int)$result,
            'type' => \PDO::PARAM_INT
        ];
    }
    $hostgroupHgIdCondition = <<<SQL
h.host_id IN (
      SELECT host_id
      FROM hosts_hostgroups
      WHERE hostgroup_id IN ({$queryHg}))
SQL;
    $query = CentreonUtils::conditionBuilder($query, $hostgroupHgIdCondition);
}
if (!empty($preferences['display_severities']) && !empty($preferences['criticality_filter'])) {
    $tab = explode(',', $preferences['criticality_filter']);
    $labels = '';
    foreach ($tab as $p) {
        if ($labels != '') {
            $labels .= ',';
        }
        $labels .= ":id_" . $p;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $p,
            'value' => (int)$p,
            'type' => \PDO::PARAM_INT
        ];
    }
    $SeverityIdCondition =
        "h.host_id IN (
            SELECT DISTINCT host_host_id
            FROM `{$conf_centreon['db']}`.hostcategories_relation
            WHERE hostcategories_hc_id IN ({$labels}))";
    $query = CentreonUtils::conditionBuilder($query, $SeverityIdCondition);
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $query .= $aclObj->queryBuilder('AND', 'h.host_id', $aclObj->getHostsString('ID', $dbb));
}
$orderBy = 'h.name ASC';
if (isset($preferences['order_by']) && $preferences['order_by'] != '') {
    $orderBy = $preferences['order_by'];
}
$query .= " ORDER BY {$orderBy}";

$res = $dbb->prepare($query);

foreach ($mainQueryParameters as $parameter) {
    $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
}

unset($parameter, $mainQueryParameters);

$res->execute();
$nbRows = $res->rowCount();
$data = [];
$outputLength = $preferences['output_length'] ?? 50;
$commentLength = $preferences['comment_length'] ?? 50;
$hostObj = new CentreonHost($db);
$gmt = new CentreonGMT($db);
$gmt->getMyGMTFromSession(session_id(), $db);

while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        if ($key == 'last_check') {
            $value = $gmt->getDate('Y-m-d H:i:s', $value);
        } elseif ($key == 'last_state_change' || $key == 'last_hard_state_change') {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == 'check_attempt') {
            $value = $value . '/' . $row['max_check_attempts'] . ' (' . $aStateType[$row['state_type']] . ')';
        } elseif ($key == 'state') {
            $value = $stateLabels[$value];
        } elseif ($key == 'output') {
            $value = substr($value, 0, $outputLength);
        } elseif (($key == 'action_url' || $key == 'notes_url') && $value) {
            if (!preg_match("/(^http[s]?)|(^\/\/)/", $value)) {
                $value = '//' . $value;
            }

            $value = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['name'], $value));
        } elseif ($key == 'criticality' && $value != '') {
            $critData = $criticality->getData($row['criticality_id']);
            $value = $critData['hc_name'];
        }
        $data[$row['host_id']][$key] = $value;
    }

    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        $res2 = $dbb->prepare(
            'SELECT data FROM comments where host_id = :hostId
            AND service_id IS NULL ORDER BY entry_time DESC LIMIT 1'
        );
        $res2->bindValue(':hostId', $row['host_id'], \PDO::PARAM_INT);
        $res2->execute();
        if ($row2 = $res2->fetch()) {
            $data[$row['host_id']]['comment'] = substr($row2['data'], 0, $commentLength);
        } else {
            $data[$row['host_id']]['comment'] = '-';
        }
    }
}

$lines = [];
foreach ($data as $lineData) {
    $lines[0] = [];
    $line = [];

    // severity column
    if ($preferences['display_severities']) {
        $lines[0][] = 'Severity';
        $line[] = $lineData['criticality'];
    }

    // name column
    if ($preferences['display_host_name'] && $preferences['display_host_alias']) {
        $lines[0][] = 'Host Name - Host Alias';
        $line[] = $lineData['name'] . ' - ' . $lineData['alias'];
    } elseif ($preferences['display_host_alias']) {
        $lines[0][] = 'Host Alias';
        $line[] = $lineData['alias'];
    } else {
        $lines[0][] = 'Host Name';
        $line[] = $lineData['name'];
    }

    // ip address column
    if ($preferences['display_ip']) {
        $lines[0][] = 'Address';
        $line[] = $lineData['address'];
    }

    // status column
    if ($preferences['display_status']) {
        $lines[0][] = 'Status';
        $line[] = $lineData['state'];
    }

    // duration column
    if ($preferences['display_duration']) {
        $lines[0][] = 'Duration';
        $line[] = $lineData['last_state_change'];
    }

    // hard state duration column
    if ($preferences['display_hard_state_duration']) {
        $lines[0][] = 'Hard State Duration';
        $line[] = $lineData['last_hard_state_change'];
    }

    // last check column
    if ($preferences['display_last_check']) {
        $lines[0][] = 'Last Check';
        $line[] = $lineData['last_check'];
    }

    // check attempts column
    if ($preferences['display_tries']) {
        $lines[0][] = 'Attempt';
        $line[] = $lineData['check_attempt'];
    }

    // output column
    if ($preferences['display_output']) {
        $lines[0][] = 'Output';
        $line[] = $lineData['output'];
    }

    // comment column
    if ($preferences['display_last_comment']) {
        $lines[0][] = 'Last comment';
        $line[] = $lineData['comment'];
    }

    $lines[] = $line;
}

// open raw memory as file so no temp files needed, you might run out of memory though
$memoryFile = fopen('php://memory', 'w');
// loop over the input array
foreach ($lines as $line) {
    // generate csv lines from the inner arrays
    fputcsv($memoryFile, $line, ';');
}
// reset the file pointer to the start of the file
fseek($memoryFile, 0);
// tell the browser it's going to be a csv file
header('Content-Type: application/csv');
// tell the browser we want to save it instead of displaying it
header('Content-Disposition: attachment; filename="hosts-monitoring.csv";');
// make php send the generated csv lines to the browser
fpassthru($memoryFile);
