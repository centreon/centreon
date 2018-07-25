<?php
/*
 * Copyright 2005-2018 CENTREON
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
$template = initSmartyTplForPopup($centreon_path . 'www/widgets/service-monitoring/src/', $template, './', $centreon_path);

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

$centreon = $_SESSION['centreon'];
$centreonWebPath = trim($centreon->optGen['oreon_web_path'], '/');
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$stateSColors = [
    0 => '#88b917',
    1 => '#ff9a13',
    2 => '#e00b3d',
    3 => '#818285',
    4 => '#2ad1d4',
];
$stateHColors = [
    0 => '#88b917',
    1 => '#e00b3d',
    2 => '#82CFD8',
    4 => '#2ad1d4',
];

$stateLabels = [
    0 => 'Ok',
    1 => 'Warning',
    2 => 'Critical',
    3 => 'Unknown',
    4 => 'Pending',
];
$aStateType = ['1' => 'H', '0' => 'S'];
$mainQueryParameters = [];

// Build Query
$query = 'SELECT SQL_CALC_FOUND_ROWS h.host_id,
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
        cv.value AS criticality_level,
        h.icon_image
';
$query .= ' FROM hosts h JOIN instances i ON h.instance_id=i.instance_id, ';
$query .= ' services s ';
$query .= ' LEFT JOIN customvariables cv ON (s.service_id = cv.service_id AND s.host_id = cv.host_id AND cv.name = \'CRITICALITY_LEVEL\') ';
$query .= ' LEFT JOIN customvariables cv2 ON (s.service_id = cv2.service_id AND s.host_id = cv2.host_id AND cv2.name = \'CRITICALITY_ID\') ';

if (!$centreon->user->admin) {
    $query .= ' , centreon_acl acl ';
}

$query .= ' WHERE s.host_id = h.host_id ';
$query .= ' AND h.name NOT LIKE \'_Module_%\' ';
$query .= ' AND s.enabled = 1 ';
$query .= ' AND h.enabled = 1 ';
if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != '') {
    $tab = explode(' ', $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = [
            'parameter' => ':host_name_search',
            'value'     => $search,
            'type'      => PDO::PARAM_STR
        ];
        $hostNameCondition = 'h.name ' . CentreonUtils::operandToMysqlFormat($op) . ' :host_name_search ';
        $query = CentreonUtils::conditionBuilder($query, $hostNameCondition);
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != '') {
    $tab = explode(' ', $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = [
            'parameter' => ':service_description',
            'value'     => $search,
            'type'      => PDO::PARAM_STR
        ];
        $serviceDescriptionCondition = 's.description ' . CentreonUtils::operandToMysqlFormat($op) . ' :service_description ';
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
    $query = CentreonUtils::conditionBuilder($query, ' h.state != 1 ');
}
if (isset($preferences['hide_unreachable_host']) && $preferences['hide_unreachable_host']) {
    $query = CentreonUtils::conditionBuilder($query, ' h.state != 2 ');
}


if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, ' s.state IN (' . implode(',', $stateTab) . ')');
}

if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == 'ack') {
        $query = CentreonUtils::conditionBuilder($query, ' s.acknowledged = 1');
    } elseif ($preferences['acknowledgement_filter'] == 'nack') {
        $query = CentreonUtils::conditionBuilder($query, ' s.acknowledged = 0 AND h.acknowledged = 0 AND h.scheduled_downtime_depth = 0 ');
    }
}

if (isset($preferences['notification_filter']) && $preferences['notification_filter']) {
    if ($preferences['notification_filter'] == 'enabled') {
        $query = CentreonUtils::conditionBuilder($query, ' s.notify = 1');
    } elseif ($preferences['notification_filter'] == 'disabled') {
        $query = CentreonUtils::conditionBuilder($query, ' s.notify = 0');
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == 'downtime') {
        $query = CentreonUtils::conditionBuilder($query, ' s.scheduled_downtime_depth > 0 ');
    } elseif ($preferences['downtime_filter'] == 'ndowntime') {
        $query = CentreonUtils::conditionBuilder($query, ' s.scheduled_downtime_depth = 0 ');
    }
}

if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == 'hardonly') {
        $query = CentreonUtils::conditionBuilder($query, ' s.state_type = 1 ');
    } elseif ($preferences['state_type_filter'] == 'softonly') {
        $query = CentreonUtils::conditionBuilder($query, ' s.state_type = 0 ');
    }
}

if (isset($preferences['poller']) && $preferences['poller']) {
    $mainQueryParameters[] = [
        'parameter' => ':instance_id',
        'value'     => $preferences['poller'],
        'type'      => PDO::PARAM_INT
    ];
    $instanceIdCondition = ' i.instance_id = :instance_id';
    $query = CentreonUtils::conditionBuilder($query, $instanceIdCondition);
}

if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $mainQueryParameters[] = [
        'parameter' => ':hostgroup_hg_id',
        'value'     => $preferences['hostgroup'],
        'type'      => PDO::PARAM_INT
    ];
    $hostgroupHgIdCondition = <<<SQL
 s.host_id IN (
      SELECT host_host_id
      FROM {$conf_centreon['db']}.hostgroup_relation
      WHERE hostgroup_hg_id = :hostgroup_hg_id)
SQL;
    $query = CentreonUtils::conditionBuilder($query, $hostgroupHgIdCondition);
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {
    $queryHost = <<<SQL
SELECT DISTINCT h.host_id FROM servicegroups sg INNER JOIN services_servicegroups
    sgm ON sg.servicegroup_id = sgm.servicegroup_id INNER JOIN services s ON s.service_id = sgm.service_id
    INNER JOIN  hosts h ON sgm.host_id = h.host_id AND h.host_id = s.host_id WHERE  sg.servicegroup_id = :servicegroup_id
SQL;

    $resultHost = $dbb->prepare($queryHost);
    $resultHost->bindValue(':servicegroup_id', $preferences['servicegroup'], PDO::PARAM_INT);
    $resultHost->execute();

    $hosts = [];
    while ($row = $resultHost->fetch()) {
        $hosts[] = $row['host_id'];
    }
    $hostsList = implode(',', $hosts);
    unset($hosts);

    $mainQueryParameters[] = [
        'parameter' => ':servicegroup_id',
        'value'     => $preferences['servicegroup'],
        'type'      => PDO::PARAM_INT
    ];
    $servicegroupIdCondition = <<<SQL
 s.service_id IN (
            SELECT DISTINCT s.service_id FROM servicegroups sg, services_servicegroups sgm, 
            services s, hosts h WHERE h.host_id = s.host_id AND s.host_id = sgm.host_id AND s.service_id = sgm.service_id 
            AND sg.servicegroup_id = sgm.servicegroup_id
            AND sg.servicegroup_id = :servicegroup_id 
            AND h.host_id IN ({$hostsList}) 
      ) 
SQL;
    $query = CentreonUtils::conditionBuilder($query, $servicegroupIdCondition);
    unset($hostsList);
}
if (isset($preferences['display_severities']) && $preferences['display_severities'] && isset($preferences['criticality_filter']) && $preferences['criticality_filter'] != '') {
    $tab = explode(',', $preferences['criticality_filter']);
    $labels = '';
    foreach ($tab as $p) {
        if ($labels != '') {
            $labels .= ',';
        }
        $labels .= "'" . trim($p) . "'";
    }
    $query2 = "SELECT sc_id FROM service_categories WHERE sc_name IN ({$labels})";
    $RES = $db->query($query2);
    $idC = '';
    while ($d1 = $RES->fetch()) {
        if ($idC != '') {
            $idC .= ',';
        }
        $idC .= $d1['sc_id'];
    }
    $query .= " AND cv2.`value` IN ({$idC}) ";
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id
    AND acl.service_id = s.service_id
    AND acl.group_id IN ({$groupList})";
}
if (isset($preferences['output_search']) && $preferences['output_search'] != "") {
    $tab = explode(' ', $preferences['output_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = [
            'parameter' => ':service_output',
            'value'     => $search,
            'type'      => PDO::PARAM_STR
        ];
        $serviceOutputCondition = 's.output ' . CentreonUtils::operandToMysqlFormat($op) . ' :service_output ';
        $query = CentreonUtils::conditionBuilder($query, $serviceOutputCondition);
    }
}
$orderBy = 'hostname ASC , description ASC';

if (isset($preferences['order_by']) && trim($preferences['order_by']) != '') {
    $aOrder = explode(' ', $preferences['order_by']);
    if (in_array('last_state_change', $aOrder) || in_array('last_hard_state_change', $aOrder)) {
        if ($aOrder[1] == 'DESC') {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }
        $orderBy = $aOrder[0] . ' ' . $order;
    } else {
        $orderBy = $preferences['order_by'];
    }

    if (isset($preferences['order_by2']) && trim($preferences['order_by2']) != '') {
        $aOrder = explode(' ', $preferences['order_by2']);
        $orderBy .= ', ' . $aOrder[0] . ' ' . $aOrder[1];
    }
}

$query .= 'GROUP BY hostname, description ';

if (trim($orderBy)) {
    $query .= "ORDER BY {$orderBy}";
}

$query .= " LIMIT " . ($page * $preferences['entries']) . "," . $preferences['entries'];

$res = $dbb->prepare($query);

foreach ($mainQueryParameters as $parameter) {
    $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
}

unset($parameter, $mainQueryParameters);

$res->execute();

$nbRows = $res->rowCount();
$data = [];
$outputLength = $preferences['output_length'] ? $preferences['output_length'] : 50;
$commentLength = $preferences['comment_length'] ? $preferences['comment_length'] : 50;

$hostObj = new CentreonHost($db);
$svcObj = new CentreonService($db);
$gmt = new CentreonGMT($db);
$gmt->getMyGMTFromSession(session_id(), $db);
$allowedActionProtocols = ['http[s]?', '//', 'ssh', 'rdp', 'ftp', 'sftp'];
$allowedProtocolsRegex = '#(^'. implode(')|(^', $allowedActionProtocols) .')#'; // String starting with one of these protocols

while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        $data[$row['host_id'] . '_' . $row['service_id']][$key] = $value;
    }

    // last_check
    $valueLastCheck = $row['last_check'];
    $valueLastCheckTimestamp = time() - $valueLastCheck;
    if ($valueLastCheckTimestamp < 3600) {
        $valueLastCheck = CentreonDuration::toString($valueLastCheckTimestamp) . ' ago';
    } else {
//        $valueLastCheck = $gmt->getDate('Y-m-d H:i:s', $valueLastCheck);
        $valueLastCheck = (int)$row['last_check'];

    }
    $data[$row['host_id'] . '_' . $row['service_id']]['last_check'] = $valueLastCheck;

    // last_state_change
    $valueLastState = $row['last_state_change'];
    $valueLastStateTimestamp = time() - $valueLastState;
    if ($valueLastStateTimestamp < 3600) {
        $valueLastState = CentreonDuration::toString($valueLastStateTimestamp) . ' ago';
    } else {
        $valueLastState = (int)$row['last_state_change'];
    }
    $data[$row['host_id'] . '_' . $row['service_id']]['last_state_change'] = $valueLastState;

    // last_hard_state_change
    $valueLastHardState = $row['last_hard_state_change'];
    $valueLastHardStateTimestamp = time() - $valueLastHardState;
    if ($valueLastHardStateTimestamp < 3600) {
        $valueLastHardState = CentreonDuration::toString($valueLastHardStateTimestamp) . ' ago';
    } else {
        $valueLastHardState = (int)$row['last_hard_state_change'];
    }
    $data[$row['host_id'] . '_' . $row['service_id']]['last_hard_state_change'] = $valueLastHardState;

    // check_attempt
    $valueCheckAttempt = "{$row['check_attempt']}/{$row['max_check_attempts']} ({$aStateType[$row['state_type']]})";
    $data[$row['host_id'] . '_' . $row['service_id']]['check_attempt'] = $valueCheckAttempt;

    // s_state
    $data[$row['host_id'].'_'.$row['service_id']]['color'] = $stateSColors[$row['s_state']];
    $data[$row['host_id'].'_'.$row['service_id']]['s_state'] = $stateLabels[$row['s_state']];

    // h_state
    $value = $data[$row['host_id'].'_'.$row['service_id']]['hcolor'] = $stateHColors[$row['h_state']];
    $data[$row['host_id'].'_'.$row['service_id']]['h_state'] = $stateLabels[$row['h_state']];

    // output
    $data[$row['host_id'].'_'.$row['service_id']]['output'] = substr($row['output'], 0, $outputLength);

    // h_action_url
    $valueHActionUrl = $row['h_action_url'];
    if ($valueHActionUrl) {
        if (preg_match('#^\./(.+)#', $valueHActionUrl, $matches)) {
            $valueHActionUrl = '/' . $centreonWebPath . '/' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueHActionUrl)) {
            $valueHActionUrl = '//' . $valueHActionUrl;
        }

        $valueHActionUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['hostname'], $valueHActionUrl));
        $data[$row['host_id'] . '_' . $row['service_id']]['h_action_url'] = $valueHActionUrl;
    }

    // h_notes_url
    $valueHNotesUrl = $row['h_notes_url'];
    if ($valueHNotesUrl) {
        if (preg_match('#^\./(.+)#', $valueHNotesUrl, $matches)) {
            $valueHNotesUrl = '/' . $centreonWebPath . '/' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueHNotesUrl)) {
            $valueHNotesUrl = '//' . $valueHNotesUrl;
        }

        $valueHNotesUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['hostname'], $valueHNotesUrl));
        $data[$row['host_id'] . '_' . $row['service_id']]['h_notes_url'] = $valueHNotesUrl;
    }

    // s_action_url
    $valueSActionUrl = $row['s_action_url'];
    if ($valueSActionUrl) {
        if (preg_match('#^\./(.+)#', $valueSActionUrl, $matches)) {
            $valueSActionUrl = '/' . $centreonWebPath . '/' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueSActionUrl)) {
            $valueSActionUrl = '//' . $valueSActionUrl;
        }
        $valueSActionUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['hostname'], $valueSActionUrl));
        $valueSActionUrl = CentreonUtils::escapeSecure($svcObj->replaceMacroInString($row['service_id'], $valueSActionUrl));
        $data[$row['host_id'] . '_' . $row['service_id']]['s_action_url'] = $valueSActionUrl;
    }

    // s_notes_url
    $valueSNotesUrl = $row['s_notes_url'];
    if ($valueSNotesUrl) {
        if (preg_match('#^\./(.+)#', $valueSNotesUrl, $matches)) {
            $valueSNotesUrl = '/' . $centreonWebPath . '/' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueSNotesUrl)) {
            $valueSNotesUrl = '//' . $valueSNotesUrl;
        }
        $valueSNotesUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString($row['hostname'], $valueSNotesUrl));
        $valueSNotesUrl = CentreonUtils::escapeSecure($svcObj->replaceMacroInString($row['service_id'], $valueSNotesUrl));
        $data[$row['host_id'] . '_' . $row['service_id']]['s_notes_url'] = $valueSNotesUrl;
    }

    // criticality_id
    if ($value != '') {
        $critData = $criticality->getData($row['criticality_id'], 1);
        $valueCriticalityId = "<img src='../../img/media/" . $media->getFilename($critData['icon_id']) . "' title='" . $critData["sc_name"] . "' width='16' height='16'>";
        $data[$row['host_id'] . '_' . $row['service_id']]['criticality_id'] = $valueCriticalityId;
    }

    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        $commentSql = 'SELECT data FROM comments';
        $comment = '-';

        if (intval($row['s_acknowledged']) === 1) { // Service is acknowledged
            $commentSql = 'SELECT comment_data AS data FROM acknowledgements';
        } elseif (intval($row['s_scheduled_downtime_depth']) === 1) { // Service is in downtime
            $commentSql = 'SELECT comment_data AS data FROM downtimes';
        }

        $commentSql .= " WHERE host_id = {$row['host_id']} AND service_id = {$row['service_id']}";
        $commentSql .= ' ORDER BY entry_time DESC LIMIT 1';
        $commentResult = $dbb->query($commentSql);

        while ($commentRow = $commentResult->fetch()) {
            $comment = substr($commentRow['data'], 0, $commentLength);

            unset($commentRow);
        }

        $data[$row['host_id'] . '_' . $row['service_id']]['comment'] = $comment;
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
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('page', $page);
$template->assign('dataJS', count($data));
$template->assign('nbRows', $nbRows);
$template->assign('StateHColors', $stateHColors);
$template->assign('StateSColors', $stateSColors);
$template->assign('centreon_web_path', $centreon->optGen['oreon_web_path']);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->assign('broker', 'broker');
$template->display('table.ihtml');
