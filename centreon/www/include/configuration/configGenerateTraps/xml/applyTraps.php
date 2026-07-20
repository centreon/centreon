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

$xml->startElement('response');

if (! isset($allowedPollers[$pollerId])) {
    $xml->writeElement('status', _('NOK'));
    $xml->writeElement('statuscode', 1);
    $xml->writeElement('error', _('Poller not allowed'));
    $xml->endElement();
    $xml->output();

    exit();
}

$centcoreDirectory = defined('_CENTREON_VARLIB_') ? _CENTREON_VARLIB_ : '/var/lib/centreon';
$centcorePipe = is_dir($centcoreDirectory . '/centcore')
    ? $centcoreDirectory . '/centcore/' . microtime(true) . '-externalcommand.cmd'
    : $centcoreDirectory . '/centcore.cmd';

if ($fh = @fopen($centcorePipe, 'a+')) {
    fwrite($fh, 'SYNCTRAP:' . $pollerId . "\n");
    fclose($fh);
    $xml->writeElement('status', _('OK'));
    $xml->writeElement('statuscode', 0);
} else {
    $xml->writeElement('status', _('NOK'));
    $xml->writeElement('statuscode', 1);
    $xml->writeElement('error', _('Could not write into centcore.cmd. Please check file permissions.'));
}
$xml->endElement();

$xml->output();
