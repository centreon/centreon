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

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonExternalCommand.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonHost.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonService.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUtils.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . '/www/class/HtmlAnalyzer.php';

CentreonSession::start(1);
$centreon = $_SESSION['centreon'];
if (! isset($_SESSION['centreon'], $_POST['host_id'], $_POST['service_id'], $_POST['cmd'], $_POST['actiontype'])) {
    exit();
}

$pearDB = new CentreonDB();
$hostObj = new CentreonHost($pearDB);
$svcObj = new CentreonService($pearDB);

$hostId = filter_var(
    $_POST['host_id'] ?? false,
    FILTER_VALIDATE_INT
);

$serviceId = filter_var(
    $_POST['service_id'] ?? false,
    FILTER_VALIDATE_INT
);

$pollerId = $hostObj->getHostPollerId($hostId);

$cmd = HtmlAnalyzer::sanitizeAndRemoveTags($_POST['cmd'] ?? '');

$cmd = CentreonUtils::escapeSecure($cmd, CentreonUtils::ESCAPE_ILLEGAL_CHARS);

$actionType = (int) $_POST['actiontype'];

$pearDB = new CentreonDB();

if ($sessionId = session_id()) {
    $res = $pearDB->prepare('SELECT * FROM `session` WHERE `session_id` = :sid');
    $res->bindValue(':sid', $sessionId, PDO::PARAM_STR);
    $res->execute();
    if (! $session = $res->fetch(PDO::FETCH_ASSOC)) {
        exit();
    }
} else {
    exit();
}

/* If admin variable equals 1 it means that user admin
 * otherwise it means that it is a simple user under ACL
 */
$isAdmin = (int) $centreon->user->access->admin;
if ($centreon->user->access->admin === 0) {
    if (! $centreon->user->access->checkAction($cmd)) {
        exit();
    }
    if (! $centreon->user->access->checkHost($hostId)) {
        exit();
    }
    if (! $centreon->user->access->checkService($serviceId)) {
        exit();
    }
}

$command = new CentreonExternalCommand($centreon);
$commandList = $command->getExternalCommandList();

$sendCommand = $commandList[$cmd][$actionType];

$sendCommand .= ';' . $hostObj->getHostName($hostId) . ';' . $svcObj->getServiceDesc($serviceId) . ';' . time();
$command->setProcessCommand($sendCommand, $pollerId);
$returnType = $actionType ? 1 : 0;
$result = $command->write();
$buffer = new CentreonXML();
$buffer->startElement('root');
$buffer->writeElement('result', $result);
$buffer->writeElement('cmd', $cmd);
$buffer->writeElement('actiontype', $returnType);
$buffer->endElement();
header('Content-type: text/xml; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$buffer->output();
