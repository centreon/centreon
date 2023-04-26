<?php
/*
 * Copyright 2015-2019 Centreon (http://www.centreon.com/)
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
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';

require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

$smartyDir = __DIR__ . '/../../../../vendor/smarty/smarty/';
require_once $smartyDir . 'libs/Smarty.class.php';

session_start();
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

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = new CentreonDB("centstorage");
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

// Set Colors Table

$stateSColors = array(
    0 => "#13EB3A",
    1 => "#F8C706",
    2 => "#F91D05",
    3 => "#DCDADA",
    4 => "#2AD1D4"
);
$stateHColors = array(
    0 => "#13EB3A",
    1 => "#F91D05",
    2 => "#DCDADA",
    3 => "#2AD1D4"
);


$stateLabels = array(
    0 => "Ok",
    1 => "Warning",
    2 => "Critical",
    3 => "Unknown",
    4 => "Pending"
);
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

if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, " s.state IN (" . implode(',', $stateTab) . ")");
}

if (isset($preferences['hide_disable_notif_host']) && $preferences['hide_disable_notif_host']) {
    $query = CentreonUtils::conditionBuilder($query, " h.notify != 0 ");
}
if (isset($preferences['hide_disable_notif_service']) && $preferences['hide_disable_notif_service']) {
    $query = CentreonUtils::conditionBuilder($query, " s.notify != 0 ");
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
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.host_id IN (
        SELECT host_host_id
        FROM `" . $conf_centreon['db'] . "`.hostgroup_relation
        WHERE hostgroup_hg_id = " . $dbb->escape($preferences['hostgroup']) . ")"
    );
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.service_id IN (SELECT service_service_id
        FROM `" . $conf_centreon['db'] . "`.servicegroup_relation
        WHERE servicegroup_sg_id = " . $dbb->escape($preferences['servicegroup']) . "
        UNION
        SELECT sgr.service_service_id
        FROM `" . $conf_centreon['db'] . "`.servicegroup_relation sgr, `" .
        $conf_centreon['db'] . "`.host_service_relation hsr
        WHERE hsr.hostgroup_hg_id = sgr.hostgroup_hg_id
        AND sgr.servicegroup_sg_id = " . $dbb->escape($preferences['servicegroup']) . ") "
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
$outputLength = $preferences['output_length'] ? $preferences['output_length'] : 50;
$commentLength = $preferences['comment_length'] ? $preferences['comment_length'] : 50;

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
