<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Include configurations files
include_once "../../../../config/centreon.config.php";

// Require Classes
require_once _CENTREON_PATH_ . "www/class/centreonSession.class.php";
require_once _CENTREON_PATH_ . "www/class/centreon.class.php";
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/../Paginator.php';
require_once __DIR__ . '/PaginationRenderer.php';
require_once __DIR__ . '/../../../class/centreonLog.class.php';
require_once __DIR__ . '/../../../class/exceptions/CentreonDbException.php';

// Connect to DB
$pearDB = $dependencyInjector['configuration_db'];
$pearDBO = $dependencyInjector['realtime_db'];

// Check Session
CentreonSession::start();
if (!CentreonSession::checkSession(session_id(), $pearDB)) {
    print "Bad Session";
    exit();
}

/**
 * @var Centreon $centreon
 */
$centreon = $_SESSION["centreon"];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

/**
 * Language informations init
 */
$locale = $centreon->user->get_lang();
putenv("LANG=$locale");
setlocale(LC_ALL, $locale);
bindtextdomain("messages", _CENTREON_PATH_ . "/www/locale/");
bind_textdomain_codeset("messages", "UTF-8");
textdomain("messages");

define("STATUS_OK", 0);
define("STATUS_WARNING", 1);
define("STATUS_CRITICAL", 2);
define("STATUS_UNKNOWN", 3);
define("STATUS_PENDING", 4);
define("STATUS_ACKNOWLEDGEMENT", 5);
define("STATUS_UP", 0);
define("STATUS_DOWN", 1);
define("STATUS_UNREACHABLE", 2);
define("TYPE_SOFT", 0);
define("TYPE_HARD", 1);

/**
 * Defining constants for the ACK message types
 */
define('SERVICE_ACKNOWLEDGEMENT_MSG_TYPE', 10);
define('HOST_ACKNOWLEDGEMENT_MSG_TYPE', 11);

// Include Access Class
include_once _CENTREON_PATH_ . "www/class/centreonACL.class.php";
include_once _CENTREON_PATH_ . "www/class/centreonXML.class.php";
include_once _CENTREON_PATH_ . "www/class/centreonGMT.class.php";
include_once _CENTREON_PATH_ . "www/include/common/common-Func.php";

$defaultLimit = $centreon->optGen['maxViewConfiguration'] > 1
    ? (int) $centreon->optGen['maxViewConfiguration']
    : 30;

/**
 * Retrieves and optionally sanitizes a value from GET or POST input, with fallback defaults.
 *
 * This function checks for a specified key in the `$_GET` or `$_POST` arrays,
 * applying optional sanitization and filtering if required. It supports a
 * fallback default value if the key does not exist in either array.
 *
 * @param string $key The name of the input key to retrieve from `$_GET` or `$_POST`.
 * @param bool $sanitize If true, applies HTML sanitization to the value.
 * @param mixed $default A default value returned if the key does not exist in the inputs.
 * @param int|null $filter Optional filter constant for input validation, e.g., `FILTER_VALIDATE_INT`.
 *
 * @return mixed The sanitized and filtered input value, or the default value if the key is not found.
 *
 * Usage:
 * ```php
 * $name = getInput('name', true, 'Guest');
 * $age = getInput('age', false, 0, FILTER_VALIDATE_INT);
 * ```
 */
