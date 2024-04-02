<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

const HOST_GROUP_ADD = 'a';
const HOST_GROUP_WATCH = 'w';
const HOST_GROUP_MODIFY = 'c';
const HOST_GROUP_ACTIVATION = 's';
const HOST_GROUP_MASSIVE_ACTIVATION = 'ms';
const HOST_GROUP_DEACTIVATION = 'u';
const HOST_GROUP_MASSIVE_DEACTIVATION = 'mu';
const HOST_GROUP_DUPLICATION = 'm';
const HOST_GROUP_DELETION = 'd';

$hostGroupId = filter_var(
    $_GET['hg_id'] ?? $_POST['hg_id'] ?? null,
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

// Path to the configuration dir
$path = './include/configuration/configObject/hostgroup/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

global $isCloudPlatform;

$isCloudPlatform = isCloudPlatform();

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] !== '' && $p !== $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl();
$hgs = $acl->getHostGroupAclConf(null, 'broker');

function mywrap($el)
{
    return "'" . $el . "'";
}

$hgString = implode(',', array_map('mywrap', array_keys($hgs)));
$hoststring = $acl->getHostsString('ID', $dbmon);

switch ($o) {
    case HOST_GROUP_ADD:
        require_once $path . 'formHostGroup.php';
        break; // Add a Hostgroup
    case HOST_GROUP_WATCH:
        require_once $path . 'formHostGroup.php';
        break; // Watch a Hostgroup
    case HOST_GROUP_MODIFY:
        require_once $path . 'formHostGroup.php';
        break; // Modify a Hostgroup
    case HOST_GROUP_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostGroupInDB($hostGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Activate a Hostgroup
    case HOST_GROUP_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostGroupInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break;
    case HOST_GROUP_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostGroupInDB($hostGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Desactivate a Hostgroup
    case HOST_GROUP_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostGroupInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break;
    case HOST_GROUP_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostGroupInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        $acl = $centreon->user->access;
        $hgs = $acl->getHostGroupAclConf(null, 'broker');
        $hgString = implode(',', array_map('mywrap', array_keys($hgs)));
        $hoststring = $acl->getHostsString('ID', $dbmon);
        require_once $path . 'listHostGroup.php';
        break; // Duplicate n Host Groups
    case HOST_GROUP_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostGroupInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Delete n Host group
    default:
        require_once $path . 'listHostGroup.php';
        break;
}
