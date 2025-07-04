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

if (! isset($_POST['poller'])) {
    exit();
}

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonInstance.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';

$db = new CentreonDB();

// Check Session
CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $db)) {
    echo 'Bad Session';

    exit();
}

$pollers = explode(',', $_POST['poller']);

$xml = new CentreonXML();
$kernel = App\Kernel::createForWeb();
$gorgoneService = $kernel->getContainer()->get(Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface::class);

$res = $db->query("SELECT `id` FROM `nagios_server` WHERE `localhost` = '1'");
$idCentral = (int) $res->fetch(PDO::FETCH_COLUMN);

$res = $db->query("SELECT `name`, `id`, `localhost` 
    FROM `nagios_server` 
    WHERE `ns_activate` = '1' 
    ORDER BY `name` ASC");
$xml->startElement('response');
$str = sprintf('<br/><b>%s</b><br/>', _('Post execution command results'));
$ok = true;
$instanceObj = new CentreonInstance($db);

while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    if ($pollers == 0 || in_array($row['id'], $pollers)) {
        $commands = $instanceObj->getCommandData($row['id']);
        if (! count($commands)) {
            continue;
        }
        $str .= "<br/><strong>{$row['name']}</strong><br/>";
        foreach ($commands as $command) {
            $requestData = json_encode(
                [
                    [
                        'command' => $command['command_line'],
                        'timeout' => 30,
                        'continue_on_error' => true,
                    ],
                ]
            );
            $gorgoneCommand = new Centreon\Domain\Gorgone\Command\Command($idCentral, $requestData);
            $gorgoneResponse = $gorgoneService->send($gorgoneCommand);
            $str .= _('Executing command') . ': ' . $command['command_name'] . '<br/>';
        }
    }
}
$statusStr = $ok === false ? "<b><font color='red'>NOK</font></b>" : "<b><font color='green'>OK</font></b>";

$xml->writeElement('result', $str);
$xml->writeElement('status', $statusStr);
$xml->endElement();
header('Content-Type: application/xml');
header('Cache-Control: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');
$xml->output();