function getInput($key, $sanitize = false, $default = null, $filter = null)
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;

    if ($filter !== null && $value !== null) {
        $value = filter_var($value, $filter, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    if ($sanitize && $value !== null) {
        // Sanitizing the input
        $value = HtmlSanitizer::createFromString($value)
            ->removeTags()  // Remove all HTML tags
            ->sanitize()    // Convert special characters to HTML entities
            ->getString();
    }

    return $value;
}

// Define input specifications
$inputDefinitions = [
    'lang' => ['sanitize' => true, 'default' => null],
    'id' => ['sanitize' => true, 'default' => '-1'],
    'num' => ['default' => 0, 'filter' => FILTER_VALIDATE_INT],
    'limit' => ['default' => $defaultLimit, 'filter' => FILTER_VALIDATE_INT],
    'StartDate' => ['sanitize' => true, 'default' => ''],
    'EndDate' => ['sanitize' => true, 'default' => ''],
    'StartTime' => ['sanitize' => true, 'default' => ''],
    'EndTime' => ['sanitize' => true, 'default' => ''],
    'period' => ['default' => -1, 'filter' => FILTER_VALIDATE_INT],
    'engine' => ['default' => 'false'],
    'up' => ['default' => 'true'],
    'down' => ['default' => 'true'],
    'unreachable' => ['default' => 'true'],
    'ok' => ['default' => 'true'],
    'warning' => ['default' => 'true'],
    'critical' => ['default' => 'true'],
    'unknown' => ['default' => 'true'],
    'acknowledgement' => ['default' => 'true'],
    'notification' => ['default' => 'false'],
    'alert' => ['default' => 'true'],
    'oh' => ['default' => 'false'],
    'error' => ['default' => 'false'],
    'output' => ['sanitize' => true, 'default' => ''],
    'search_H' => ['default' => 'VIDE'],
    'search_S' => ['default' => 'VIDE'],
    'search_host' => ['default' => ''],
    'search_service' => ['default' => ''],
    'export' => ['default' => 0],
];

// Collect inputs
$inputs = [];
foreach ($inputDefinitions as $key => $properties) {
    $sanitize = $properties['sanitize'] ?? false;
    $default = $properties['default'] ?? null;
    $filter = $properties['filter'] ?? null;

    $inputs[$key] = getInput($key, $sanitize, $default, $filter);
}

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

// Start XML document root
$buffer = new CentreonXML();
$buffer->startElement("root");

/*
 * Security check
 */
$lang_ = $inputs["lang"] ?? "-1";
$openid = $inputs["id"];
$sid = session_id();
$sid ??= "-1";

/*
 * Init GMT class
 */
$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession($sid);

/*
 * Check Session
 */
$contact_id = check_session($sid, $pearDB);

$is_admin = isUserAdmin($sid);
if (isset($sid) && $sid) {
    $access = new CentreonAcl($contact_id, $is_admin);
    $lca = [
        "LcaHost" => $access->getHostsServices($pearDBO, 1),
        "LcaHostGroup" => $access->getHostGroups(),
        "LcaSG" => $access->getServiceGroups()
    ];
}

// Binding limit value
$num = filter_var($inputs['num'], FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
$limit = filter_var($inputs['limit'], FILTER_VALIDATE_INT, ['options' => ['default' => 30]]);

// use Binding, to avoid SQL injection
$StartDate = $inputs["StartDate"];
$EndDate = $inputs["EndDate"];
$StartTime = $inputs["StartTime"];
$EndTime = $inputs["EndTime"];
$auto_period = (int)$inputs["period"];
$engine = $inputs["engine"];
$up = $inputs["up"];
$down = $inputs["down"];
$unreachable = $inputs["unreachable"];
$ok = $inputs["ok"];
$warning = $inputs["warning"];
$critical = $inputs["critical"];
$unknown = $inputs["unknown"];
$acknowledgement = $inputs["acknowledgement"];
$notification = $inputs["notification"];
$alert = $inputs["alert"];
$oh = $inputs["oh"];
$error = $inputs["error"];
$output = isset($inputs["output"]) ? urldecode($inputs["output"]) : "";
$search_H = $inputs["search_H"];
$search_S = $inputs["search_S"];
$search_host = $inputs["search_host"];
$search_service = $inputs["search_service"];
$export = $inputs["export"];

$start = 0;
$end = time();

if ($engine == "true") {
    $ok = "false";
    $up = "false";
    $unknown = "false";
    $unreachable = "false";
    $down = "false";
    $warning = "false";
    $critical = "false";
    $acknowledgement = "false";
    $oh = "false";
    $alert = "false";
}

if ($StartDate != "" && $StartTime == "") {
    $StartTime = "00:00";
}

if ($EndDate != "" && $EndTime == "") {
    $EndTime = "00:00";
}

if ($StartDate != "") {
    $dateTime = DateTime::createFromFormat('m/d/Y H:i', "$StartDate $StartTime");
    if ($dateTime !== false) {
        $start = $dateTime->getTimestamp();
    } else {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "Invalid date format: $StartDate $StartTime"
        );
    }
}
if ($EndDate != "") {
    $dateTime = DateTime::createFromFormat('m/d/Y H:i', "$EndDate $EndTime");
    if ($dateTime !== false) {
        $end = $dateTime->getTimestamp();
    } else {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            "Invalid date format: $EndDate $EndTime"
        );
    }
}

