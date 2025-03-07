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

use App\Kernel;
use Centreon\Application\Controller\MonitoringResourceController;
use Centreon\Domain\Log\Logger;

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

CentreonSession::start(1);
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

$db = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

/**
 * @var $dbb CentreonDB
 */
$dbb = $dependencyInjector['realtime_db'];

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

// Smarty template initialization
$path = $centreon_path . 'www/widgets/host-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

$centreon = $_SESSION['centreon'];

$kernel = Kernel::createForWeb();
/** @var Logger $logger */
$logger = $kernel->getContainer()->get(Logger::class);

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

$mainQueryParameters = [];

try {
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
} catch (Exception $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while getting widget preferences for the host monitoring custom view',
        ['widget_id' => $widgetId],
        $e
    );
    throw $e;
}

// Default colors
$stateColors = getColors($db);
// Get status labels
$stateLabels = getLabels();

$aStateType = ['1' => 'H', '0' => 'S'];

$query = 'SELECT SQL_CALC_FOUND_ROWS
    1 AS REALTIME,
    h.host_id,
    h.name AS host_name,
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
        ON (cv.host_id = h.host_id AND cv.service_id = 0 AND cv.name = \'CRITICALITY_LEVEL\')
    LEFT JOIN `customvariables` cv2
        ON (cv2.host_id = h.host_id AND cv2.service_id = 0 AND cv2.name = \'CRITICALITY_ID\')
    WHERE enabled = 1
    AND h.name NOT LIKE \'_Module_%\' ';
$stateTab = [];

if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != '') {
    $tab = explode(' ', $preferences['host_name_search']);
    $op = $tab[0];

    if (isset($tab[1])) {
        $search = $tab[1];
    }

    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = ['parameter' => ':host_name_search', 'value' => $search, 'type' => PDO::PARAM_STR];
        $hostNameCondition = 'h.name ' . CentreonUtils::operandToMysqlFormat($op) . ' :host_name_search ';
        $query = CentreonUtils::conditionBuilder($query, $hostNameCondition);
    }
}

if (isset($preferences['notification_filter']) && $preferences['notification_filter']) {
    if ($preferences['notification_filter'] == "enabled") {
        $query = CentreonUtils::conditionBuilder($query, " notify = 1");
    } elseif ($preferences['notification_filter'] == "disabled") {
        $query = CentreonUtils::conditionBuilder($query, " notify = 0");
    }
}

if (isset($preferences['host_up']) && $preferences['host_up']) {
    $stateTab[] = 0;
}
if (isset($preferences['host_down']) && $preferences['host_down']) {
    $stateTab[] = 1;
}
if (isset($preferences['host_unreachable']) && $preferences['host_unreachable']) {
    $stateTab[] = 2;
}
if ($stateTab !== []) {
    $query = CentreonUtils::conditionBuilder($query, ' state IN (' . implode(',', $stateTab) . ')');
}
if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == 'ack') {
        $query = CentreonUtils::conditionBuilder($query, ' acknowledged = 1');
    } elseif ($preferences['acknowledgement_filter'] == 'nack') {
        $query = CentreonUtils::conditionBuilder($query, ' acknowledged = 0');
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == 'downtime') {
        $query = CentreonUtils::conditionBuilder($query, ' scheduled_downtime_depth > 0 ');
    } elseif ($preferences['downtime_filter'] == 'ndowntime') {
        $query = CentreonUtils::conditionBuilder($query, ' scheduled_downtime_depth = 0 ');
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
            'type' => PDO::PARAM_INT
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
if (!empty($preferences['criticality_filter'])) {
    $tab = explode(',', $preferences['criticality_filter']);
    $labels = '';
    foreach ($tab as $p) {
        if ($labels != '') {
            $labels .= ',';
        }
        $labels .= ":severity_id_" . $p;
        $mainQueryParameters[] = [
            'parameter' => ":severity_id_" . $p,
            'value' => (int)$p,
            'type' => PDO::PARAM_INT
        ];
    }
    $severityIdCondition = 'cv2.value IN (' . $labels . ')';
    $query = CentreonUtils::conditionBuilder($query, $severityIdCondition);
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $query .= $aclObj->queryBuilder('AND', 'h.host_id', $aclObj->getHostsString('ID', $dbb));
}

// prepare order_by
$orderBy = 'h.name ASC';

// Define allowed columns and directions
$allowedOrderColumns = [
    'h.name',
    'h.alias',
    'criticality',
    'address',
    'state',
    'output',
    'check_attempt',
    'last_check',
    'last_state_change',
    'last_hard_state_change',
    'scheduled_downtime_depth',
    'acknowledged',
    'notify',
    'active_checks',
    'passive_checks'
];

const ORDER_DIRECTION_ASC = 'ASC';
const ORDER_DIRECTION_DESC = 'DESC';

$allowedDirections = [ORDER_DIRECTION_ASC, ORDER_DIRECTION_DESC];
$defaultDirection = ORDER_DIRECTION_ASC;

$orderByToAnalyse = isset($preferences['order_by'])
    ? trim($preferences['order_by'])
    : null;

if ($orderByToAnalyse !== null) {
    $orderByToAnalyse .= " $defaultDirection";
    [$column, $direction] = explode(' ', $orderByToAnalyse);

    if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
        $orderBy = $column . ' ' . $direction;
    }
}

