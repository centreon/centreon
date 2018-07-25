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

require_once dirname(__FILE__) . '/../../../../bootstrap.php';

error_reporting(E_ALL & ~E_STRICT);
ini_set('display_errors', false);

require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/class/Export.class.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/class/ClapiObject.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';
define('_CLAPI_LIB_', _CENTREON_PATH_ . "/lib");
define('_CLAPI_CLASS_', _CENTREON_PATH_ . "/www/class/centreon-clapi");

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(_CLAPI_LIB_),
    realpath(_CLAPI_CLASS_),
    get_include_path()
)));
require_once _CLAPI_CLASS_ . "/centreonUtils.class.php";
require_once _CLAPI_CLASS_ . "/centreonAPI.class.php";

$formValue = array(
    'export_cmd',
    'TP',
    'CONTACT',
    'CG',
    'export_HOST',
    'export_HTPL',
    'export_HG',
    'HC',
    'export_SERVICE',
    'export_STPL',
    'export_SG',
    'SC',
    'ACL',
    'LDAP',
    'export_INSTANCE'
);

$centreonSession = new CentreonSession();
$centreonSession->start();
$username = $_SESSION['centreon']->user->alias;
$clapiConnector = new \ClapiObject($dependencyInjector, array('username' => $username));

/*
* Set log_contact
*/
\CentreonClapi\CentreonUtils::setUserName($username);

$scriptContent = array();
$ajaxReturn = array();

$oExport = new \Export($clapiConnector, $dependencyInjector);

foreach ($_POST as $object => $value) {
    if (in_array($object, $formValue)) {
        $type = explode('_', $object);
        if ($type[0] == 'export') {
            $generateContent = $oExport->generateGroup($type[1], $value);
            if (!empty($generateContent)) {
                if (!empty($generateContent['error'])) {
                    $ajaxReturn['error'][] = $generateContent['error'];
                }
                if (!is_null($generateContent['result'])) {
                    $scriptContent[] = $generateContent['result'];
                }
            }
        } elseif ($type[0] != 'submitC') {
            $generateContent = $oExport->generateObject($type[0]);
            if (!empty($generateContent)) {
                if (!empty($generateContent['error'])) {
                    $ajaxReturn['error'][] = $generateContent['error'];
                }
                if (!is_null($generateContent['result'])) {
                    $scriptContent[] = $generateContent['result'];
                }
            }
        }
    } else {
        $ajaxReturn['error'][] = 'Unknown object : ' . $object;
    }
}

$ajaxReturn['fileGenerate'] = $oExport->clapiExport($scriptContent);
echo json_encode($ajaxReturn);
exit;
