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
require_once _CLAPI_LIB_ . "/Centreon/Db/Manager/Manager.php";
require_once _CLAPI_CLASS_ . "/centreonUtils.class.php";
require_once _CLAPI_CLASS_ . "/centreonAPI.class.php";


$formValue = array(
    'export_cmd',
    'TP',
    'CONTACT',
    'CG',
    'export_HOST',
    'export_HTPL',
    'HC',
    'export_SERVICE',
    'export_STPL',
    'SC',
    'ACL',
    'LDAP',
    'export_INSTANCE'
);

$dbConfig['host'] = $conf_centreon['hostCentreon'];
$dbConfig['username'] = $conf_centreon['user'];
$dbConfig['password'] = $conf_centreon['password'];
$dbConfig['dbname'] = $conf_centreon['db'];
$dbConfig['storage'] = $conf_centreon['dbcstg'];
if (isset($conf_centreon['port'])) {
    $dbConfig['port'] = $conf_centreon['port'];
} elseif ($p = strstr($dbConfig['host'], ':')) {
    $p = substr($p, 1);
    if (is_numeric($p)) {
        $dbConfig['port'] = $p;
    }
}

$centreonSession = new CentreonSession();
$centreonSession->start();
$username = $_SESSION['centreon']->user->alias;
$clapiConnector = new \ClapiObject($dbConfig, array('username' => $username));

/*
* Set log_contact
*/
\CentreonClapi\CentreonUtils::setUserName($username);

$scriptContent = array();
$ajaxReturn = array();

$oExport = new \Export($clapiConnector);

foreach ($_POST as $object => $value) {

    if(in_array($object, $formValue)){
        $type = explode('_', $object);
        if ($type[0] == 'export') {
            $scriptContent[] = $oExport->generateGroup($type[1], $value);
        } elseif ($type[0] != 'submitC') {
            $scriptContent[] = $oExport->generateObject($type[0]);
        }
    } else {
        $ajaxReturn['error'][] = 'Unknown object : ' . $object;
    }

}
$ajaxReturn['fileGenerate'] = $oExport->clapiExport($scriptContent);
echo json_encode($ajaxReturn);
exit;
