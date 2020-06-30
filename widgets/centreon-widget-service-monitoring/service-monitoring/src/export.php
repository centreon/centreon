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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="services-monitoring.csv"');

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

session_start();
if (!isset($_SESSION['centreon'], $_GET['widgetId'], $_GET['list'])) {
    // As the header is already defined, if one of these parameters is missing, an empty CSV is exported
    exit();
}

$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

/**
 * Sanitize and concatenate selected resources for the query
 */
// Check returned combinations
if (false !== strpos($_GET['list'], ',')) {
    $resources = explode(',', $_GET['list']);
} else {
    $resources[] = $_GET['list'];
}
// Check combinations consistency and split them in an [hostId, serviceId] array
$exportList = [];
foreach ($resources as $resource) {
    if (false !== strpos($resource, '\;')) {
        continue;
    } else {
        $exportList[] = explode(';', $resource);
    }
}
$mainQueryParameters = [];
$hostQuery = '';
$serviceQuery = '';
// Prepare the query concatenation and the bind values
$firstResult = true;
foreach ($exportList as $key => $Id) {
    if (
        !isset($exportList[$key][1]) ||
        0 === (int) $exportList[$key][0] ||
        0 === (int) $exportList[$key][1]
    ) {
        // skip missing serviceId in combinations or non consistent data
        continue;
    }
    if (false === $firstResult) {
        $hostQuery .= ', ';
        $serviceQuery .= ', ';
    }
    $hostQuery .= ':' . $key . 'hId' . $exportList[$key][0];
    $mainQueryParameters[] = [
        'parameter' => ':' . $key . 'hId' . $exportList[$key][0],
        'value' => (int) $exportList[$key][0],
        'type' => \PDO::PARAM_INT
    ];
    $serviceQuery .= ':' . $key . 'sId' . $exportList[$key][1];
    $mainQueryParameters[] = [
        'parameter' => ':' . $key . 'sId' . $exportList[$key][1],
        'value' => (int) $exportList[$key][1],
        'type' => \PDO::PARAM_INT
    ];
    $firstResult = false;
}

$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$aStateType = array("1" => "H", "0" => "S");
$stateLabels = array(
    0 => "Ok",
    1 => "Warning",
    2 => "Critical",
    3 => "Unknown",
    4 => "Pending"
);

// Build Query
$query = "SELECT SQL_CALC_FOUND_ROWS h.host_id,
    h.name as hostname,
    h.alias as hostalias,
    s.latency,
    s.execution_time,
    h.state as h_state,
    s.service_id,
    s.description,
    s.state as s_state,
    h.state_type as state_type,
    s.last_hard_state,
    s.output,
    s.scheduled_downtime_depth as s_scheduled_downtime_depth,
    s.acknowledged as s_acknowledged,
    s.notify as s_notify,
    s.active_checks as s_active_checks,
    s.passive_checks as s_passive_checks,
    h.scheduled_downtime_depth as h_scheduled_downtime_depth,
    h.acknowledged as h_acknowledged,
    h.notify as h_notify,
    h.active_checks as h_active_checks,
    h.passive_checks as h_passive_checks,
    s.last_check,
    s.last_state_change,
    s.last_hard_state_change,
    s.check_attempt,
    s.max_check_attempts,
    h.action_url as h_action_url,
    h.notes_url as h_notes_url,
    s.action_url as s_action_url,
    s.notes_url as s_notes_url,
    cv2.value AS criticality_id,
    cv.value AS criticality_level
    FROM hosts h, services s
    LEFT JOIN customvariables cv ON (
        s.service_id = cv.service_id AND s.host_id = cv.host_id AND cv.name = 'CRITICALITY_LEVEL'
    )
    LEFT JOIN customvariables cv2 ON (
        s.service_id = cv2.service_id AND s.host_id = cv2.host_id AND cv2.name = 'CRITICALITY_ID'
    ) ";
if (!$centreon->user->admin) {
    $query .= " , centreon_acl acl ";
}
$query .= " WHERE s.host_id = h.host_id
    AND h.name NOT LIKE '_Module_%'
    AND s.enabled = 1
    AND h.enabled = 1 ";

if (false === $firstResult) {
    $query .= " AND h.host_id IN ($hostQuery) AND s.service_id IN ($serviceQuery) ";
}

