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

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

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
$aclDbName = $dbmon->getConnectionConfig()->getDatabaseNameRealTime();
$hgs = $acl->getHostGroupAclConf(null, 'broker');
$aclHostString = $acl->getHostsString('ID', $dbmon);
$aclPollerString = $acl->getPollerString();

/**
 * Keep a write action to the hosts the caller may actually write.
 *
 * The topology check upstream admits read-only access to this page, and none of
 * the branches below look at the ids they are handed — the CSRF token is their
 * only gate. So a reader could reach every bulk action, on any host id it posted.
 *
 * Returns the granted subset, so an action carrying a mix acts on the allowed
 * part rather than failing whole, which is how the AJAX endpoints behave too.
 *
 * @param array<int, mixed> $selection Host ids as keys, as getSelectOption() returns
 *
 * @return array<int, mixed>
 */
function keepWritableHosts(array $selection): array
{
    global $acl, $dbmon, $centreon, $p;

    if ($selection === []) {
        return [];
    }

    if ($acl->page($p) != 1) {
        Logger::create(LogChannelEnum::WEB)->warning(
            'Hosts page: bulk action refused, no write access',
            ['userId' => (int) $centreon->user->get_id(), 'pageId' => $p]
        );

        return [];
    }

    if ($centreon->user->admin) {
        return $selection;
    }

    $granted = array_flip(array_filter(array_map(
        'intval',
        explode(',', (string) $acl->getHostsString('ID', $dbmon, false))
    )));
    $writable = array_intersect_key($selection, $granted);

    if (count($writable) !== count($selection)) {
        Logger::create(LogChannelEnum::WEB)->warning(
            'Hosts page: bulk action narrowed to the caller ACL',
            [
                'userId' => (int) $centreon->user->get_id(),
                'submitted' => count($selection),
                'granted' => count($writable),
            ]
        );
    }

    return $writable;
}

switch ($o) {
    case HOST_ADD:
    case HOST_WATCH:
    case HOST_MODIFY:
        require_once $path . 'formHost.php';
        break;
    case HOST_MASSIVE_CHANGE:
        $select = keepWritableHosts($select ?? []);
        require_once $path . 'formHost.php';
        break;
    case HOST_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            // Same guard as the bulk branches: this one took the id straight
            // from the request too.
            enableHostInDB(null, keepWritableHosts($host_id ? [$host_id => '1'] : []));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Activate a host
    case HOST_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostInDB(null, keepWritableHosts($select ?? []));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            // Same guard as the bulk branches: this one took the id straight
            // from the request too.
            disableHostInDB(null, keepWritableHosts($host_id ? [$host_id => '1'] : []));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Desactivate a host
    case HOST_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostInDB(null, keepWritableHosts($select ?? []));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostInDB(keepWritableHosts($select ?? []), $dupNbr);
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
            deleteHostInApi(hosts: array_keys(keepWritableHosts(is_array($select) ? $select : [])));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break;
    case HOST_SERVICE_DEPLOYMENT:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            applytpl(array_keys(keepWritableHosts($select ?? [])));
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHost.php';
        break; // Deploy service n hosts
    default:
        require_once $path . 'listHost.php';
        break;
}
