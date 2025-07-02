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

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? null,
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

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

switch ($o) {
    case 'a':
        require_once __DIR__ . '/formMnftr.php';
        break; // Add a Trap
    case 'w':
        require_once __DIR__ . '/formMnftr.php';
        break; // Watch a Trap
    case 'c':
        require_once __DIR__ . '/formMnftr.php';
        break; // Modify a Trap
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleMnftrInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listMnftr.php';
        break; // Duplicate n Traps
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteMnftrInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listMnftr.php';
        break; // Delete n Traps
    default:
        require_once __DIR__ . '/listMnftr.php';
        break;
}
