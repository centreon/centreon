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

$nagiosId = filter_var(
    $_GET['nagios_id'] ?? $_POST['nagios_id'] ?? null,
    FILTER_VALIDATE_INT
) ?: null;

$select = filter_var_array(
    $_GET['select'] ?? $_POST['select'] ?? [],
    FILTER_VALIDATE_INT
);

$dupNbr = filter_var_array(
    $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [],
    FILTER_VALIDATE_INT
);

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

$acl = $oreon->user->access;
$serverString = $acl->getPollerString();
$allowedMainConf = [];
if ($serverString != "''" && ! empty($serverString)) {
    $sql = 'SELECT nagios_id FROM cfg_nagios WHERE nagios_server_id IN (' . $serverString . ')';
    $res = $pearDB->query($sql);
    while ($row = $res->fetchRow()) {
        $allowedMainConf[$row['nagios_id']] = true;
    }
}

switch ($o) {
    case 'a':
        require_once __DIR__ . '/formNagios.php';
        break; // Add Nagios.cfg
    case 'w':
        require_once __DIR__ . '/formNagios.php';
        break; // Watch Nagios.cfg
    case 'c':
        require_once __DIR__ . '/formNagios.php';
        break; // Modify Nagios.cfg
    case 's':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableNagiosInDB($nagiosId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listNagios.php';
        break; // Activate a nagios CFG
    case 'u':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableNagiosInDB($nagiosId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listNagios.php';
        break; // Desactivate a nagios CFG
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleNagiosInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listNagios.php';
        break; // Duplicate n nagios CFGs
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteNagiosInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listNagios.php';
        break; // Delete n nagios CFG
    default:
        require_once __DIR__ . '/listNagios.php';
        break;
}
