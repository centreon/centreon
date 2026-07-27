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

if (! isset($_POST['poller']) || ! is_numeric($_POST['poller'])) {
    exit();
}

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once realpath(__DIR__ . '/../../../../../config/bootstrap.php');
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';

$pearDB = new CentreonDB();
$xml = new CentreonXML();

CentreonSession::start(1);
if (! CentreonSession::checkSession(session_id(), $pearDB)) {
    echo 'Bad Session';

    exit();
}
$centreon = $_SESSION['centreon'];

if (
    (! $centreon->user->admin && $centreon->user->access->checkAction('generate_trap') === 0)
    || ! isCSRFTokenValid()
) {
    exit();
}

$pollerId = (int) $_POST['poller'];

// Restrict to pollers the user is allowed to see
$allowedPollers = $centreon->user->access->getPollerAclConf([
    'get_row' => 'name',
    'keys' => ['id'],
    'conditions' => ['ns_activate' => 1],
]);

header('Content-Type: application/xml');
header('Cache-Control: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');

if (! isset($allowedPollers[$pollerId])) {
    $xml->startElement('response');
    $xml->writeElement('status', _('NOK'));
    $xml->writeElement('statuscode', 1);
    $xml->writeElement('error', _('Poller not allowed'));
    $xml->endElement();
    $xml->output();

    exit();
}

// Trap databases are generated centrally: find the central server's
// configured storage path (same behaviour as the legacy synchronous form).
$trapdPath = '/etc/snmp/centreon_traps/';
$result = $pearDB->query(
    "SELECT `snmp_trapd_path_conf` FROM `nagios_server` WHERE `localhost` = '1' AND `ns_activate` = '1' LIMIT 1"
);
if ($row = $result->fetchRow()) {
    if (! empty($row['snmp_trapd_path_conf']) && ! str_contains($row['snmp_trapd_path_conf'], '..')) {
        $trapdPath = $row['snmp_trapd_path_conf'];
    }
}

if (! is_dir($trapdPath . '/' . $pollerId)) {
    mkdir($trapdPath . '/' . $pollerId, 0755, true);
}
$filename = $trapdPath . '/' . $pollerId . '/centreontrapd.sdb';

$output = [];
$returnVal = 0;
exec(
    escapeshellcmd(_CENTREON_PATH_ . '/bin/generateSqlLite')
        . ' ' . escapeshellarg((string) $pollerId)
        . ' ' . escapeshellarg($filename)
        . ' 2>&1',
    $output,
    $returnVal
);

$xml->startElement('response');
if ($returnVal === 0) {
    $xml->writeElement('status', _('OK'));
    $xml->writeElement('statuscode', 0);
} else {
    $xml->writeElement('status', _('NOK'));
    $xml->writeElement('statuscode', 1);
    $xml->writeElement('error', _('Trap database generation failed'));
}
$xml->writeElement('debug', implode(' | ', $output));
$xml->endElement();

$xml->output();
