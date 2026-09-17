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

const SERVICE_GROUP_ADD = 'a';
const SERVICE_GROUP_WATCH = 'w';
const SERVICE_GROUP_MODIFY = 'c';
const SERVICE_GROUP_ACTIVATION = 's';
const SERVICE_GROUP_DEACTIVATION = 'u';
const SERVICE_GROUP_DUPLICATION = 'm';
const SERVICE_GROUP_DELETION = 'd';

// Path to the configuration dir
$path = './include/configuration/configObject/servicegroup/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

global $isCloudPlatform;

$isCloudPlatform = isCloudPlatform();

$serviceGroupId = filter_var(
    $_GET['sg_id'] ?? $_POST['sg_id'] ?? null,
    FILTER_VALIDATE_INT
);

$select = filter_var_array(
    getSelectOption(),
    FILTER_VALIDATE_INT
);

$dupNbr = filter_var_array(
    getDuplicateNumberOption(),
    FILTER_VALIDATE_INT
);

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] !== '' && $p !== $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $oreon->user->access;
$aclDbName = $acl->getNameDBAcl();
$dbmon = new CentreonDB('centstorage');
$sgs = $acl->getServiceGroupAclConf(null, 'broker');

function mywrap($el)
{
    return "'" . $el . "'";
}
$sgString = implode(',', array_map('mywrap', array_keys($sgs)));

switch ($o) {
    case SERVICE_GROUP_ADD: // Add a service group
    case SERVICE_GROUP_WATCH: // Watch a service group
    case SERVICE_GROUP_MODIFY: // Modify a service group
        require_once $path . 'formServiceGroup.php';
        break;
    case SERVICE_GROUP_ACTIVATION: // Activate a service group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceGroupInDB($serviceGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DEACTIVATION: // Deactivate a service group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceGroupInDB($serviceGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DUPLICATION: // Duplicate n service groups
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleServiceGroupInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DELETION: // Delete n service groups
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteServiceGroupInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    default:
        require_once $path . 'listServiceGroup.php';
        break;
}
