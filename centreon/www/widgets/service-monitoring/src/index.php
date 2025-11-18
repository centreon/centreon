<?php

/*
 * Copyright 2005-2021 Centreon
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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';
require_once $centreon_path . 'www/class/centreonAclLazy.class.php';
require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';

CentreonSession::start(1);
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

/**
 * @var CentreonDB $configurationDatabase
 */
$configurationDatabase = $dependencyInjector['configuration_db'];
if (CentreonSession::checkSession(session_id(), $configurationDatabase) == 0) {
    exit();
}

// Smarty template initialization
$template = SmartyBC::createSmartyTemplate($centreon_path . 'www/widgets/service-monitoring/src/', './');

// Init Objects
$criticality = new CentreonCriticality($configurationDatabase);
$media = new CentreonMedia($configurationDatabase);

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$widgetId = filter_input(
    INPUT_GET,
    'widgetId',
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 0]],
);

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 0]],
);

/**
 * @var CentreonDB $realtimeDatabase
 */
$realtimeDatabase = $dependencyInjector['realtime_db'];
$widgetObj = new CentreonWidget($centreon, $configurationDatabase);

/**
 * @var array{
 *     host_name_search: string,
 *     service_description_search: string,
 *     svc_ok: string,
 *     hide_down_host: string,
 *     hide_unreachable_host: string,
 *     svc_warning: string,
 *     svc_critical: string,
 *     svc_unknown: string,
 *     svc_pending: string,
 *     criticality_filter: string,
 *     acknowledgement_filter: string,
 *     notification_filter: string,
 *     downtime_filter: string,
 *     state_type_filter: string,
 *     poller: string,
 *     hostgroup: string,
 *     servicegroup: string,
 *     entries: string,
 *     output_search: string,
 *     display_severities: string,
 *     display_host_name: string,
 *     display_host_alias: string,
 *     display_chart_icon: string,
 *     display_svc_description: string,
 *     display_output: string,
 *     output_length: string,
 *     display_status: string,
 *     display_last_check: string,
 *     display_duration: string,
 *     display_hard_state_duration: string,
 *     display_tries: string,
 *     display_last_comment: string,
 *     display_latency: string,
 *     display_execution_time: string,
 *     comment_length: string,
 *     order_by: string,
 *     order_by2: string,
 *     refresh_interval: string,
 *     more_views: string
 * } $preferences
 */
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
    ? (int) $preferences['refresh_interval']
    : 30;

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

$aStateType = [
    '1' => 'H',
    '0' => 'S',
];

$mainQueryParameters = [];

$querySelect = <<<'SQL'
            SELECT DISTINCT
                1 AS REALTIME,
                h.host_id,
                h.name AS hostname,
                h.alias AS hostalias,
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
                s.perfdata,
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
                cv.value AS criticality_level,
                h.icon_image
    SQL;

$baseQuery = <<<'SQL'
        FROM hosts h
        INNER JOIN instances i
            ON h.instance_id = i.instance_id
        INNER JOIN services s
            ON s.host_id = h.host_id
        LEFT JOIN customvariables cv
            ON s.service_id = cv.service_id
            AND s.host_id = cv.host_id
            AND cv.name = 'CRITICALITY_LEVEL'
        LEFT JOIN customvariables cv2
            ON s.service_id = cv2.service_id
            AND s.host_id = cv2.host_id
            AND cv2.name = 'CRITICALITY_ID'
    SQL;

if (! empty($preferences['acknowledgement_filter']) &&  $preferences['acknowledgement_filter'] == 'ackByMe') {
    $baseQuery .= <<<'SQL'
            INNER JOIN acknowledgements ack
                ON s.service_id = ack.service_id
        SQL;
}

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    $accessGroups = $acls->getAccessGroups()->getIds();

    ['parameters' => $accessGroupParameters, 'placeholderList' => $accessGroupList] = createMultipleBindParameters(
        $accessGroups,
        'access_group',
        QueryParameterTypeEnum::INTEGER
    );

    $baseQuery .= <<<SQL
            INNER JOIN centreon_acl acl
                ON h.host_id = acl.host_id
                AND s.service_id = acl.service_id
                AND acl.group_id IN ({$accessGroupList})
        SQL;

    $mainQueryParameters = [...$accessGroupParameters, ...$mainQueryParameters];
}

