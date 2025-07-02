<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

ini_set('display_errors', 'Off');

// Include configuration
require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');

// Include Classes / Methods
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';

// Connect MySQL DB
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');
$pearDBO->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

// Security check
CentreonSession::start(1);
$sessionId = session_id();
if (! CentreonSession::checkSession((string) $sessionId, $pearDB)) {
    echo 'Bad Session';

    exit();
}

$centreon = $_SESSION['centreon'];

// Language informations init
$locale = $centreon->user->get_lang();
putenv("LANG={$locale}");
setlocale(LC_ALL, $locale);
bindtextdomain('messages', _CENTREON_PATH_ . '/www/locale/');
bind_textdomain_codeset('messages', 'UTF-8');
textdomain('messages');

$sid = $sessionId === false ? '-1' : $sessionId;
$contact_id = check_session($sid, $pearDB); // @phpstan-ignore-line

$is_admin = isUserAdmin($sid); // @phpstan-ignore-line
$access = new CentreonACL($contact_id, $is_admin);
$lca = [
    'LcaHost' => $access->getHostsServices($pearDBO, true),
    'LcaHostGroup' => $access->getHostGroups(),
    'LcaSG' => $access->getServiceGroups(),
];

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Request.php');
$requestHandler = new Centreon\Legacy\EventLogs\Export\Request();
$requestHandler->setIsAdmin($is_admin);
$requestHandler->setLca($lca);

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . 'QueryGenerator.php');
$queryGenerator = new Centreon\Legacy\EventLogs\Export\QueryGenerator($pearDBO);
$queryGenerator->setIsAdmin($is_admin);
$queryGenerator->setEngine($requestHandler->getEngine());
$queryGenerator->setOpenid($requestHandler->getOpenid());
$queryGenerator->setOutput($requestHandler->getOutput());
$queryGenerator->setAccess($access);
$queryGenerator->setStart($requestHandler->getStart());
$queryGenerator->setEnd($requestHandler->getEnd());
$queryGenerator->setUp($requestHandler->getUp());
$queryGenerator->setDown($requestHandler->getDown());
$queryGenerator->setUnreachable($requestHandler->getUnreachable());
$queryGenerator->setOk($requestHandler->getOk());
$queryGenerator->setWarning($requestHandler->getWarning());
$queryGenerator->setCritical($requestHandler->getCritical());
$queryGenerator->setUnreachable($requestHandler->getUnreachable());
$queryGenerator->setNotification($requestHandler->getNotification());
$queryGenerator->setAlert($requestHandler->getAlert());
$queryGenerator->setError($requestHandler->getError());
$queryGenerator->setOh($requestHandler->getOh());
$queryGenerator->setHostMsgStatusSet($requestHandler->getHostMsgStatusSet());
$queryGenerator->setSvcMsgStatusSet($requestHandler->getSvcMsgStatusSet());
$queryGenerator->setTabHostIds($requestHandler->getTabHostIds());
$queryGenerator->setSearchHost($requestHandler->getSearchHost());
$queryGenerator->setTabSvc($requestHandler->getTabSvc());
$queryGenerator->setSearchService($requestHandler->getSearchService());
$queryGenerator->setExport($requestHandler->getExport());
$queryGenerator->setAcknowledgement($requestHandler->getAcknowledgement());
$stmt = $queryGenerator->getStatement();
unset($queryGenerator);

$stmt->execute();

$HostCache = [];
$dbResult = $pearDB->query("SELECT host_name, host_address FROM host WHERE host_register = '1'");
if (! $dbResult instanceof PDOStatement) {
    throw new RuntimeException('An error occurred. Hosts could not be found');
}

while ($h = $dbResult->fetch()) {
    $HostCache[$h['host_name']] = $h['host_address'];
}
$dbResult->closeCursor();

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Formatter.php');
$formatter = new Centreon\Legacy\EventLogs\Export\Formatter();
$formatter->setHosts($HostCache);
$formatter->setStart($requestHandler->getStart());
$formatter->setEnd($requestHandler->getEnd());
$formatter->setNotification($requestHandler->getNotification());
$formatter->setAlert($requestHandler->getAlert());
$formatter->setError($requestHandler->getError());
$formatter->setUp($requestHandler->getUp());
$formatter->setDown($requestHandler->getDown());
$formatter->setUnreachable($requestHandler->getUnreachable());
$formatter->setOk($requestHandler->getOk());
$formatter->setWarning($requestHandler->getWarning());
$formatter->setCritical($requestHandler->getCritical());
$formatter->setUnknown($requestHandler->getUnknown());
$formatter->setAcknowledgement($requestHandler->getAcknowledgement());
$formattedLogs = $formatter->formatLogs($stmt);
$logHeads = $formatter->getLogHeads();
$metaData = $formatter->formatMetaData();
unset($formatter);

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Presenter.php');
$presenter = new Centreon\Legacy\EventLogs\Export\Presenter();
$presenter->setMetaData($metaData);
$presenter->setHeads($logHeads);
$presenter->setLogs($formattedLogs);
$presenter->render();
$stmt->closeCursor();
unset($presenter, $stmt);