// concatenate order by + limit + offset  to the query
$query .= "ORDER BY " . $orderBy . " LIMIT :limit OFFSET :offset";

$num = filter_var($preferences['entries'], FILTER_VALIDATE_INT) ?: 10;
$mainQueryParameters[] = [
    'parameter' => "limit",
    'value' => $num,
    'type' => PDO::PARAM_INT
];
$mainQueryParameters[] = [
    'parameter' => "offset",
    'value' => ($page * $num),
    'type' => PDO::PARAM_INT
];

try {
    $res = $dbb->prepare($query);
    foreach ($mainQueryParameters as $parameter) {
        $res->bindValue($parameter['parameter'], $parameter['value'], $parameter['type']);
    }
    $res->execute();
} catch (PDOException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while getting hosts for the host monitoring custom view',
        ['pdo_info' => $e->errorInfo, 'query_parameters' => $mainQueryParameters],
        $e
    );
    throw $e;
}

unset($mainQueryParameters);


try {
    $nbRows = (int) $dbb->query('SELECT FOUND_ROWS() AS REALTIME')->fetchColumn();
} catch (PDOException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while counting hosts for the host monitoring custom view',
        ['pdo_info' => $e->errorInfo],
        $e
    );
    throw $e;
}

$data = [];
$outputLength = $preferences['output_length'] ?: 50;
$commentLength = $preferences['comment_length'] ?: 50;

try {
    $hostObj = new CentreonHost($db);
} catch (PDOException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error when CentreonHost called for the host monitoring custom view',
        ['pdo_info' => $e->errorInfo],
        $e
    );
    throw $e;
}

$gmt = new CentreonGMT();
$gmt->getMyGMTFromSession(session_id());
$allowedActionProtocols = ['http[s]?', '//', 'ssh', 'rdp', 'ftp', 'sftp'];
$allowedProtocolsRegex = '#(^' . implode(')|(^', $allowedActionProtocols) . ')#';
// String starting with one of these protocols