$baseQuery .= <<<'SQL'
        WHERE h.name NOT LIKE '_Module_%'
          AND s.enabled = 1
          AND h.enabled = 1
    SQL;

if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != '') {
    $tab = explode(' ', $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = QueryParameter::string('host_name_search', $search);
        $hostNameCondition = 'h.name ' . CentreonUtils::operandToMysqlFormat($op) . ' :host_name_search ';
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, $hostNameCondition);
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != '') {
    $tab = explode(' ', $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = QueryParameter::string('service_description', $search);

        $serviceDescriptionCondition = 's.description '
            . CentreonUtils::operandToMysqlFormat($op) . ' :service_description ';
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, $serviceDescriptionCondition);
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
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' h.state != 1 ');
}
if (isset($preferences['hide_unreachable_host']) && $preferences['hide_unreachable_host']) {
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' h.state != 2 ');
}


if ($stateTab !== []) {
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.state IN (' . implode(',', $stateTab) . ')');
}
if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == 'ack') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.acknowledged = 1');
    } elseif ($preferences['acknowledgement_filter'] == 'ackByMe') {
        $mainQueryParameters[] = QueryParameter::string('ack_author', $centreon->user->alias);
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.acknowledged = 1 AND ack.author = :ack_author');
    } elseif ($preferences['acknowledgement_filter'] == 'nack') {
        $baseQuery = CentreonUtils::conditionBuilder(
            $baseQuery,
            ' s.acknowledged = 0 AND h.acknowledged = 0 AND h.scheduled_downtime_depth = 0 '
        );
    }
}

if (isset($preferences['notification_filter']) && $preferences['notification_filter']) {
    if ($preferences['notification_filter'] == 'enabled') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.notify = 1');
    } elseif ($preferences['notification_filter'] == 'disabled') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.notify = 0');
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == 'downtime') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.scheduled_downtime_depth > 0 ');
    } elseif ($preferences['downtime_filter'] == 'ndowntime') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.scheduled_downtime_depth = 0 ');
    }
}

if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == 'hardonly') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.state_type = 1 ');
    } elseif ($preferences['state_type_filter'] == 'softonly') {
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, ' s.state_type = 0 ');
    }
}

if (isset($preferences['poller']) && $preferences['poller']) {
    $mainQueryParameters[] = QueryParameter::int('instance_id', $preferences['poller']);
    $instanceIdCondition = ' i.instance_id = :instance_id';
    $baseQuery = CentreonUtils::conditionBuilder($baseQuery, $instanceIdCondition);
}

