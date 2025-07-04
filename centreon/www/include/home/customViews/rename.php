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

require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonWidget.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/HtmlAnalyzer.php';

session_start();
session_write_close();

if (! isset($_SESSION['centreon'])) {
    exit();
}

$elementId = filter_input(
    INPUT_POST,
    'elementId',
    FILTER_VALIDATE_REGEXP,
    [
        'options' => ['regexp' => '/^title_\d+$/'],
    ]
);

if ($elementId === null) {
    echo 'missing elementId argument';

    exit();
}
if ($elementId === false) {
    echo 'elementId must use following regexp format : "title_\d+"';

    exit();
}

$widgetId = null;
if (preg_match('/^title_(\d+)$/', $_POST['elementId'], $matches)) {
    $widgetId = $matches[1];
}

$newName = isset($_POST['newName']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['newName']) : null;
if ($newName === null) {
    echo 'missing newName argument';

    exit();
}

$centreon = $_SESSION['centreon'];
$db = new CentreonDB();

if (CentreonSession::checkSession(session_id(), $db) === false) {
    exit();
}

$widgetObj = new CentreonWidget($centreon, $db);
try {
    echo $widgetObj->rename($widgetId, htmlspecialchars($newName, ENT_QUOTES, 'UTF-8'));
} catch (CentreonWidgetException $e) {
    echo $e->getMessage();
}
