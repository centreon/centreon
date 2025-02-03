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

const HOST_ADD = 'a';
const HOST_WATCH = 'w';
const HOST_MODIFY = 'c';
const HOST_MASSIVE_CHANGE = 'mc';
const HOST_ACTIVATION = 's';
const HOST_MASSIVE_ACTIVATION = 'ms';
const HOST_DEACTIVATION = 'u';
const HOST_MASSIVE_DEACTIVATION = 'mu';
const HOST_DUPLICATION = 'm';
const HOST_DELETION = 'd';
const HOST_SERVICE_DEPLOYMENT = 'dp';

if (isset($_POST['o1'], $_POST['o2'])) {
    if ($_POST['o1'] !== '') {
        $o = $_POST['o1'];
    }
    if ($_POST['o2'] !== '') {
        $o = $_POST['o2'];
    }
}

$host_id = $o === HOST_MASSIVE_CHANGE
    ? false
    : filter_var(
        $_GET['host_id'] ?? $_POST['host_id'] ?? null,
        FILTER_VALIDATE_INT
    );

// Path to the configuration dir
global $path, $isCloudPlatform;

$path = './include/configuration/configObject/host/';

require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$isCloudPlatform = isCloudPlatform();

$select = filter_var_array(
    getSelectOption(),
    FILTER_VALIDATE_INT
);
$dupNbr = filter_var_array(
    getDuplicateNumberOption(),
    FILTER_VALIDATE_INT
);

// Set the real page
if (
    isset($ret2)
    && is_array($ret2)
    && $ret2['topology_page'] !== ''
    && $p !== $ret2['topology_page']
) {
    $p = $ret2['topology_page'];
} elseif (
    isset($ret)
    && is_array($ret)
    && $ret['topology_page'] !== ''
    && $p !== $ret['topology_page']
) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl('broker');
$hgs = $acl->getHostGroupAclConf(null, 'broker');
$aclHostString = $acl->getHostsString('ID', $dbmon);
$aclPollerString = $acl->getPollerString();

switch ($o) {
    case HOST_ADD:
    case HOST_WATCH:
    case HOST_MODIFY:
    case HOST_MASSIVE_CHANGE:
        require_once $path . 'formHost.php';
        break;
    case HOST_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostInDB($host_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Activate a host
    case HOST_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostInDB($host_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Desactivate a host
    case HOST_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        $hgs = $acl->getHostGroupAclConf(null, 'broker');
        $aclHostString = $acl->getHostsString('ID', $dbmon);
        $aclPollerString = $acl->getPollerString();
        require_once $path . 'listHost.php';
        break;
    case HOST_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostInApi(hosts: is_array($select) ? array_keys($select) : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_SERVICE_DEPLOYMENT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            applytpl(array_keys($select) ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Deploy service n hosts
    default:
        require_once $path . 'listHost.php';
        break;
}
