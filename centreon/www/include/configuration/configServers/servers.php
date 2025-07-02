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
$server_id = filter_var(
    $_GET['server_id'] ?? $_POST['server_id'] ?? null,
    FILTER_VALIDATE_INT
);

$select = filter_var_array(
    $_GET['select'] ?? $_POST['select'] ?? [],
    FILTER_VALIDATE_INT
);

$dupNbr = filter_var_array(
    $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [],
    FILTER_VALIDATE_INT
);

// Path to the configuration dir
$path = './include/configuration/configServers/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

// Build poller listing for ACL
$serverResult
    = $centreon->user->access->getPollerAclConf(
        [
            'fields' => ['id', 'name', 'last_restart'],
            'order' => ['name'],
            'keys' => ['id'],
        ]
    );

$instanceObj = new CentreonInstance($pearDB);

define('SERVER_ADD', 'a');
define('SERVER_DELETE', 'd');
define('SERVER_DISABLE', 'u');
define('SERVER_DUPLICATE', 'm');
define('SERVER_ENABLE', 's');
define('SERVER_MODIFY', 'c');
define('SERVER_WATCH', 'w');

$action = filter_var(
    $_POST['o1'] ?? $_POST['o2'] ?? null,
    FILTER_VALIDATE_REGEXP,
    ['options' => ['regexp' => '/^(a|c|d|m|s|u|w)$/']]
);
if ($action !== false) {
    $o = $action;
}

/**
 * Actions forbidden if server is a remote
 */
$forbiddenIfRemote = [
    SERVER_ADD,
    SERVER_MODIFY,
    SERVER_ENABLE,
    SERVER_DISABLE,
    SERVER_DUPLICATE,
    SERVER_DELETE,
];
if ($isRemote && in_array($o, $forbiddenIfRemote)) {
    require_once $path . '../../core/errors/alt_error.php';

    exit();
}

switch ($o) {
    case SERVER_ADD:
    case SERVER_WATCH:
    case SERVER_MODIFY:
        require_once $path . 'formServers.php';
        break;
    case SERVER_ENABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($server_id !== false) {
                enableServerInDB($server_id);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServers.php';
        break;
    case SERVER_DISABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($server_id !== false) {
                disableServerInDB($server_id);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServers.php';
        break;
    case SERVER_DUPLICATE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $select) && ! in_array(false, $dupNbr)) {
                duplicateServer($select, $dupNbr);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServers.php';
        break;
    case SERVER_DELETE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $select)) {
                deleteServerInDB($select);
            }
        } else {
            unvalidFormMessage();
        }
        // then require the same file than default
        // no break
    default:
        require_once $path . 'listServers.php';
        break;
}
