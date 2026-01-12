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

const ADD_BROKER_CONFIGURATION = 'a';
const WATCH_BROKER_CONFIGURATION = 'w';
const MODIFY_BROKER_CONFIGURATION = 'c';
const ACTIVATE_BROKER_CONFIGURATION = 's';
const DEACTIVATE_BROKER_CONFIGURATION = 'u';
const DUPLICATE_BROKER_CONFIGURATIONS = 'm';
const DELETE_BROKER_CONFIGURATIONS = 'd';
const LISTING_FILE = '/listCentreonBroker.php';
const FORM_FILE = '/formCentreonBroker.php';

$cG = $_GET['id'] ?? null;
$cP = $_POST['id'] ?? null;
$id = $cG ?: $cP;

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?: $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?: $cP;

require_once './class/centreonConfigCentreonBroker.php';

// Path to the configuration dir

// PHP functions
require_once __DIR__ . '/DB-Func.php';
require_once './include/common/common-Func.php';

/**
 *  Page forbidden if server is a remote
 */
if ($isRemote) {
    require_once __DIR__ . '/../../core/errors/alt_error.php';

    exit();
}

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$serverString = trim($acl->getPollerString());
$allowedBrokerConf = [];

if ($serverString != "''" && ! empty($serverString)) {
    $sql = 'SELECT config_id FROM cfg_centreonbroker WHERE ns_nagios_server IN (' . $serverString . ')';
    $res = $pearDB->query($sql);
    while ($row = $res->fetchRow()) {
        $allowedBrokerConf[$row['config_id']] = true;
    }
}
switch ($o) {
    case ADD_BROKER_CONFIGURATION:
    case WATCH_BROKER_CONFIGURATION:
    case MODIFY_BROKER_CONFIGURATION:
        require_once __DIR__ . FORM_FILE;
        break;
    case ACTIVATE_BROKER_CONFIGURATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableCentreonBrokerInDB($id);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . LISTING_FILE;
        break; // Activate a CentreonBroker CFG
    case DEACTIVATE_BROKER_CONFIGURATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disablCentreonBrokerInDB($id);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . LISTING_FILE;
        break; // Desactivate a CentreonBroker CFG
    case DUPLICATE_BROKER_CONFIGURATIONS:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleCentreonBrokerInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . LISTING_FILE;
        break; // Duplicate n CentreonBroker CFGs
    case DELETE_BROKER_CONFIGURATIONS:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteCentreonBrokerInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . LISTING_FILE;
        break; // Delete n CentreonBroker CFG
    default:
        require_once __DIR__ . LISTING_FILE;
        break;
}
