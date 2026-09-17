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

if (! isset($centreon)) {
    exit();
}

require_once './class/centreonTraps.class.php';
require_once './include/common/common-Func.php';

define('TRAP_ADD', 'a');
define('TRAP_DELETE', 'd');
define('TRAP_DUPLICATE', 'm');
define('TRAP_MODIFY', 'c');
define('TRAP_WATCH', 'w');

$trapsId = filter_var(
    $_GET['traps_id'] ?? $_POST['traps_id'] ?? null,
    FILTER_VALIDATE_INT
);

$selectIds = filter_var_array(
    $_GET['select'] ?? $_POST['select'] ?? [],
    FILTER_VALIDATE_INT
);

$duplicateNbr = filter_var_array(
    $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [],
    FILTER_VALIDATE_INT
);

// Path to the configuration dir
$path = './include/configuration/configObject/traps/';

$trapObj = new CentreonTraps($pearDB, $oreon);
$acl = $centreon->user->access;
$aclDbName = $acl->getNameDBAcl();
$dbmon = new CentreonDB('centstorage');
$sgs = $acl->getServiceGroupAclConf(null, 'broker');
$severityObj = new CentreonCriticality($pearDB);

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

switch ($o) {
    case TRAP_ADD:
        require_once $path . 'formTraps.php';
        break;
    case TRAP_WATCH:
        if (is_int($trapsId)) {
            require_once $path . 'formTraps.php';
        } else {
            require_once $path . 'listTraps.php';
        }
        break;
    case TRAP_MODIFY:
        if (is_int($trapsId)) {
            require_once $path . 'formTraps.php';
        } else {
            require_once $path . 'listTraps.php';
        }
        break;
    case TRAP_DUPLICATE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds) && ! in_array(false, $duplicateNbr)) {
                $trapObj->duplicate($selectIds, $duplicateNbr);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listTraps.php';
        break;
    case TRAP_DELETE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                $trapObj->delete($selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listTraps.php';
        break;
    default:
        require_once $path . 'listTraps.php';
        break;
}
