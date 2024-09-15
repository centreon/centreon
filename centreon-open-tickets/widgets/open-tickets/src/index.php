<?php
/*
 * Copyright 2016-2023 Centreon (http://www.centreon.com/)
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

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonInstance.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonHostcategories.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

$smartyDir = __DIR__ . '/../../../../vendor/smarty/smarty/';
require_once $smartyDir . 'libs/Smarty.class.php';

require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

CentreonSession::start(1);
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

// Init Smarty
$template = new Smarty();
$template = initSmartyTplForPopup(
    $centreon_path . "www/widgets/open-tickets/src/templates/",
    $template,
    "./",
    $centreon_path
);

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);
$rule = new Centreon_OpenTickets_Rule($db);

/** @var \Centreon $centreon */
$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

/**
 * @var $dbb CentreonDB
 */
$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

if (!isset($preferences['rule'])) {
    exit;
}

$macro_tickets = $rule->getMacroNames($preferences['rule'], $widgetId);

$aColorHost = [
    0 => 'host_up',
    1 => 'host_down',
    2 => 'host_unreachable',
    4 => 'host_pending'
];

$aColorService = [
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

$aStateType = ["1" => "H", "0" => "S"];
$mainQueryParameters = [];

// Build Query
$query = "SELECT SQL_CALC_FOUND_ROWS h.host_id,
        h.name AS hostname,
        s.latency,
        s.execution_time,
        h.state AS h_state,
        s.service_id,
        s.description,
        s.state AS s_state,
        s.state_type AS state_type,
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
        s.last_time_ok,
        s.check_attempt,
        s.max_check_attempts,
        h.action_url AS h_action_url,
        h.notes_url AS h_notes_url,
        s.action_url AS s_action_url,
        s.notes_url AS s_notes_url,
        h.last_hard_state_change AS host_last_hard_state_change,
        h.last_time_up AS host_last_time_up,
        CAST(mop1.timestamp AS UNSIGNED) AS host_ticket_time,
        mop1.ticket_value AS host_ticket_id,
        mopd1.subject AS host_ticket_subject,
        CAST(mop2.timestamp AS UNSIGNED) AS service_ticket_time,
        mopd2.subject AS service_ticket_subject,
        mop2.ticket_value AS service_ticket_id,
        CONCAT_WS('', mop1.ticket_value, mop2.ticket_value) AS ticket_id,
        cv2.value AS criticality_id,
        cv.value AS criticality_level,
        h.icon_image
    FROM hosts h
    LEFT JOIN customvariables cv5 ON (
        h.host_id = cv5.host_id AND (cv5.service_id IS NULL OR cv5.service_id = 0) AND cv5.name = '" . $macro_tickets['ticket_id'] . "'
    )
    LEFT JOIN mod_open_tickets mop1 ON (
        cv5.value = mop1.ticket_value AND (
            mop1.timestamp > h.last_time_up OR h.last_time_up IS NULL
        )
    )
    LEFT JOIN mod_open_tickets_data mopd1 ON (mop1.ticket_id = mopd1.ticket_id), services s
    LEFT JOIN customvariables cv ON (
        s.service_id = cv.service_id AND s.host_id = cv.host_id AND cv.name = 'CRITICALITY_LEVEL'
    )
    LEFT JOIN customvariables cv2 ON (
        s.service_id = cv2.service_id AND s.host_id = cv2.host_id AND cv2.name = 'CRITICALITY_ID'
    )
    LEFT JOIN customvariables cv3 ON (
        s.service_id = cv3.service_id AND s.host_id = cv3.host_id AND cv3.name = '" . $macro_tickets['ticket_id'] . "'
    )
    LEFT JOIN mod_open_tickets mop2 ON (
        cv3.value = mop2.ticket_value AND (
            mop2.timestamp > s.last_time_ok OR s.last_time_ok IS NULL
        )
    )
    LEFT JOIN mod_open_tickets_data mopd2 ON (mop2.ticket_id = mopd2.ticket_id)";

if (!$centreon->user->admin) {
    $query .= " , centreon_acl acl ";
}
$query .= " WHERE s.host_id = h.host_id
    AND h.enabled = 1 AND h.name NOT LIKE '_Module_%'
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
            "h.name " . CentreonUtils::operandToMysqlFormat($op) . " '" . $dbb->escape($search) . "' "
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
            "s.description " . CentreonUtils::operandToMysqlFormat($op) . " '" . $dbb->escape($search) . "' "
        );
    }
}
$stateTab = [];
if (! empty($preferences['svc_warning'])) {
    $stateTab[] = 1;
}
if (! empty($preferences['svc_critical'])) {
    $stateTab[] = 2;
}
if (! empty($preferences['svc_unknown'])) {
    $stateTab[] = 3;
}

