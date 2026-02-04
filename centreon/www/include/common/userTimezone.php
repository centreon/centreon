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

require_once realpath(__DIR__ . '/../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';

$pearDB = new CentreonDB();
$buffer = new CentreonXML();
$buffer->startElement('entry');

session_start();
session_write_close();

$sid = session_id();
$centreon = null;

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
    $currentTime = $centreon->CentreonGMT->getCurrentTime(time(), $centreon->user->getMyGMT());

    $stmt = $pearDB->prepare('SELECT user_id FROM session WHERE session_id = ?');
    $stmt->execute([$sid]);

    if ($stmt->rowCount()) {
        $buffer->writeElement('state', 'ok');
    } else {
        $buffer->writeElement('state', 'nok');
    }
} else {
    $currentTime = date_format(date_create(), '%c');
    $buffer->writeElement('state', 'nok');
}
$buffer->writeElement('time', $currentTime);
$buffer->writeElement(
    'timezone',
    $centreon !== null
    ? $centreon->CentreonGMT->getActiveTimezone($centreon->user->getMyGMT())
    : date_default_timezone_get()
);
$buffer->endElement();

header('Content-Type: text/xml');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');

$buffer->output();