// Setting the startDate/Time using the user's chosen period
$period = 86400;
if ($auto_period > 0 || $start === 0) {
    $period = $auto_period;
    $start = time() - $period;
    $end = time();
}

$general_opt = getStatusColor($pearDB);

$tab_color_service = [
    STATUS_OK => 'service_ok',
    STATUS_WARNING => 'service_warning',
    STATUS_CRITICAL => 'service_critical',
    STATUS_UNKNOWN => 'service_unknown',
    STATUS_ACKNOWLEDGEMENT => 'service_acknowledgement',
    STATUS_PENDING => 'pending'
];
$tab_color_host = [
    STATUS_UP => 'host_up',
    STATUS_DOWN => 'host_down',
    STATUS_UNREACHABLE => 'host_unreachable'
];

$tab_type = ["1" => "HARD", "0" => "SOFT"];
$tab_class = ["0" => "list_one", "1" => "list_two"];
$tab_status_host = ["0" => "UP", "1" => "DOWN", "2" => "UNREACHABLE"];
$tab_status_service = ["0" => "OK", "1" => "WARNING", "2" => "CRITICAL", "3" => "UNKNOWN", "5" => "ACKNOWLEDGEMENT"];
$acknowlegementMessageType = [
    'badgeColor' => 'ack',
    'badgeText' => 'ACK'
];

/*
 * Create IP Cache
 */
if ($export) {
    $HostCache = [];

    try {
        $dbResult = $pearDB->executeQuery("SELECT host_name, host_address FROM host WHERE host_register = '1'");
        while ($h = $pearDB->fetch($dbResult)) {
            $HostCache[$h["host_name"]] = $h["host_address"];
        }
        $pearDB->closeQuery($dbResult);
    } catch (CentreonDbException $e) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Error while fetching hosts',
            $e->getOptions()
        );
    }
}

$logs = [];

/*
 * Print infos..
 */
$buffer->startElement("infos");
$buffer->writeElement("opid", $openid);
$buffer->writeElement("start", $start);
$buffer->writeElement("end", $end);
$buffer->writeElement("notification", $notification);
$buffer->writeElement("alert", $alert);
$buffer->writeElement("error", $error);
$buffer->writeElement("up", $up);
$buffer->writeElement("down", $down);
$buffer->writeElement("unreachable", $unreachable);
$buffer->writeElement("ok", $ok);
$buffer->writeElement("warning", $warning);
$buffer->writeElement("critical", $critical);
$buffer->writeElement("unknown", $unknown);
$buffer->writeElement("acknowledgement", $acknowledgement);
$buffer->writeElement("oh", $oh);
$buffer->writeElement("search_H", $search_H);
$buffer->writeElement("search_S", $search_S);
$buffer->endElement();

// Build message type and status conditions
$msg_type_set = [];
if ($alert == 'true') {
    array_push($msg_type_set, 0, 1);
}
if ($notification == 'true') {
    array_push($msg_type_set, 2, 3);
}
if ($error == 'true') {
    array_push($msg_type_set, 4);
}

$host_msg_status_set = [];
if ($up == 'true') {
    $host_msg_status_set[] = STATUS_UP;
}
if ($down == 'true') {
    $host_msg_status_set[] = STATUS_DOWN;
}
if ($unreachable == 'true') {
    $host_msg_status_set[] = STATUS_UNREACHABLE;
}

