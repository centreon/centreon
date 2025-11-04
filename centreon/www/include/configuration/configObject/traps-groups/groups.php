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

use Core\Common\Domain\Exception\RepositoryException;

if (! isset($centreon)) {
    exit();
}

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Normalize select input
$selectInput = $_GET['select'] ?? $_POST['select'] ?? [];
if (! is_array($selectInput)) {
    $selectInput = [$selectInput];
}
$select = filter_var_array($selectInput, FILTER_VALIDATE_INT) ?: [];
$select = array_filter($select, fn($value) => $value !== false);

// Normalize dupNbr input
$dupNbrInput = $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [];
if (! is_array($dupNbrInput)) {
    $dupNbrInput = [$dupNbrInput];
}
$dupNbr = filter_var_array($dupNbrInput, FILTER_VALIDATE_INT) ?: [];
$dupNbr = array_filter($dupNbr, fn($value) => $value !== false);
$dupNbr = array_intersect_key($dupNbr, $select);

// Path to the configuration dir
$path = './include/configuration/configObject/traps-groups/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}
try {
    switch ($o) {
        case 'a':
            require_once $path . 'formGroups.php';
            break; // Add a Trap
        case 'w':
            require_once $path . 'formGroups.php';
            break; // Watch a Trap
        case 'c':
            require_once $path . 'formGroups.php';
            break; // Modify a Trap
        case 'm':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                multipleTrapGroupInDB($select ?? [], $dupNbr);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listGroups.php';
            break; // Duplicate n Traps
        case 'd':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                deleteTrapGroupInDB($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listGroups.php';
            break; // Delete n Traps
        default:
            require_once $path . 'listGroups.php';
            break;
    }
} catch (RepositoryException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while processing traps groups: ' . $exception->getMessage(),
        exception: $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText('Error while processing traps groups');
}