if ($stateTab !== []) {
    $query = CentreonUtils::conditionBuilder($query, " s.state IN (" . implode(',', $stateTab) . ")");
}

if (! empty($preferences['duration_filter'])) {
    $tab = explode(" ", $preferences['duration_filter']);
    if (
        count($tab) >= 2
        && ! empty($tab[0])
        && is_numeric($tab[1])
    ) {
        $op = $tab[0];
        if ($op === 'gt') {
            $op = 'lt';
        } elseif ($op === 'lt') {
            $op = 'gt';
        } elseif ($op === 'gte') {
            $op = 'lte';
        } elseif ($op === 'lte') {
            $op = 'gte';
        }
        $op = CentreonUtils::operandToMysqlFormat($op);

        $durationValue = time() - $tab[1];
        if (! empty($op)) {
            $query = CentreonUtils::conditionBuilder(
                $query,
                "s.last_state_change " . $op . " " . $durationValue
            );
        }
    }
}
if (! empty($preferences['hide_down_host'])) {
    $query = CentreonUtils::conditionBuilder($query, " h.state != 1 ");
}
if (! empty($preferences['hide_unreachable_host'])) {
    $query = CentreonUtils::conditionBuilder($query, " h.state != 2 ");
}
if (! empty($preferences['hide_disable_notif_host'])) {
    $query = CentreonUtils::conditionBuilder($query, " h.notify != 0 ");
}
if (! empty($preferences['hide_disable_notif_service'])) {
    $query = CentreonUtils::conditionBuilder($query, " s.notify != 0 ");
}

# For Open Tickets
if (!isset($preferences['opened_tickets']) || $preferences['opened_tickets'] == 0) {
    $query .= " AND mop1.timestamp IS NULL ";
    $query .= " AND mop2.timestamp IS NULL ";
} else {
    $query .= " AND (mop1.timestamp IS NOT NULL ";
    $query .= "       OR mop2.timestamp IS NOT NULL) ";
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
        if ($queryPoller !== '') {
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

if (isset($preferences["display_severities"]) &&
    $preferences["display_severities"] &&
    isset($preferences['criticality_filter']) &&
    $preferences['criticality_filter'] != ""
) {
    $tab = explode(",", $preferences['criticality_filter']);
    $labels = "";
    foreach ($tab as $p) {
        if ($labels != '') {
            $labels .= ',';
        }
        $labels .= "'" . trim($p) . "'";
    }
    $query2 = "SELECT sc_id FROM service_categories WHERE sc_name IN (" . $labels . ")";
    $RES = $db->query($query2);
    $idC = "";
    while ($d1 = $RES->fetch()) {
        if ($idC != '') {
            $idC .= ",";
        }
        $idC .= $d1['sc_id'];
    }
    $query .= " AND cv2.`value` IN ($idC) ";
}

if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id AND acl.service_id = s.service_id AND acl.group_id IN ($groupList)";
}
if (isset($preferences['output_search']) && $preferences['output_search'] != "") {
    $tab = explode(" ", $preferences['output_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder(
            $query,
            "s.output " . CentreonUtils::operandToMysqlFormat($op) . " '" . $dbb->escape($search) . "' "
        );
    }
}
if (isset($preferences['ticket_id_search']) && $preferences['ticket_id_search'] != "") {
    $query .= " AND (mop1.ticket_value LIKE '" . $dbb->escape($preferences['ticket_id_search']) .
        "' OR mop2.ticket_value LIKE '" . $dbb->escape($preferences['ticket_id_search']) . "') ";
}
if (isset($preferences['ticket_subject_search']) && $preferences['ticket_subject_search'] != "") {
    $query .= " AND (mopd1.subject LIKE '" . $dbb->escape($preferences['ticket_subject_search']) .
        "' OR mopd2.subject LIKE '" . $dbb->escape($preferences['ticket_subject_search']) . "') ";
}

$orderBy = "hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $aOrder = explode(" ", $preferences['order_by']);
    if (in_array('last_state_change', $aOrder) || in_array('last_hard_state_change', $aOrder)) {
        $order = $aOrder[1] == 'DESC' ? 'ASC' : 'DESC';
        $orderBy = $aOrder[0] . " " . $order;
    } else {
        $orderBy = $preferences['order_by'];
    }

    if (isset($preferences['order_by2']) && $preferences['order_by2'] != "") {
        $aOrder = explode(" ", $preferences['order_by2']);
        $orderBy .= ", " . $aOrder[0] . " " . $aOrder[1];
    }
}

$query .= "ORDER BY " . $orderBy;
$query .= " LIMIT " . ($page * $preferences['entries']) . "," . $preferences['entries'];


$res = $dbb->prepare($query);
foreach ($mainQueryParameters as $parameter) {
    $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
}

unset($parameter, $mainQueryParameters);
$res->execute();

$nbRows = $dbb->query("SELECT FOUND_ROWS()")->fetchColumn();
$data = [];
$outputLength = $preferences['output_length'] ?: 50;

