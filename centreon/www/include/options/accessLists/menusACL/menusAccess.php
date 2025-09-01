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
// Path to the configuration dir
$path = './include/options/accessLists/menusACL/';

require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

define('ACL_ADD', 'a');
define('ACL_WATCH', 'w');
define('ACL_MODIFY', 'c');
define('ACL_ENABLE', 's');
define('ACL_MULTI_ENABLE', 'ms');
define('ACL_DISABLE', 'u');
define('ACL_MULTI_DISABLE', 'mu');
define('ACL_DUPLICATE', 'm');
define('ACL_DELETE', 'd');

$aclTopologyId = filter_var(
    $_GET['acl_topo_id'] ?? $_POST['acl_topo_id'] ?? null,
    FILTER_VALIDATE_INT
);

$duplicateNbr = filter_var_array(
    $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [],
    FILTER_VALIDATE_INT
);

// If one data are not correctly typed in array, it will be set to false
$selectIds = filter_var_array(
    $_GET['select'] ?? $_POST['select'] ?? [],
    FILTER_VALIDATE_INT
);

$action = filter_var(
    $_POST['o1'] ?? $_POST['o2'] ?? null,
    FILTER_VALIDATE_REGEXP,
    ['options' => ['regexp' => '/([a|c|d|m|s|u|w]{1})/']]
);
if ($action !== false) {
    $o = $action;
}

switch ($o) {
    case ACL_ADD:
        require_once $path . 'formMenusAccess.php';
        break;
    case ACL_WATCH:
        if (is_int($aclTopologyId)) {
            require_once $path . 'formMenusAccess.php';
        } else {
            require_once $path . 'listsMenusAccess.php';
        }
        break;
    case ACL_MODIFY:
        if (is_int($aclTopologyId)) {
            require_once $path . 'formMenusAccess.php';
        } else {
            require_once $path . 'listsMenusAccess.php';
        }
        break;
    case ACL_ENABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (is_int($aclTopologyId)) {
                enableLCAInDB($aclTopologyId);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    case ACL_MULTI_ENABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                enableLCAInDB(null, $selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    case ACL_DISABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (is_int($aclTopologyId)) {
                disableLCAInDB($aclTopologyId);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    case ACL_MULTI_DISABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                disableLCAInDB(null, $selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    case ACL_DUPLICATE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds) && ! in_array(false, $duplicateNbr)) {
                multipleLCAInDB($selectIds, $duplicateNbr);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    case ACL_DELETE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                deleteLCAInDB($selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listsMenusAccess.php';
        break;
    default:
        require_once $path . 'listsMenusAccess.php';
        break;
}
