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

if (! isset($oreon)) {
    exit();
}

$cG = $_GET['pool_id'] ?? null;
$cP = $_POST['pool_id'] ?? null;
$slot_id = $cG ?? $cP;
$slot_id = $slot_id !== null ? (int) $slot_id : null;

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?? $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?? $cP;

$search = isset($_POST['searchSlot']) ? htmlentities($_POST['searchSlot'], ENT_QUOTES) : null;

// Path to the configuration dir
$path = './modules/centreon-dsm/core/configuration/services/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

switch ($o) {
    case 'a':
        include_once $path . 'formSlot.php'; // Add a slot
        break;
    case 'w':
        include_once $path . 'formSlot.php'; // Watch a slot
        break;
    case 'c':
        include_once $path . 'formSlot.php'; // Modify a slot
        break;
    case 's':
        enablePoolInDB($slot_id);
        include_once $path . 'listSlot.php'; // Activate a slot
        break;
    case 'ms':
        enablePoolInDB(null, $select ?? []);
        include_once $path . 'listSlot.php';
        break;
    case 'u':
        disablePoolInDB($slot_id);
        include_once $path . 'listSlot.php'; // Desactivate a slot
        break;
    case 'mu':
        disablePoolInDB(null, $select ?? []);
        include_once $path . 'listSlot.php';
        break;
    case 'm':
        multiplePoolInDB($select ?? [], $dupNbr);
        include_once $path . 'listSlot.php'; // Duplicate n slots
        break;
    case 'd':
        deletePoolInDB($select ?? []);
        include_once $path . 'listSlot.php'; // Delete n slots
        break;
    default:
        include_once $path . 'listSlot.php';
        break;
}
