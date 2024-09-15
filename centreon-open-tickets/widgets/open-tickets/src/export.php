<?php
/*
 * Copyright 2015-2023 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

header('Content-type: application/csv');
header('Content-Disposition: attachment; filename="open-tickets.csv"');

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonHostcategories.class.php';
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

$smartyDir = __DIR__ . '/../../../../vendor/smarty/smarty/';
require_once $smartyDir . 'libs/Smarty.class.php';

CentreonSession::start(1);
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

// Init Smarty
$template = new Smarty();
$template = initSmartyTplForPopup($centreon_path . "www/widgets/open-tickets/src/", $template, "./", $centreon_path);

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

/** @var \Centreon $centreon */
$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);	
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
	
/**	
 * @var $dbb CentreonDB	
 */	
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

// Set Colors Table	
$stateHColors = [	
    0 => 'host_up',	
    1 => 'host_down',	
    2 => 'host_unreachable',	
    4 => 'host_pending'	
];	
$stateSColors = [	
    0 => 'service_ok',	
    1 => 'service_warning',	
    2 => 'service_critical',	
    3 => 'service_unknown',	
    4 => 'pending'	
];	
$stateLabels = [	
    0 => 'Ok',	
    1 => 'Warning',	
    2 => 'Critical',	
    3 => 'Unknown',	
    4 => 'Pending'	
];	
$aStateType = ['1' => 'H', '0' => 'S'];	
$mainQueryParameters = [];


// Build Query
$query = "SELECT SQL_CALC_FOUND_ROWS h.host_id,
        h.name AS hostname,
        h.state AS h_state,
        s.service_id,
        s.description,
        s.state AS s_state,
        s.last_hard_state,
        s.output,
        s.scheduled_downtime_depth AS s_scheduled_downtime_depth,
        s.acknowledged AS s_acknowledged,
        s.notify AS s_notify,
        s.active_checks AS s_active_checks,
        s.passive_checks AS s_passive_checks,
        h.scheduled_downtime_depth AS h_scheduled_downtime_depth,
        h.acknowledged AS h_acknowledged,
        h.notify AS h_notify,
        h.active_checks AS h_active_checks,
        h.passive_checks AS h_passive_checks,
        s.last_check,
        s.last_state_change,
        s.last_hard_state_change,
        s.check_attempt,
        s.max_check_attempts,
        h.action_url AS h_action_url,
        h.notes_url AS h_notes_url,
        s.action_url AS s_action_url,
        s.notes_url AS s_notes_url,
        cv2.value AS criticality_id,
        cv.value AS criticality_level
    FROM hosts h, services s
    LEFT JOIN customvariables cv ON (
        s.service_id = cv.service_id AND s.host_id = cv.host_id AND cv.name = 'CRITICALITY_LEVEL'
    )
    LEFT JOIN customvariables cv2 ON (
        s.service_id = cv2.service_id AND s.host_id = cv2.host_id AND cv2.name = 'CRITICALITY_ID'
    )";
if (!$centreon->user->admin) {
    $query .= " , centreon_acl acl ";
}
$query .= " WHERE s.host_id = h.host_id
    AND h.name NOT LIKE '_Module_%'
    AND s.enabled = 1 ";
if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != "") {
    $tab = explode(" ", $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder(
            $query,
            "h.name ".CentreonUtils::operandToMysqlFormat($op) .
            " '" . $dbb->escape($search) . "' "
        );
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != "") {
    $tab = explode(" ", $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder(
            $query,
            "s.description " . CentreonUtils::operandToMysqlFormat($op) . " '" . $dbb->escape($search)."' "
        );
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

if ($stateTab !== []) {
    $query = CentreonUtils::conditionBuilder($query, " s.state IN (" . implode(',', $stateTab) . ")");
}

if (! empty($preferences['hide_disable_notif_host'])) {
    $query = CentreonUtils::conditionBuilder($query, " h.notify != 0 ");
}
if (! empty($preferences['hide_disable_notif_service'])) {
    $query = CentreonUtils::conditionBuilder($query, " s.notify != 0 ");
}

if (! empty($preferences['acknowledgement_filter'])) {
    if ($preferences['acknowledgement_filter'] == "ack") {
        $query = CentreonUtils::conditionBuilder($query, " s.acknowledged = 1");
    } elseif ($preferences['acknowledgement_filter'] == "nack") {
        $query = CentreonUtils::conditionBuilder(
            $query,
            " s.acknowledged = 0 AND h.acknowledged = 0 AND h.scheduled_downtime_depth = 0 "
        );
    }
}

if (! empty($preferences['downtime_filter'])) {
    if ($preferences['downtime_filter'] == "downtime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth > 0 ");
    } elseif ($preferences['downtime_filter'] == "ndowntime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth = 0 ");
    }
}

if (! empty($preferences['state_type_filter'])) {
    if ($preferences['state_type_filter'] == "hardonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 1 ");
    } elseif ($preferences['state_type_filter'] == "softonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 0 ");
    }
}

