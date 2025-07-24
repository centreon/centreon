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
$path = './include/options/accessLists/groupsACL/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

function sanitize_input_array(array $inputArray): array
{
    $sanitizedArray = [];
    foreach ($inputArray as $key => $value) {
        $key = filter_var($key, FILTER_VALIDATE_INT);
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if (false !== $key && false !== $value) {
            $sanitizedArray[$key] = $value;
        }
    }

    return $sanitizedArray;
}

$dupNbr = $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? null;
$dupNbr = is_array($dupNbr) ? sanitize_input_array($dupNbr) : [];

$select = $_GET['select'] ?? $_POST['select'] ?? null;
$select = is_array($select) ? sanitize_input_array($select) : [];

$acl_group_id = filter_var($_GET['acl_group_id'] ?? $_POST['acl_group_id'] ?? null, FILTER_VALIDATE_INT) ?? null;

// Caution $o may already be set from the GET or from the POST.
$postO = filter_var(
    $_POST['o1'] ?? $_POST['o2'] ?? $o ?? null,
    FILTER_VALIDATE_REGEXP,
    ['options' => ['regexp' => '/^(a|c|d|m|s|u|w)$/']]
);
if ($postO !== false) {
    $o = $postO;
}

switch ($o) {
    case 'a':
        // Add an access group
    case 'w':
        // Watch an access group
    case 'c':
        // Modify an access group
        require_once $path . 'formGroupConfig.php';
        break;
    case 's':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableGroupInDB($acl_group_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Activate a contactgroup
    case 'ms':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableGroupInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Activate n access group
    case 'u':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableGroupInDB($acl_group_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Desactivate a contactgroup
    case 'mu':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableGroupInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Desactivate n access group
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleGroupInDB($select, $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Duplicate n access group
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteGroupInDB($select);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listGroupConfig.php';
        break; // Delete n access group
    default:
        require_once $path . 'listGroupConfig.php';
        break;
}
