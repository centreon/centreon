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

require_once __DIR__ . '/../../../../config/centreon.config.php';
require_once _CENTREON_PATH_ . '/bootstrap.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/class/ClapiObject.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/include/common/common-Func.php';

define('_CLAPI_LIB_', _CENTREON_PATH_ . '/lib');
define('_CLAPI_CLASS_', _CENTREON_PATH_ . '/www/class/centreon-clapi');

set_include_path(
    implode(
        PATH_SEPARATOR,
        [
            realpath(_CLAPI_LIB_),
            realpath(_CLAPI_CLASS_),
            get_include_path(),
        ]
    )
);
require_once _CLAPI_CLASS_ . '/centreonUtils.class.php';
require_once _CLAPI_CLASS_ . '/centreonAPI.class.php';

$pearDB = new CentreonDB();

$centreonSession = new CentreonSession();
$centreonSession->start();

// Exit if no session cookie
if (!isset($_COOKIE['PHPSESSID']) || empty($_COOKIE['PHPSESSID'])) {
    echo json_encode(['error' => 'Authentication required']);

    exit;
}

// Exit if invalid session
if (!isset($_SESSION['centreon']) || !isset($_SESSION['centreon']->user)) {
    echo json_encode(['error' => 'Invalid or expired session']);

    exit;
}

// Check CSRF token
if (!isset($_POST['centreon_token']) || !isCSRFTokenValid()) {
    echo json_encode(['error' => 'Invalid security token']);

    exit;
}
purgeCSRFToken();

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

$username = $_SESSION['centreon']->user->alias;
/** @var Pimple\Container $dependencyInjector */
$clapiConnector = new ClapiObject($dependencyInjector, ['username' => $username]);
$importReturn = [];

/**
 * Upload file
 */
if (! isset($_FILES['clapiImport'])) {
    $importReturn['error'] = 'File is empty';
    echo json_encode($importReturn);

    exit;
}

$uploadDir = _CENTREON_CACHEDIR_ . '/';
$uploadFile = $uploadDir . basename($_FILES['clapiImport']['name']);
$tmpLogFile = $uploadDir . 'log' . time() . '.htm';
if (! is_dir($uploadDir)) {
    mkdir($uploadDir);
}
$moveFile = move_uploaded_file($_FILES['clapiImport']['tmp_name'], $uploadFile);
if (! $moveFile) {
    $importReturn['error'] = 'Upload failed';
    echo json_encode($importReturn);

    exit;
}

/**
 * Unzip file
 */
$zip = new ZipArchive();
$confPath = _CENTREON_CACHEDIR_ . '/filesUpload/';

$openResult = $zip->open($uploadFile);
if ($openResult === true) {
    $zip->extractTo($confPath);
    $zip->close();
} elseif ($openResult !== 0 /** {@see ZipArchive::ER_OK} */) {
    $importReturn['error'] = 'Unzip failed';
    echo json_encode($importReturn);

    exit;
}

/**
 * Set log_contact
 */
CentreonClapi\CentreonUtils::setUserName($username);

/**
 * Using CLAPI command to import configuration
 * Exemple -> "./centreon -u admin -p centreon -i /tmp/clapi-export.txt"
 */
$finalFile = $confPath . basename($uploadFile, '.zip') . '.txt';

try {
    ob_start();
    $clapiConnector->import($finalFile);
    ob_end_clean();
    $importReturn['response'] = 'Import successful';
} catch (Exception $e) {
    $importReturn['error'] = $e->getMessage();
}
unlink($uploadFile);
unlink($finalFile);
echo json_encode($importReturn);

exit;