$svc_msg_status_set = [];
if ($ok == 'true') {
    $svc_msg_status_set[] = STATUS_OK;
}
if ($warning == 'true') {
    $svc_msg_status_set[] = STATUS_WARNING;
}
if ($critical == 'true') {
    $svc_msg_status_set[] = STATUS_CRITICAL;
}
if ($unknown == 'true') {
    $svc_msg_status_set[] = STATUS_UNKNOWN;
}
if ($acknowledgement == 'true') {
    $svc_msg_status_set[] = STATUS_ACKNOWLEDGEMENT;
}

$whereClauses = [];
$queryValues = [];

// Time range conditions
$whereClauses[] = 'logs.ctime > :startTime';
$queryValues[':startTime'] = [$start, \PDO::PARAM_INT];
$whereClauses[] = 'logs.ctime <= :endTime';
$queryValues[':endTime'] = [$end, \PDO::PARAM_INT];

// Output filter
if (!empty($output)) {
    $whereClauses[] = 'logs.output LIKE :output';
    $queryValues[':output'] = ['%' . $output . '%', \PDO::PARAM_STR];
}

// Message type and status conditions
$msgConditions = [];

if ($notification == 'true') {
    if (!empty($host_msg_status_set)) {
        [$bindValues, $bindQuery] = createMultipleBindQuery($host_msg_status_set, ':host_msg_status_set_', \PDO::PARAM_INT);
        $msgConditions[] = "(logs.msg_type = 3 AND logs.status IN ($bindQuery))";
        $queryValues = array_merge($queryValues, $bindValues);
    }
    if (!empty($svc_msg_status_set)) {
        [$bindValues, $bindQuery] = createMultipleBindQuery($svc_msg_status_set, ':svc_msg_status_set_', \PDO::PARAM_INT);
        $msgConditions[] = "(logs.msg_type = 2 AND logs.status IN ($bindQuery))";
        $queryValues = array_merge($queryValues, $bindValues);
    }
}
if ($alert == 'true') {
    $alertConditions = [];
    $alertMsgTypesHost = [1, 10, 11];
    $alertMsgTypesSvc = [0, 10, 11];

    if (!empty($host_msg_status_set)) {
        [$bindValuesHost, $bindQueryHost] = createMultipleBindQuery($host_msg_status_set, ':host_msg_status_set_', \PDO::PARAM_INT);
        [$bindValuesAlert, $bindQueryAlert] = createMultipleBindQuery($alertMsgTypesHost, ':alertMsgTypesHost_', \PDO::PARAM_INT);
        $alertConditions[] = "(logs.msg_type IN ($bindQueryAlert) AND logs.status IN ($bindQueryHost))";
        $queryValues = array_merge($queryValues, $bindValuesHost, $bindValuesAlert);
    }
    if (!empty($svc_msg_status_set)) {
        [$bindValuesSvc, $bindQuerySvc] = createMultipleBindQuery($svc_msg_status_set, ':svc_msg_status_set_', \PDO::PARAM_INT);
        [$bindValuesAlert, $bindQueryAlert] = createMultipleBindQuery($alertMsgTypesSvc, ':alertMsgTypesSvc_', \PDO::PARAM_INT);
        $alertConditions[] = "(logs.msg_type IN ($bindQueryAlert) AND logs.status IN ($bindQuerySvc))";
        $queryValues = array_merge($queryValues, $bindValuesSvc, $bindValuesAlert);
    }
    if ($oh == 'true') {
        // Apply 'logs.type = :logType' only to alert conditions with $oh = true
        $whereClauses[] = 'logs.type = :logType';
        $queryValues[':logType'] = [TYPE_HARD, \PDO::PARAM_INT];
    }
    // Add alert conditions to msgConditions
    $msgConditions = array_merge($msgConditions, $alertConditions);
}

if ($error == 'true') {
    $msgConditions[] = 'logs.msg_type IN (4, 5)';
}
if (!empty($msgConditions)) {
    $whereClauses[] = '(' . implode(' OR ', $msgConditions) . ')';
}