if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != "") {
    $tab = explode(" ", $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $mainQueryParameters[] = [
            'parameter' => ':host_name',
            'value' => $search,
            'type' => \PDO::PARAM_STR
        ];
        $hostNameCondition = 'h.name ' . CentreonUtils::operandToMysqlFormat($op) . ' :host_name ';
        $query = CentreonUtils::conditionBuilder($query, $hostNameCondition);
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != "") {
    $tab = explode(" ", $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $mainQueryParameters[] = [
            'parameter' => ':service_description',
            'value' => $search,
            'type' => \PDO::PARAM_STR
        ];
        $serviceDescriptionCondition = 's.description ' .
            CentreonUtils::operandToMysqlFormat($op) . ' :service_description ';
        $query = CentreonUtils::conditionBuilder($query, $serviceDescriptionCondition);
    }
}
$stateTab = array();
if (isset($preferences['svc_ok']) && $preferences['svc_ok']) {
    $stateTab[] = 0;
}
if (isset($preferences['svc_warning']) && $preferences['svc_warning']) {
    $stateTab[] = 1;
}
if (isset($preferences['svc_critical']) && $preferences['svc_critical']) {
    $stateTab[] = 2;
}
if (isset($preferences['svc_unknown']) && $preferences['svc_unknown']) {
    $stateTab[] = 3;
}
if (isset($preferences['svc_pending']) && $preferences['svc_pending']) {
    $stateTab[] = 4;
}
if (isset($preferences['hide_down_host']) && $preferences['hide_down_host']) {
    $query = CentreonUtils::conditionBuilder($query, " h.state != 1 ");
}
if (isset($preferences['hide_unreachable_host']) && $preferences['hide_unreachable_host']) {
    $query = CentreonUtils::conditionBuilder($query, " h.state != 2 ");
}
if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, " s.state IN (" . implode(',', $stateTab) . ")");
}
if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == "ack") {
        $query = CentreonUtils::conditionBuilder($query, " s.acknowledged = 1");
    } elseif ($preferences['acknowledgement_filter'] == "nack") {
        $query = CentreonUtils::conditionBuilder(
            $query,
            " s.acknowledged = 0 AND h.acknowledged = 0 AND h.scheduled_downtime_depth = 0 "
        );
    }
}
if (isset($preferences['notification_filter']) && $preferences['notification_filter']) {
    if ($preferences['notification_filter'] == "enabled") {
        $query = CentreonUtils::conditionBuilder($query, " s.notify = 1");
    } elseif ($preferences['notification_filter'] == "disabled") {
        $query = CentreonUtils::conditionBuilder($query, " s.notify = 0");
    }
}
if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == "downtime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth > 0 ");
    } elseif ($preferences['downtime_filter'] == "ndowntime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth = 0 ");
    }
}
if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == "hardonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 1 ");
    } elseif ($preferences['state_type_filter'] == "softonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 0 ");
    }
}
if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $results = explode(',', $preferences['hostgroup']);
    $queryHG = '';
    foreach ($results as $result) {
        if ($queryHG != '') {
            $queryHG .= ', ';
        }
        $queryHG .= ":id_" . $result;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $result,
            'value' => (int)$result,
            'type' => \PDO::PARAM_INT
        ];
    }
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.host_id IN (
            SELECT host_host_id
            FROM " . $conf_centreon['db'] . ".hostgroup_relation
            WHERE hostgroup_hg_id IN (" . $queryHG . ")
        )"
    );
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {
    $resultsSG = explode(',', $preferences['servicegroup']);
    $querySG = '';
    foreach ($resultsSG as $resultSG) {
        if ($querySG != '') {
            $querySG .= ', ';
        }
        $querySG .= ":id_" . $resultSG;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $resultSG,
            'value' => (int)$resultSG,
            'type' => \PDO::PARAM_INT
        ];
    }
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.service_id IN (
            SELECT DISTINCT service_id
            FROM services_servicegroups
            WHERE servicegroup_id IN (" . $querySG . ")
        )"
    );
}
if  (!empty($preferences['criticality_filter'])) {
    $tab = explode(',', $preferences['criticality_filter']);
    $labels = [];
    foreach ($tab as $p) {
        $labels[] = ":id_". $p;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $p,
            'value' => (int) $p,
            'type' => \PDO::PARAM_INT
        ];
    }
    $query = CentreonUtils::conditionBuilder(
        $query,
        'cv2.value IN (' . implode(',', $labels) . ')'
    );
}
if (isset($preferences['output_search']) && $preferences['output_search'] != "") {
    $tab = explode(" ", $preferences['output_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $mainQueryParameters[] = [
            'parameter' => ':service_output',
            'value' => $search,
            'type' => \PDO::PARAM_STR
        ];
        $serviceOutputCondition = ' s.output ' . CentreonUtils::operandToMysqlFormat($op) . ' :service_output ';
        $query = CentreonUtils::conditionBuilder($query, $serviceOutputCondition);
    }
}
if (!$centreon->user->admin) {
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id
        AND acl.service_id = s.service_id
        AND acl.group_id IN (" . $groupList . ")";
}
$orderby = " hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}

$query .= " ORDER BY " . $orderby;

$res = $dbb->prepare($query);

foreach ($mainQueryParameters as $parameter) {
    $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
}

unset($parameter, $mainQueryParameters);

$res->execute();

$nbRows = $res->rowCount();
$data = array();
$outputLength = $preferences['output_length'] ?? 50;
$commentLength = $preferences['comment_length'] ?? 50;