if (! empty($preferences['poller'])) {
    $resultsPoller = explode(',', $preferences['poller']);
    $queryPoller = '';
    foreach ($resultsPoller as $resultPoller) {
        if ($queryPoller != '') {
            $queryPoller .= ', ';
        }
        $queryPoller .= ':instance_id_' . $resultPoller;
        $mainQueryParameters[] = [
            'parameter' => ':instance_id_' . $resultPoller,
            'value' => (int)$resultPoller,
            'type' => PDO::PARAM_INT
        ];
    }
    $instanceIdCondition = ' h.instance_id IN (' . $queryPoller . ')';
    $query = CentreonUtils::conditionBuilder($query, $instanceIdCondition);
}
if (! empty($preferences['hostgroup'])) {
    $results = explode(',', $preferences['hostgroup']);
    $queryHG = '';
    foreach ($results as $result) {
        if ($queryHG !== '') {
            $queryHG .= ', ';
        }
        $queryHG .= ":id_" . $result;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $result,
            'value' => (int)$result,
            'type' => PDO::PARAM_INT
        ];
    }
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.host_id IN (
            SELECT host_host_id
            FROM `" . $conf_centreon['db'] . "`.hostgroup_relation
            WHERE hostgroup_hg_id IN (" . $queryHG . ")
        )"
    );
}
if (! empty($preferences['servicegroup'])) {
    $resultsSG = explode(',', $preferences['servicegroup']);
    $querySG = '';
    foreach ($resultsSG as $resultSG) {
        if ($querySG !== '') {
            $querySG .= ', ';
        }
        $querySG .= ":id_" . $resultSG;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $resultSG,
            'value' => (int)$resultSG,
            'type' => PDO::PARAM_INT
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
if (! empty($preferences['hostcategories'])) {
    $resultsHC = explode(',', $preferences['hostcategories']);
    $queryHC = '';
    foreach ($resultsHC as $resultHC) {
        if ($queryHC !== '') {
            $queryHC .= ', ';
        }
        $queryHC .= ":id_" . $resultHC;
        $mainQueryParameters[] = [
            'parameter' => ':id_' . $resultHC,
            'value' => (int)$resultsHC,
            'type' => PDO::PARAM_INT
        ];
    }
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.host_id IN (
            SELECT host_host_id
            FROM `" . $conf_centreon['db'] . "`.hostcategories_relation
            WHERE hostcategories_hc_id IN (" . $queryHC . ")
        )"
    );
}
if (isset($preferences["display_severities"])
    && $preferences["display_severities"]
    && isset($preferences['criticality_filter'])
    && $preferences['criticality_filter'] != ""
) {
    $tab = explode(",", $preferences['criticality_filter']);
    $labels = "";
    foreach ($tab as $p) {
        if ($labels != '') {
            $labels .= ',';
        }
        $labels .= "'" . trim($p) . "'";
    }
    $res = $db->query("SELECT sc_id FROM service_categories WHERE sc_name IN (" . $labels . ")");
    $idC = "";
    while ($d1 = $res->fetch()) {
        if ($idC != '') {
            $idC .= ",";
        }
        $idC .= $d1['sc_id'];
    }
    $query .= " AND cv2.`value` IN (" . $idC . ") ";
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id
        AND acl.service_id = s.service_id
        AND acl.group_id IN (" . $groupList . ")";
}
$orderby = "hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}
$query .= "ORDER BY $orderby";
$res = $dbb->query($query);
$nbRows = $dbb->numberRows();
$data = array();
$outputLength = $preferences['output_length'] ?: 50;
$commentLength = $preferences['comment_length'] ?: 50;

$hostObj = new CentreonHost($db);
$svcObj = new CentreonService($db);
while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        if ($key == "last_check") {
            $value = date("Y-m-d H:i:s", $value);
        } elseif ($key == "last_state_change" || $key == "last_hard_state_change") {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == "check_attempt") {
            $value = $value . "/" . $row['max_check_attempts'];
        } elseif ($key == "s_state") {
            $data[$row['host_id']."_".$row['service_id']]['color'] = $stateSColors[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "h_state") {
            $data[$row['host_id']."_".$row['service_id']]['hcolor'] = $stateHColors[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "output") {
            $value = substr($value, 0, $outputLength);
        } elseif (($key == "h_action_url" || $key == "h_notes_url") && $value) {
            $value = $hostObj->replaceMacroInString($row['hostname'], $value);
        } elseif (($key == "s_action_url" || $key == "s_notes_url") && $value) {
            $value = $hostObj->replaceMacroInString($row['hostname'], $value);
            $value = $svcObj->replaceMacroInString($service_id, $value);
        } elseif ($key == "criticality_id" && $value != '') {
            $critData = $criticality->getData($row["criticality_id"], 1);
            $value = $critData["hc_name"];
        }
        $data[$row['host_id']."_".$row['service_id']][$key] = $value;
    }

    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        $res2 = $dbb->query(
            'SELECT data
            FROM comments
            WHERE host_id = ' . $row['host_id'] . '
            AND service_id = ' . $row['service_id'] . '
            ORDER BY entry_time DESC LIMIT 1'
        );
        if ($row2 = $res2->fetch()) {
            $data[$row['host_id']."_".$row['service_id']]['comment'] = substr($row2['data'], 0, $commentLength);
        } else {
            $data[$row['host_id']."_".$row['service_id']]['comment'] = '-';
        }
    }
}

$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->display('export.ihtml');