// Host and service filters
$hostServiceConditions = [];
// Check if the filter is on services
$service_filter = str_contains($openid, 'HS');
$tab_id = explode(',', $openid);
$tab_host_ids = [];
$tab_svc = [];
foreach ($tab_id as $openidItem) {
    $tab_tmp = explode('_', $openidItem);
    $id = $tab_tmp[2] ?? $tab_tmp[1] ?? '';
    $hostId = !empty($tab_tmp[2]) ? $tab_tmp[1] : '';
    if ($id == "") {
        continue;
    }

    $type = $tab_tmp[0];
    if (!$service_filter && $type == "HG" && (isset($lca["LcaHostGroup"][$id]) || $is_admin)) {
        // Get hosts from host groups
        $hosts = getMyHostGroupHosts($id);
        foreach ($hosts as $h_id) {
            if (isset($lca["LcaHost"][$h_id])) {
                $tab_host_ids[] = $h_id;
                $tab_svc[$h_id] = $lca["LcaHost"][$h_id];
            }
        }
    } elseif (!$service_filter && $type == 'SG' && (isset($lca["LcaSG"][$id]) || $is_admin)) {
        // Get services from service groups
        $services = getMyServiceGroupServices($id);
        foreach ($services as $svc_id => $svc_name) {
            $svc_parts = explode('_', $svc_id);
            $tmp_host_id = $svc_parts[0];
            $tmp_service_id = $svc_parts[1];
            if (isset($lca["LcaHost"][$tmp_host_id][$tmp_service_id])) {
                $tab_svc[$tmp_host_id][$tmp_service_id] = $lca["LcaHost"][$tmp_host_id][$tmp_service_id];
            }
        }
    } elseif (!$service_filter && $type == "HH" && isset($lca["LcaHost"][$id])) {
        $tab_host_ids[] = $id;
        $tab_svc[$id] = $lca["LcaHost"][$id];
    } elseif ($type == "HS" && isset($lca["LcaHost"][$hostId][$id])) {
        $tab_svc[$hostId][$id] = $lca["LcaHost"][$hostId][$id];
    } elseif ($type == "MS") {
        $tab_svc["_Module_Meta"][$id] = "meta_" . $id;
    }
}

if (in_array('true', [$up, $down, $unreachable, $ok, $warning, $critical, $unknown, $acknowledgement])) {

    if (!empty($tab_host_ids)) {
        [$bindValues, $bindQuery] = createMultipleBindQuery($tab_host_ids, ':tab_host_ids_', \PDO::PARAM_INT);
        $hostServiceConditions[] = "(logs.host_id IN ($bindQuery) AND (logs.service_id IS NULL OR logs.service_id = 0))";
        $queryValues = array_merge($queryValues, $bindValues);
    }

    if (!empty($tab_svc)) {
        $serviceConditions = [];
        foreach ($tab_svc as $hostIndex => $services) {
            $hostParam = ':hostId' . $hostIndex;
            $queryValues[$hostParam] = [$hostIndex, \PDO::PARAM_INT];

            $servicePlaceholders = [];
            foreach ($services as $svcIndex => $svcId) {
                $paramName = ':serviceId' . $hostIndex . '_' . $svcIndex;
                $servicePlaceholders[] = $paramName;
                $queryValues[$paramName] = [$svcIndex, \PDO::PARAM_INT];
            }
            $servicePlaceholdersString = implode(', ', $servicePlaceholders);
            $serviceConditions[] = "(logs.host_id = $hostParam AND logs.service_id IN ($servicePlaceholdersString))";
        }
        if (!empty($serviceConditions)) {
            $hostServiceConditions[] = '(' . implode(' OR ', $serviceConditions) . ')';
        }
    }

    if (!empty($hostServiceConditions)) {
        $whereClauses[] = '(' . implode(' OR ', $hostServiceConditions) . ')';
    }
}

// Exclude BAM modules if necessary
if ($engine == "false" && empty($tab_host_ids) && empty($tab_svc)) {
    $whereClauses[] = "logs.msg_type NOT IN (4, 5)";
    $whereClauses[] = "logs.host_name NOT LIKE '_Module_BAM%'";
}

