<?php
/**
 * Copyright 2005-2014 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
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

require_once "../../require.php";
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
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit();
}

$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

// Init Smarty
$template = new Smarty();
$template = initSmartyTplForPopup(
    $centreon_path . "www/widgets/service-monitoring/src/",
    $template,
    "./",
    $centreon_path
);

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

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

$mainQueryParameters = [];

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
            'type' => PDO::PARAM_STR
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
            'type' => PDO::PARAM_STR
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
    $mainQueryParameters[] = [
        'parameter' => ':hostgroup',
        'value' => $preferences['hostgroup_id'],
        'type' => PDO::PARAM_INT
    ];
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.host_id IN (
            SELECT host_host_id
            FROM " . $conf_centreon['db'] .".hostgroup_relation
            WHERE hostgroup_hg_id = :hostgroup_id
        )"
    );
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {
    $mainQueryParameters[] = [
        'parameter' => ':servicegroup_id',
        'value' => $preferences['servicegroup'],
        'type' => PDO::PARAM_INT
    ];
    $query = CentreonUtils::conditionBuilder(
        $query,
        " s.service_id IN (
            SELECT service_service_id
            FROM " . $conf_centreon['db'] . ".servicegroup_relation
            WHERE servicegroup_sg_id = :servicegroup_id
            UNION
            SELECT sgr.service_service_id
            FROM " . $conf_centreon['db'] . ".servicegroup_relation sgr, " .
                $conf_centreon['db'] . ".host_service_relation hsr
            WHERE hsr.hostgroup_hg_id = sgr.hostgroup_hg_id
            AND sgr.servicegroup_sg_id = :servicegroup_id
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
    $query2 = "SELECT sc_id FROM service_categories WHERE sc_name IN (" . $labels . ")";
    $res2 = $db->query($query2);
    $idC = "";
    while ($d1 = $res2->fetch()) {
        if ($idC != '') {
            $idC .= ",";
        }
        $idC .= $d1['sc_id'];
    }
    $query .= " AND cv2.`value` IN ($idC) ";
}
unset($query2, $res2);
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id
	AND acl.service_id = s.service_id
	AND acl.group_id IN (" . $groupList . ")";
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
            'type' => PDO::PARAM_STR
        ];
        $serviceOutputCondition = ' s.output ' . CentreonUtils::operandToMysqlFormat($op) . ' :service_output ';
        $query = CentreonUtils::conditionBuilder($query, $servicegroupCondition);
    }
}
$orderby = "hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}

$query .= "ORDER BY " . $orderby;

$res = $dbb->prepare($query);

foreach ($mainQueryParameters as $parameter) {
    $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
}

unset($parameter, $mainQueryParameters);

$res->execute();

$nbRows = $res->rowCount();
$data = array();
$outputLength = $preferences['output_length'] ? $preferences['output_length'] : 50;
$commentLength = $preferences['comment_length'] ? $preferences['comment_length'] : 50;

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
        $res2->bindValue(':host_id', $row['host_id'], PDO::PARAM_INT);
        $res2->bindValue(':service_id', $row['service_id'], PDO::PARAM_INT);
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

$autoRefresh = $preferences['refresh_interval'];
$template->assign('widgetId', $widgetId);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->display('export.ihtml');
