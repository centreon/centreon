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

require_once __DIR__ . '/../../../../bootstrap.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/class/ZipAndDownload.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

$pearDB = new CentreonDB();

$centreonSession = new CentreonSession();
$centreonSession->start();

// Exit if no session cookie
if (! isset($_COOKIE['PHPSESSID']) || empty($_COOKIE['PHPSESSID'])) {
    echo json_encode(['error' => 'Authentication required']);

    exit;
}

// Exit if invalid session
if (! isset($_SESSION['centreon']) || ! isset($_SESSION['centreon']->user)) {
    echo json_encode(['error' => 'Invalid or expired session']);

    exit;
}

// Exit if user is not admin
if ((bool) $_SESSION['centreon']->user->admin !== true) {
    echo json_encode(['error' => 'Permission denied']);

    exit;
}

// Exit if user is a service account
if (isServiceAccount($_SESSION['centreon']->user->user_id)) {
    echo json_encode(['error' => 'Permission denied for service accounts']);

    exit;
}

// Only the generated export file can be downloaded
$fileName = $_POST['pathFile'] ?? null;
if (! is_string($fileName) || preg_match('/^centreon-clapi-export-\d+$/', $fileName) !== 1) {
    echo json_encode(['error' => 'Invalid export file']);

    exit;
}
$oExport = new ZipAndDownload($fileName);