// Apply host and service search filters
if (!empty($search_host)) {
    $whereClauses[] = 'logs.host_name LIKE :searchHost';
    $queryValues[':searchHost'] = ['%' . $search_host . '%', \PDO::PARAM_STR];
}
if (!empty($search_service)) {
    $whereClauses[] = 'logs.service_description LIKE :searchService';
    $queryValues[':searchService'] = ['%' . $search_service . '%', \PDO::PARAM_STR];
}

// Build the select fields without including SQL_CALC_FOUND_ROWS and DISTINCT
$selectFields = [
    "1 AS REALTIME",
    "logs.ctime",
    "logs.host_id",
    "logs.host_name",
    "logs.service_id",
    "logs.service_description",
    "logs.msg_type",
    "logs.notification_cmd",
    "logs.notification_contact",
    "logs.output",
    "logs.retry",
    "logs.status",
    "logs.type",
    "logs.instance_name"
];

// Start building the SELECT clause
$selectClause = "SELECT ";

// Add DISTINCT if the user is not an admin
if (!$is_admin) {
    $selectClause .= "DISTINCT ";
}

// Add the select fields
$selectClause .= implode(", ", $selectFields);

$fromClause = "FROM logs";

$joinClauses = [];
if ($engine == "true" && !empty($openid)) {
    $pollerIds = array_filter(explode(',', $openid), 'is_numeric');
    if (!empty($pollerIds)) {
        [$bindValues, $bindQuery] = createMultipleBindQuery($pollerIds, ':pollerIds_', \PDO::PARAM_INT);
        $joinClauses[] = "
        INNER JOIN instances i ON i.name = logs.instance_name
        AND i.instance_id IN ($bindQuery)
        ";
        $queryValues = array_merge($queryValues, $bindValues);
    }
    if ($str_unitH != "") {
        $str_unitH = "(logs.host_id IN ($str_unitH) AND (logs.service_id IS NULL OR logs.service_id = 0))";
        if (isset($search_host) && $search_host != "") {
            $host_search_sql = " AND logs.host_name LIKE '%" . $pearDBO->escapeString($search_host) . "%' ";
        }
    }
}

if (!$is_admin) {
    $joinClauses[] = "
        INNER JOIN centreon_acl acl ON (
            logs.host_id = acl.host_id
            AND (acl.service_id IS NULL OR acl.service_id = logs.service_id)
        )
    ";
}

$whereClause = "WHERE " . implode(' AND ', $whereClauses);
$orderClause = "ORDER BY logs.ctime DESC";

$limitClause = '';
if (!$export) {
    $queryValues[':offset'] = [$num, \PDO::PARAM_INT];
    $queryValues[':limit'] = [$limit, \PDO::PARAM_INT];
    $limitClause = 'LIMIT :limit OFFSET :offset';
}

$sqlQuery = "
    $selectClause
    $fromClause
    " . implode(' ', $joinClauses) . "
    $whereClause
    $orderClause
    $limitClause
";

$countQuery = "
    SELECT COUNT(*)
    $fromClause
    " . implode(' ', $joinClauses) . "
    $whereClause
";

$paginator = new Paginator((int)$num, (int)$limit);
try {
    // Execute the count query
    $countqueryValues = $queryValues;
    unset($countqueryValues[':limit'], $countqueryValues[':offset']);
    $countStatement = $pearDBO->prepareQuery($countQuery);
    $pearDBO->executePreparedQuery($countStatement, $countqueryValues, true);
    $totalRows = $pearDBO->fetchColumn($countStatement);
    $paginator = $paginator->withTotalRecordCount((int)$totalRows);
    $pearDBO->closeQuery($countStatement);

    // Prepare and execute the query using CentreonDB methods
    $statement = $pearDBO->prepareQuery($sqlQuery);
    $pearDBO->executePreparedQuery($statement, $queryValues, true);
    $rows = $statement->rowCount();

    // If the current page is out of bounds, adjust it
    if (!$export && 0 === $rows && $paginator->isOutOfUpperBound()) {
        // Update the offset in both $queryValues and $flatQueryValues
        $newOffset = $paginator->getOffsetMaximum();
        $queryValues[':offset'] = [$newOffset, \PDO::PARAM_INT];

        // Re-prepare and execute the query with the updated offset
        $statement = $pearDBO->prepareQuery($sqlQuery);
        $pearDBO->executePreparedQuery($statement, $queryValues, true);
    }

    $logs = $pearDBO->fetchAll($statement);
    $pearDBO->closeQuery($statement);
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_BUSINESS_LOG,
        'Error while fetching logs',
        $e->getOptions()
    );
}