while ($row = $res->fetch()) {
    foreach ($row as $key => $value) {
        $data[$row['host_id']][$key] = $value;
    }

    // last_check
    $valueLastCheck = (int)$row['last_check'];
    $valueLastCheckTimestamp = time() - $valueLastCheck;
    if (
        $valueLastCheckTimestamp > 0
        && $valueLastCheckTimestamp < 3600
    ) {
        $valueLastCheck = CentreonDuration::toString($valueLastCheckTimestamp) . ' ago';
    }
    $data[$row['host_id']]['last_check'] = $valueLastCheck;

    // last_state_change
    $valueLastState = (int)$row['last_state_change'];
    if ($valueLastState > 0) {
        $valueLastStateTimestamp = time() - $valueLastState;
        $valueLastState = CentreonDuration::toString($valueLastStateTimestamp) . ' ago';
    } else {
        $valueLastState = 'N/A';
    }
    $data[$row['host_id']]['last_state_change'] = $valueLastState;

    // last_hard_state_change
    $valueLastHardState = (int)$row['last_hard_state_change'];
    if ($valueLastHardState > 0) {
        $valueLastHardStateTimestamp = time() - $valueLastHardState;
        $valueLastHardState = CentreonDuration::toString($valueLastHardStateTimestamp) . ' ago';
    } else {
        $valueLastHardState = 'N/A';
    }
    $data[$row['host_id']]['last_hard_state_change'] = $valueLastHardState;

    // check_attempt
    $valueCheckAttempt = "{$row['check_attempt']}/{$row['max_check_attempts']} ({$aStateType[$row['state_type']]})";
    $data[$row['host_id']]['check_attempt'] = $valueCheckAttempt;

    // state
    $valueState = $row['state'];
    $data[$row['host_id']]['status'] = $valueState;
    $data[$row['host_id']]['color'] = $stateColors[$valueState];
    $data[$row['host_id']]['state'] = $stateLabels[$valueState];

    // output
    $data[$row['host_id']]['output'] = substr($row['output'], 0, $outputLength);


    $resourceController = $kernel->getContainer()->get(MonitoringResourceController::class);
    $data[$row['host_id']]['details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=20202&o=hd&host_name=' . $row['host_name']
        : $resourceController->buildHostDetailsUri($row['host_id']);

    // action_url
    $valueActionUrl = $row['action_url'];
    if (!empty($valueActionUrl)) {
        if (preg_match('#^\./(.+)#', $valueActionUrl, $matches)) {
            $valueActionUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueActionUrl)) {
            $valueActionUrl = '//' . $valueActionUrl;
        }

        $valueActionUrl = CentreonUtils::escapeSecure(
            $hostObj->replaceMacroInString($row['host_name'], $valueActionUrl)
        );
        $data[$row['host_id']]['action_url'] = $valueActionUrl;
    }

    // notes_url
    $valueNotesUrl = $row['notes_url'];
    if (!empty($valueNotesUrl)) {
        if (preg_match('#^\./(.+)#', $valueNotesUrl, $matches)) {
            $valueNotesUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueNotesUrl)) {
            $valueNotesUrl = '//' . $valueNotesUrl;
        }

        $valueNotesUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString(
            $row['host_name'],
            $valueNotesUrl
        ));
        $data[$row['host_id']]['notes_url'] = $valueNotesUrl;
    }

    // criticality
    $valueCriticality = $row['criticality'];
    if ($valueCriticality != '') {
        $critData = $criticality->getData($row["criticality_id"]);
        $valueCriticality = "<img src='../../img/media/" . $media->getFilename($critData['icon_id']) .
            "' title='" . $critData["hc_name"] . "' width='16' height='16'>";
        $data[$row['host_id']]['criticality'] = $valueCriticality;
    }

    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        try {
            $query = <<<SQL
                SELECT data FROM comments where host_id = :hostId
                AND service_id = 0 ORDER BY entry_time DESC LIMIT 1
            SQL;
            $res2 = $dbb->prepare($query);
            $res2->bindValue(':hostId', $row['host_id'], PDO::PARAM_INT);
            $res2->execute();
            $data[$row['host_id']]['comment'] = ($row2 = $res2->fetch()) ? substr($row2['data'], 0, $commentLength) : '-';
            $res2->closeCursor();
        } catch (PDOException $e) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error while getting data from comments for the host monitoring custom view',
                ['pdo_info' => $e->errorInfo, 'host_id' => $row['host_id'] ?? null],
                $e
            );
            throw $e;
        }
    }

    $data[$row['host_id']]['encoded_host_name'] = urlencode($data[$row['host_id']]['host_name']);

    $class = null;
    if ($row['scheduled_downtime_depth'] > 0) {
        $class = 'line_downtime';
    } elseif ($row['state'] == 1) {
        $class = $row['acknowledged'] == 1 ? 'line_ack' : 'list_down';
    } elseif ($row['acknowledged'] == 1) {
        $class = 'line_ack';
    }
    $data[$row['host_id']]['class_tr'] = $class;
}

$res->closeCursor();

$aColorHost = [
    0 => 'host_up',
    1 => 'host_down',
    2 => 'host_unreachable',
    4 => 'host_pending'
];

$autoRefresh = (isset($preferences['refresh_interval']) && (int)$preferences['refresh_interval'] > 0)
    ? (int)$preferences['refresh_interval']
    : 30;
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('page', $page);
$template->assign('dataJS', count($data));
$template->assign('nbRows', $nbRows);
$template->assign('aColorHost', $aColorHost);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->assign('broker', 'broker');
$template->assign('title_graph', _('See Graphs of this host'));
$template->assign('title_flapping', _('Host is flapping'));

$bMoreViews = 0;

if ($preferences['more_views']) {
    $bMoreViews = $preferences['more_views'];
}

$template->assign('more_views', $bMoreViews);

try {
    $template->display('table.ihtml');
} catch (Exception $e) {
    $logger->error(
        "Error while displaying the host monitoring custom view",
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'exception_type' => $e::class,
            'exception_message' => $e->getMessage()
        ]
    );
    throw $e;
}