$hostObj = new CentreonHost($db);
$svcObj = new CentreonService($db);
while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        if ($key == "last_check") {
            $gmt = new CentreonGMT($db);
            $gmt->getMyGMTFromSession(session_id(), $db);
            $value = $gmt->getDate("Y-m-d H:i:s", $value);
        } elseif ($key == "last_state_change" || $key == "last_hard_state_change") {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == "s_state") {
            $value = $stateLabels[$value];
        } elseif ($key == "check_attempt") {
            $value = $value . "/" . $row['max_check_attempts'] . ' (' . $aStateType[$row['state_type']] . ')';
        } elseif (($key == "h_action_url" || $key == "h_notes_url") && $value) {
            $value = urlencode($hostObj->replaceMacroInString($row['hostname'], $value));
        } elseif (($key == "s_action_url" || $key == "s_notes_url") && $value) {
            $value = $hostObj->replaceMacroInString($row['hostname'], $value);
            $value = urlencode($svcObj->replaceMacroInString($row['service_id'], $value));
        } elseif ($key == "criticality_id" && $value != '') {
            $critData = $criticality->getData($row["criticality_id"], 1);
            $value = $critData["sc_name"];
        }
        $data[$row['host_id'] . "_" . $row['service_id']][$key] = $value;
    }
    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        $res2 = $dbb->prepare(
            'SELECT data FROM comments
            WHERE host_id = :host_id
            AND service_id = :service_id
            ORDER BY entry_time DESC LIMIT 1'
        );
        $res2->bindValue(':host_id', $row['host_id'], \PDO::PARAM_INT);
        $res2->bindValue(':service_id', $row['service_id'], \PDO::PARAM_INT);
        $res2->execute();

        $data[$row['host_id'] . "_" . $row['service_id']]['comment'] = '-';

        while ($row2 = $res2->fetch()) {
            $data[$row['host_id'] . "_" . $row['service_id']]['comment'] = substr($row2['data'], 0, $commentLength);
        }
    }
    $data[$row['host_id'] . '_' . $row['service_id']]['encoded_description'] = urlencode(
        $data[$row['host_id'] . '_' . $row['service_id']]['description']
    );
    $data[$row['host_id'] . '_' . $row['service_id']]['encoded_hostname'] = urlencode(
        $data[$row['host_id'] . '_' . $row['service_id']]['hostname']
    );
}

$autoRefresh = (isset($preferences['refresh_interval']) && (int)$preferences['refresh_interval'] > 0)
    ? (int)$preferences['refresh_interval']
    : 30;

$lines = [];
foreach ($data as $lineData) {
    $lines[0] = [];
    $line = [];

    // Export if set in preferences : severities
    if ($preferences['display_severities']) {
        $lines[0][] = 'Severity';
        $line[] = $lineData['criticality_id'];
    }
    // Export if set in preferences : name column
    if ($preferences['display_host_name'] && $preferences['display_host_alias']) {
        $lines[0][] = 'Host Name - Host Alias';
        $line[] = $lineData['hostname'] . ' - ' . $lineData['hostalias'];
    } elseif ($preferences['display_host_alias']) {
        $lines[0][] = 'Host Alias';
        $line[] = $lineData['hostalias'];
    } else {
        $lines[0][] = 'Host Name';
        $line[] = $lineData['hostname'];
    }
    // Export if set in preferences : service description
    if ($preferences['display_svc_description']) {
        $lines[0][] = 'Description';
        $line[] = $lineData['description'];
    }
    // Export if set in preferences : output
    if ($preferences['display_output']) {
        $lines[0][] = 'Output';
        $line[] = $lineData['output'];
    }
    // Export if set in preferences : status
    if ($preferences['display_status']) {
        $lines[0][] = 'Status';
        $line[] = $lineData['s_state'];
    }
    // Export if set in preferences : last check
    if ($preferences['display_last_check']) {
        $lines[0][] = 'Last Check';
        $line[] = $lineData['last_check'];
    }
    // Export if set in preferences : duration
    if ($preferences['display_duration']) {
        $lines[0][] = 'Duration';
        $line[] = $lineData['last_state_change'];
    }
    // Export if set in preferences : hard state duration
    if ($preferences['display_hard_state_duration']) {
        $lines[0][] = 'Hard State Duration';
        $line[] = $lineData['last_hard_state_change'];
    }
    // Export if set in preferences : Tries
    if ($preferences['display_tries']) {
        $lines[0][] = 'Attempt';
        $line[] = $lineData['check_attempt'];
    }
    // Export if set in preferences : Last comment
    if ($preferences['display_last_comment']) {
        $lines[0][] = 'Last comment';
        $line[] = $lineData['comment'];
    }

    // Export if set in preferences : Latency
    if ($preferences['display_latency']) {
        $lines[0][] = 'Latency';
        $line[] = $lineData['latency'];
    }
    // Export if set in preferences : Latency
    if ($preferences['display_execution_time']) {
        $lines[0][] = 'Execution time';
        $line[] = $lineData['execution_time'];
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
header('Content-Disposition: attachment; filename="services-monitoring.csv";');
// make php send the generated csv lines to the browser
fpassthru($memoryFile);