// Render XML output
$buffer->startElement("selectLimit");
foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90, 100] as $i) {
    $buffer->writeElement("limitValue", $i);
}
$buffer->writeElement("limit", $limit);
$buffer->endElement();

// add generated pages into xml
$paginationRenderer = new PaginationRenderer($buffer);
$paginationRenderer->render($paginator);

// Display logs
$cpts = 0;
// The query retrieves more than $limit results, but only the first $limit elements should be displayed
foreach (array_slice($logs, 0, $limit) as $log) {
    $buffer->startElement("line");
    $buffer->writeElement("msg_type", $log["msg_type"]);

    /**
     * For an ACK there is no point to display RETRY and TYPE columns
     */
    $displayType = '';
    if (
        $log['msg_type'] != HOST_ACKNOWLEDGEMENT_MSG_TYPE
        && $log['msg_type'] != SERVICE_ACKNOWLEDGEMENT_MSG_TYPE
    ) {
        $displayType = $log['type'];

        if (isset($tab_type[$log['type']])) {
            $displayType = $tab_type[$log['type']];
        }
        $log["msg_type"] > 1 ? $buffer->writeElement("retry", "") : $buffer->writeElement("retry", $log["retry"]);
        $log["msg_type"] == 2 || $log["msg_type"] == 3
            ? $buffer->writeElement("type", "NOTIF")
            : $buffer->writeElement("type", $displayType);
    }

    /*
        * Color initialisation for services and hosts status
        * For ACK message types, display a badge 'ACK' in Yellow
        */
    $color = '';
    if (
        $log['msg_type'] == HOST_ACKNOWLEDGEMENT_MSG_TYPE
        || $log['msg_type'] == SERVICE_ACKNOWLEDGEMENT_MSG_TYPE
    ) {
        $color = $acknowlegementMessageType['badgeColor'];
    } elseif (isset($log["status"])) {
        if (
            isset($tab_color_service[$log["status"]])
            && !empty($log["service_description"])
        ) {
            $color = $tab_color_service[$log["status"]];
        } elseif (isset($tab_color_host[$log["status"]])) {
            $color = $tab_color_host[$log["status"]];
        }
    }

    /*
        * Variable initialisation to color "INITIAL STATE" on event logs
        */
    if ($log["output"] == "" && $log["status"] != "") {
        $log["output"] = "INITIAL STATE";
    }

    $buffer->startElement("status");
    $buffer->writeAttribute("color", $color);
    $displayStatus = $log["status"];
    if (
        $log['msg_type'] == HOST_ACKNOWLEDGEMENT_MSG_TYPE
        || $log['msg_type'] == SERVICE_ACKNOWLEDGEMENT_MSG_TYPE
    ) {
        $displayStatus = $acknowlegementMessageType['badgeText'];
    } elseif ($log['service_description'] && isset($tab_status_service[$log['status']])) {
        $displayStatus = $tab_status_service[$log['status']];
    } elseif (isset($tab_status_host[$log['status']])) {
        $displayStatus = $tab_status_host[$log['status']];
    }
    $buffer->text($displayStatus);
    $buffer->endElement();

    if (!strncmp($log["host_name"], "_Module_Meta", strlen("_Module_Meta"))) {
        if (preg_match('/meta_([0-9]*)/', $log["service_description"], $matches)) {
            try {
                $statement = $pearDB->prepareQuery(
                    <<<SQL
                        SELECT meta_name
                        FROM meta_service
                        WHERE meta_id = :meta_id
                    SQL
                );
                $pearDB->executePreparedQuery($statement, [':meta_id' => [$matches[1], \PDO::PARAM_INT]], true);
                $meta = $pearDB->fetch($statement);
                $pearDB->closeQuery($statement);

                $buffer->writeElement("host_name", "Meta", false);
                $buffer->writeElement("real_service_name", $log["service_description"], false);
                $buffer->writeElement("service_description", $meta["meta_name"], false);
                unset($meta);
            } catch (CentreonDbException $e) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_BUSINESS_LOG,
                    'Error while fetching meta_services',
                    $e->getOptions()
                );
            }
        } else {
            // Log case where meta pattern is not found in service description
            CentreonLog::create()->info(
                CentreonLog::TYPE_BUSINESS_LOG,
                "No meta pattern found in service_description: " . $log["service_description"]
            );

            // Default output when meta pattern is missing
            $buffer->writeElement("host_name", $log["host_name"], false);
            if ($export) {
                $buffer->writeElement("address", $HostCache[$log["host_name"]], false);
            }
            $buffer->writeElement("service_description", $log["service_description"], false);
            $buffer->writeElement("real_service_name", $log["service_description"], false);
        }
    } else {
        $buffer->writeElement("host_name", $log["host_name"], false);
        if ($export) {
            $buffer->writeElement("address", $HostCache[$log["host_name"]], false);
        }
        $buffer->writeElement("service_description", $log["service_description"], false);
        $buffer->writeElement("real_service_name", $log["service_description"], false);

        $serviceTimelineRedirectionUri = $useDeprecatedPages
            ? 'main.php?p=20201&amp;o=svcd&amp;host_name=' . $log['host_name'] . '&amp;service_description='
            . $log['service_description']
            : $resourceController->buildServiceUri(
                $log['host_id'],
                $log['service_id'],
                $resourceController::TAB_TIMELINE_NAME
            );

        $buffer->writeElement(
            "s_timeline_uri",
            $serviceTimelineRedirectionUri
        );
    }
    $buffer->writeElement("real_name", $log["host_name"], false);

    $hostTimelineRedirectionUri = $useDeprecatedPages
        ? 'main.php?p=20202&amp;o=hd&amp;host_name=' . $log['host_name']
        : $resourceController->buildHostUri($log['host_id'], $resourceController::TAB_TIMELINE_NAME);

    $buffer->writeElement(
        "h_timeline_uri",
        $hostTimelineRedirectionUri
    );
    $buffer->writeElement("class", $tab_class[$cpts % 2]);
    $buffer->writeElement("poller", $log["instance_name"]);
    $buffer->writeElement("date", $log["ctime"]);
    $buffer->writeElement("time", $log["ctime"]);
    $buffer->writeElement("output", $log["output"]);
    $buffer->writeElement("contact", $log["notification_contact"], false);
    $buffer->writeElement("contact_cmd", $log["notification_cmd"], false);
    $buffer->endElement();
    $cpts++;
}

/*
 * Translation for tables.
 */
$buffer->startElement("lang");
$buffer->writeElement("d", _("Day"), 0);
$buffer->writeElement("t", _("Time"), 0);
$buffer->writeElement("O", _("Object name"), 0);
$buffer->writeElement("T", _("Type"), 0);
$buffer->writeElement("R", _("Retry"), 0);
$buffer->writeElement("o", _("Output"), 0);
$buffer->writeElement("c", _("Contact"), 0);
$buffer->writeElement("C", _("Command"), 0);
$buffer->writeElement("P", _("Poller"), 0);

$buffer->endElement();
$buffer->endElement();


/*
 * XML tag
 */
stristr($_SERVER["HTTP_ACCEPT"], "application/xhtml+xml") ?
    header("Content-type: application/xhtml+xml") : header("Content-type: text/xml");
header('Content-Disposition: attachment; filename="eventLogs-' . time() . '.xml"');

$buffer->output();