$hostObj = new CentreonHost($db);
$svcObj = new CentreonService($db);
$gmt = new CentreonGMT($db);
$gmt->getMyGMTFromSession(session_id(), $db);
while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        if ($key == "last_check") {
            $value = $gmt->getDate("Y-m-d H:i:s", $value);
        } elseif ($key == "last_state_change" || $key == "last_hard_state_change") {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == "check_attempt") {
            $value = $value . "/" . $row['max_check_attempts'] . ' (' . $aStateType[$row['state_type']] . ')';
        } elseif ($key == "s_state") {
            $data[$row['host_id'] . "_" . $row['service_id']]['color'] = $aColorService[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "h_state") {
            $data[$row['host_id'] . "_" . $row['service_id']]['hcolor'] = $aColorHost[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "output") {
            $value = substr($value, 0, $outputLength);
        } elseif (($key == "h_action_url" || $key == "h_notes_url") && $value) {
            $value = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['hostname'], $value));
            if (preg_match("/^.\/include\/configuration\/configKnowledge\/proxy\/proxy.php(.*)/i", $value)) {
                $value = "../../" . $value;
            }
        } elseif (($key == "s_action_url" || $key == "s_notes_url") && $value) {
            $value = $hostObj->replaceMacroInString($row['hostname'], $value);
            $value = CentreonUtils::escapeSecure($svcObj->replaceMacroInString($row['service_id'], $value));
            if (preg_match("/^.\/include\/configuration\/configKnowledge\/proxy\/proxy.php(.*)/i", $value)) {
                $value = "../../" . $value;
            }
        } elseif ($key == "criticality_id" && $value != '') {
            $critData = $criticality->getData($row["criticality_id"], 1);
            $value = "<img src='../../img/media/" . $media->getFilename($critData['icon_id']) .
            "' title='" . $critData["sc_name"] . "' width='16' height='16'>";
        }
        $data[$row['host_id'] . "_" . $row['service_id']][$key] = $value;
    }

    $data[$row['host_id'] . '_' . $row['service_id']]['encoded_description'] = urlencode(
        $data[$row['host_id'] . '_' . $row['service_id']]['description']
    );

    $data[$row['host_id'] . '_' . $row['service_id']]['encoded_hostname'] = urlencode(
        $data[$row['host_id'] . '_' . $row['service_id']]['hostname']
    );

    if ($row['host_ticket_time'] > $row['host_last_time_up'] &&
        isset($row['host_ticket_id']) && !is_null($row['host_ticket_id']) && $row['host_ticket_id'] != ''
    ) {
        $ticket_id = $row['host_ticket_id'];
        $url = $rule->getUrl($preferences['rule'], $ticket_id, $row, $widgetId);
        if (!is_null($url) && $url != '') {
            $ticket_id = '<a href="' . $url . '" target="_blank">' . $ticket_id . '</a>';
        }
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_id'] = $ticket_id;
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_time'] = $gmt->getDate(
            "Y-m-d H:i:s",
            $row['host_ticket_time']
        );
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_subject'] = $row['host_ticket_subject'];
    } elseif ($row['service_ticket_time'] > $row['last_time_ok'] &&
        isset($row['service_ticket_id']) &&
        !is_null($row['service_ticket_id']) &&
        $row['service_ticket_id'] != ''
    ) {
        $ticket_id = $row['service_ticket_id'];
        $url = $rule->getUrl($preferences['rule'], $ticket_id, $row, $widgetId);
        if (!is_null($url) && $url != '') {
            $ticket_id = '<a href="' . $url . '" target="_blank">' . $ticket_id . '</a>';
        }
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_id'] = $ticket_id;
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_time'] = $gmt->getDate(
            "Y-m-d H:i:s",
            $row['service_ticket_time']
        );
        $data[$row['host_id'] . "_" . $row['service_id']]['ticket_subject'] = $row['service_ticket_subject'];
    }

    $kernel = \App\Kernel::createForWeb();
    $resourceController = $kernel->getContainer()->get(
        \Centreon\Application\Controller\MonitoringResourceController::class
    );

    $data[$row['host_id'] . '_' . $row['service_id']]['h_details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=0202&o=hd&host_name=' . $row['hostname']
        : $resourceController->buildHostDetailsUri($row['host_id']);

    $data[$row['host_id'] . '_' . $row['service_id']]['s_details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=0202&o=hd&host_name=' . $row['hostname']
            . '&service_description=' . $row['description']
        : $resourceController->buildServiceDetailsUri($row['host_id'], $row['service_id']);
}

$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $preferences['refresh_interval']);
$template->assign('preferences', $preferences);
$template->assign('page', $page);
$template->assign('dataJS', count($data));
$template->assign('nbRows', $nbRows);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->display('table.ihtml');