if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $results = explode(',', $preferences['hostgroup']);
    $queryHG = '';
    foreach ($results as $result) {
        if ($queryHG != '') {
            $queryHG .= ', ';
        }
        $queryHG .= ':id_' . $result;
        $mainQueryParameters[] = QueryParameter::int('id_' . $result, (int) $result);
    }
    $baseQuery = CentreonUtils::conditionBuilder(
        $baseQuery,
        " s.host_id IN (
            SELECT host_host_id
            FROM `" . $conf_centreon['db'] . "`.hostgroup_relation
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
        $querySG .= ':id_' . $resultSG;
        $mainQueryParameters[] = QueryParameter::int('id_' . $resultSG, (int) $resultSG);
    }
    $baseQuery = CentreonUtils::conditionBuilder(
        $baseQuery,
        " s.service_id IN (
            SELECT DISTINCT service_id
            FROM services_servicegroups
            WHERE servicegroup_id IN (" . $querySG . ")
        )"
    );
}
if (!empty($preferences['criticality_filter'])) {
    $tab = explode(',', $preferences['criticality_filter']);
    $labels = [];
    foreach ($tab as $p) {
        $labels[] = ':id_' . $p;
        $mainQueryParameters[] = QueryParameter::int('id_' . $p, (int) $p);
    }
    $baseQuery = CentreonUtils::conditionBuilder(
        $baseQuery,
        'cv2.value IN (' . implode(',', $labels) . ')'
    );
}
if (isset($preferences['output_search']) && $preferences['output_search'] != "") {
    $tab = explode(' ', $preferences['output_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != '') {
        $mainQueryParameters[] = QueryParameter::string('service_output', $search);
        $serviceOutputCondition = 's.output ' . CentreonUtils::operandToMysqlFormat($op) . ' :service_output ';
        $baseQuery = CentreonUtils::conditionBuilder($baseQuery, $serviceOutputCondition);
    }
}

$orderByClause = 'hostname ASC ';

// Define allowed columns and directions
$allowedOrderColumns = [
    'host_id',
    'hostname',
    'hostalias',
    'latency',
    'execution_time',
    'h_state',
    'service_id',
    'description',
    's_state',
    'state_type',
    'last_hard_state',
    'output',
    's_scheduled_downtime_depth',
    's_acknowledged',
    's_notify',
    'perfdata',
    's_active_checks',
    's_passive_checks',
    'h_scheduled_downtime_depth',
    'h_acknowledged',
    'h_notify',
    'h_active_checks',
    'h_passive_checks',
    'last_check',
    'last_state_change',
    'last_hard_state_change',
    'check_attempt',
    'max_check_attempts',
    'h_action_url',
    'h_notes_url',
    's_action_url',
    's_notes_url',
    'criticality_id',
    'criticality_level',
    'icon_image'
];
$allowedDirections = ['ASC', 'DESC'];

$countQuery = 'SELECT COUNT(*) ' . $baseQuery;
$nbRows = (int) $realtimeDatabase->fetchOne($countQuery, QueryParameters::create($mainQueryParameters));

if (isset($preferences['order_by']) && trim($preferences['order_by']) != '') {
    $aOrder = explode(' ', trim($preferences['order_by']));
    $column = $aOrder[0] ?? '';
    $direction = isset($aOrder[1]) ? strtoupper($aOrder[1]) : 'ASC';
    if (in_array($column, $allowedOrderColumns, true) && in_array($direction, $allowedDirections, true)) {
        if (in_array($column, ['last_state_change', 'last_hard_state_change'], true)) {
            $direction = ($direction === 'DESC') ? 'ASC' : 'DESC';
        }
        $orderByClause = $column . ' ' . $direction;
    }

    if (isset($preferences['order_by2']) && trim($preferences['order_by2']) !== '') {
        $aOrder2 = explode(' ', trim($preferences['order_by2']));
        $column2 = $aOrder2[0] ?? '';
        $direction2 = isset($aOrder2[1]) ? strtoupper($aOrder2[1]) : 'ASC';

        if (in_array($column2, $allowedOrderColumns, true) && in_array($direction2, $allowedDirections, true)) {
            $orderByClause .= ', ' . $column2 . ' ' . $direction2;
        }
    }
}

if (trim($orderByClause)) {
    $baseQuery .= 'ORDER BY ' . $orderByClause;
}

$num = filter_var($preferences['entries'], FILTER_VALIDATE_INT) ?: 10;
$baseQuery .= ' LIMIT ' . ($page * $num) . ',' . $num;

$records = $realtimeDatabase->fetchAllAssociative($querySelect . $baseQuery, QueryParameters::create($mainQueryParameters));

$data = [];
$outputLength = $preferences['output_length'] ?: 50;
$commentLength = $preferences['comment_length'] ?: 50;

$hostObj = new CentreonHost($configurationDatabase);
$svcObj = new CentreonService($configurationDatabase);
$gmt = new CentreonGMT();
$gmt->getMyGMTFromSession(session_id());
$allowedActionProtocols = ['http[s]?', '//', 'ssh', 'rdp', 'ftp', 'sftp'];
$allowedProtocolsRegex = '#(^' . implode(
    ')|(^',
    $allowedActionProtocols
) . ')#'; // String starting with one of these protocols

foreach ($records as $record) {
    foreach ($record as $key => $value) {
        $data[$record['host_id'] . '_' . $record['service_id']][$key] = $value;
    }

    $dataKey = $record['host_id'] . '_' . $record['service_id'];

    // last_check
    $valueLastCheck = (int) $record['last_check'];
    $valueLastCheckTimestamp = time() - $valueLastCheck;
    if (
        $valueLastCheckTimestamp > 0
        && $valueLastCheckTimestamp < 3600
    ) {
        $valueLastCheck = CentreonDuration::toString($valueLastCheckTimestamp) . ' ago';
    }
    $data[$dataKey]['last_check'] = $valueLastCheck;

    // last_state_change
    $valueLastState = (int) $record['last_state_change'];
    if ($valueLastState > 0) {
        $valueLastStateTimestamp = time() - $valueLastState;
        $valueLastState = CentreonDuration::toString($valueLastStateTimestamp) . ' ago';
    } else {
        $valueLastState = 'N/A';
    }
    $data[$dataKey]['last_state_change'] = $valueLastState;

    // last_hard_state_change
    $valueLastHardState = (int) $record['last_hard_state_change'];
    if ($valueLastHardState > 0) {
        $valueLastHardStateTimestamp = time() - $valueLastHardState;
        $valueLastHardState = CentreonDuration::toString($valueLastHardStateTimestamp) . ' ago';
    } else {
        $valueLastHardState = 'N/A';
    }
    $data[$dataKey]['last_hard_state_change'] = $valueLastHardState;

    // check_attempt
    $valueCheckAttempt = $record['check_attempt'] . '/'
        . $record['max_check_attempts'] . ' (' . $aStateType[$record['state_type']] . ')';
    $data[$dataKey]['check_attempt'] = $valueCheckAttempt;

    // s_state
    $data[$dataKey]['color'] = $stateSColors[$record['s_state']];
    $data[$dataKey]['s_state'] = $stateLabels[$record['s_state']];

    // h_state
    $value = $data[$dataKey]['hcolor'] = $stateHColors[$record['h_state']];
    $data[$dataKey]['h_state'] = $stateLabels[$record['h_state']];

    // output
    $data[$dataKey]['output'] = htmlspecialchars(substr($record['output'], 0, $outputLength));

    $kernel = \App\Kernel::createForWeb();
    $resourceController = $kernel->getContainer()->get(
        \Centreon\Application\Controller\MonitoringResourceController::class
    );
    $data[$dataKey]['h_details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=20202&o=hd&host_name=' . $record['hostname']
        : $resourceController->buildHostDetailsUri($record['host_id']);

    $data[$dataKey]['s_details_uri'] = $useDeprecatedPages
        ? '../../main.php?p=20201&o=svcd&host_name=' . $record['hostname']
            . '&service_description=' . $record['description']
        : $resourceController->buildServiceDetailsUri($record['host_id'], $record['service_id']);

    $data[$dataKey]['s_graph_uri'] = $useDeprecatedPages
        ? '../../main.php?p=204&mode=0&svc_id=' . $record['hostname'] . ';' . $record['description']
        : $resourceController->buildServiceUri(
            $record['host_id'],
            $record['service_id'],
            $resourceController::TAB_GRAPH_NAME
        );

    // h_action_url
    $valueHActionUrl = $record['h_action_url'];
    if ($valueHActionUrl) {
        if (preg_match('#^\./(.+)#', $valueHActionUrl, $matches)) {
            $valueHActionUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueHActionUrl)) {
            $valueHActionUrl = '//' . $valueHActionUrl;
        }

        $valueHActionUrl = CentreonUtils::escapeSecure(
            $hostObj->replaceMacroInString(
                $record['hostname'],
                $valueHActionUrl
            )
        );
        $data[$dataKey]['h_action_url'] = $valueHActionUrl;
    }

    // h_notes_url
    $valueHNotesUrl = $record['h_notes_url'];
    if ($valueHNotesUrl) {
        if (preg_match('#^\./(.+)#', $valueHNotesUrl, $matches)) {
            $valueHNotesUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueHNotesUrl)) {
            $valueHNotesUrl = '//' . $valueHNotesUrl;
        }

        $valueHNotesUrl = CentreonUtils::escapeSecure(
            $hostObj->replaceMacroInString(
                $record['hostname'],
                $valueHNotesUrl
            )
        );
        $data[$dataKey]['h_notes_url'] = $valueHNotesUrl;
    }

    // s_action_url
    $valueSActionUrl = $record['s_action_url'];
    if ($valueSActionUrl) {
        if (preg_match('#^\./(.+)#', $valueSActionUrl, $matches)) {
            $valueSActionUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueSActionUrl)) {
            $valueSActionUrl = '//' . $valueSActionUrl;
        }
        $valueSActionUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString(
            $record['hostname'],
            $valueSActionUrl
        ));
        $valueSActionUrl = CentreonUtils::escapeSecure($svcObj->replaceMacroInString(
            $record['service_id'],
            $valueSActionUrl
        ));
        $data[$dataKey]['s_action_url'] = $valueSActionUrl;
    }

    // s_notes_url
    $valueSNotesUrl = $record['s_notes_url'];
    if ($valueSNotesUrl) {
        if (preg_match('#^\./(.+)#', $valueSNotesUrl, $matches)) {
            $valueSNotesUrl = '../../' . $matches[1];
        } elseif (!preg_match($allowedProtocolsRegex, $valueSNotesUrl)) {
            $valueSNotesUrl = '//' . $valueSNotesUrl;
        }
        $valueSNotesUrl = CentreonUtils::escapeSecure($hostObj->replaceMacroInString(
            $record['hostname'],
            $valueSNotesUrl
        ));
        $valueSNotesUrl = CentreonUtils::escapeSecure($svcObj->replaceMacroInString(
            $record['service_id'],
            $valueSNotesUrl
        ));
        $data[$dataKey]['s_notes_url'] = $valueSNotesUrl;
    }

    // criticality_id
    if ($value != '') {
        $critData = $criticality->getData($record['criticality_id'], 1);

        // get criticality icon path
        $valueCriticalityId = "";
        if (isset($critData['icon_id'])) {
            $valueCriticalityId = "<img src='../../img/media/" . $media->getFilename($critData['icon_id']) .
                "' title='" . $critData["sc_name"] . "' width='16' height='16'>";
        }

        $data[$dataKey]['criticality_id'] = $valueCriticalityId;
    }

    // FIXME: A SQL REQUEST IS PLAYED ON EACH LINE TO GET THE COMMENTS !!!!!!
    // Meaning that for 100 results on which a comment has been set we get 100 SQL requests...
    if (isset($preferences['display_last_comment']) && $preferences['display_last_comment']) {
        $commentSql = 'SELECT 1 AS REALTIME, data FROM comments';
        $comment = '-';

        if ((int) $record['s_acknowledged'] === 1) { // Service is acknowledged
            $commentSql = 'SELECT 1 AS REALTIME, comment_data AS data FROM acknowledgements';
        } elseif ((int) $record['s_scheduled_downtime_depth'] === 1) { // Service is in downtime
            $commentSql = 'SELECT 1 AS REALTIME, comment_data AS data FROM downtimes';
        }

        $commentSql .= ' WHERE host_id = ' . $record['host_id'] . ' AND service_id = ' . $record['service_id'];
        $commentSql .= ' ORDER BY entry_time DESC LIMIT 1';
        $commentResult = $realtimeDatabase->query($commentSql);

        while ($commentRow = $commentResult->fetch()) {
            $comment = substr($commentRow['data'], 0, $commentLength);

            unset($commentRow);
        }

        $data[$dataKey]['comment'] = $comment;
    }

    $data[$dataKey]['encoded_description'] = urlencode($data[$dataKey]['description']);
    $data[$dataKey]['encoded_hostname'] = urlencode($data[$dataKey]['hostname']);
}

$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('preferences', $preferences);
$template->assign('page', $page);
$template->assign('dataJS', count($data));
$template->assign('nbRows', $nbRows);
$template->assign('StateHColors', $stateHColors);
$template->assign('StateSColors', $stateSColors);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->assign('broker', 'broker');
$template->display('table.ihtml');
