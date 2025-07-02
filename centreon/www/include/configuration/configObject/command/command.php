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
$path = './include/configuration/configObject/command/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$command_id = filter_var(
    $_GET['command_id'] ?? $_POST['command_id'] ?? null,
    FILTER_VALIDATE_INT
);

$type = filter_var(
    $_POST['command_type']['command_type'] ?? $_GET['type'] ?? $_POST['type'] ?? 2,
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

if (isset($_POST['o1'], $_POST['o2'])) {
    if ($_POST['o1'] != '') {
        $o = $_POST['o1'];
    }
    if ($_POST['o2'] != '') {
        $o = $_POST['o2'];
    }
}

// For inline action
if (($o === 'm' || $o === 'd') && count($select) == 0 && $command_id) {
    $select[$command_id] = 1;
}

global $isCloudPlatform;
$isCloudPlatform = isCloudPlatform();

$commandObj = new CentreonCommand($pearDB);
$lockedElements = $commandObj->getLockedCommands();

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

if ($min) {
    switch ($o) {
        case 'h': // Show Help Command
        default:
            require_once $path . 'minHelpCommand.php';
            break;
    }
} else {
    switch ($o) {
        case 'a': // Add a Command
        case 'w': // Watch a Command
        case 'c': // Modify a Command
            require_once $path . 'formCommand.php';
            break;
        case 'm': // Duplicate n Commands
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                multipleCommandInDB(
                    is_array($select) ? $select : [],
                    $select
                );
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        case 'd': // Delete n Commands
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                deleteCommandInDB(is_array($select) ? $select : []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        case 'me':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                changeCommandStatus(null, is_array($select) ? $select : [], 1);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        case 'md':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                changeCommandStatus(null, is_array($select) ? $select : [], 0);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        case 'en':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($command_id !== false) {
                    changeCommandStatus($command_id, null, 1);
                }
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        case 'di':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($command_id !== false) {
                    changeCommandStatus($command_id, null, 0);
                }
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listCommand.php';
            break;
        default:
            require_once $path . 'listCommand.php';
            break;
    }
}
