<?php
/**
 * Copyright 2018 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once dirname(__FILE__) . '/../../../../config/centreon.config.php';
require_once _CENTREON_PATH_ . 'bootstrap.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/class/ClapiObject.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';

define('_CLAPI_LIB_', _CENTREON_PATH_ . "/lib");
define('_CLAPI_CLASS_', _CENTREON_PATH_ . "/www/class/centreon-clapi");

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(_CLAPI_LIB_),
    realpath(_CLAPI_CLASS_),
    get_include_path()
)));
require_once _CLAPI_CLASS_ . "/centreonUtils.class.php";
require_once _CLAPI_CLASS_ . "/centreonAPI.class.php";


$centreonSession = new CentreonSession();
$centreonSession->start();
$username = $_SESSION['centreon']->user->alias;
$clapiConnector = new \ClapiObject($dependencyInjector, array('username' => $username));
$importReturn = array();

$uploadDir = '/usr/share/centreon/filesUpload/';
$uploadFile = $uploadDir . basename($_FILES['clapiImport']['name']);
$tmpLogFile = $uploadDir . 'log' . time() . '.htm';


/**
 * Upload file
 */

if (is_null($_FILES['clapiImport'])) {
    $importReturn['error'] = "File is empty";
    echo json_encode($importReturn);
    exit;
}

$moveFile = move_uploaded_file($_FILES['clapiImport']['tmp_name'], $uploadFile);
if (!$moveFile) {
    $importReturn['error'] = "Upload failed";
    echo json_encode($importReturn);
    exit;
}


/**
 * Dezippe file
 */
$zip = new ZipArchive;
$confPath = '/usr/share/centreon/filesUpload/';

if ($zip->open($uploadFile) === true) {
    $zip->extractTo($confPath);
    $zip->close();
} else {
    if ($zip->open($uploadFile) === false) {
        $importReturn['error'] = "Unzip failed";
        echo json_encode($importReturn);
        exit;
    }
}

/**
 * Set log_contact
 */
\CentreonClapi\CentreonUtils::setUserName($username);

/**
 * Using CLAPI command to import configuration
 * Exemple -> "./centreon -u admin -p centreon -i /tmp/clapi-export.txt"
 */
$finalFile = $confPath . basename($uploadFile, '.zip') . '.txt';

try {
    ob_start();
    $clapiConnector->import($finalFile, $tmpLogFile);
    ob_end_clean();
    $importReturn['response'] = 'Import successful';
} catch (\Exception $e) {
    $importReturn['error'] = $e->getMessage();
}

echo json_encode($importReturn);
exit;
