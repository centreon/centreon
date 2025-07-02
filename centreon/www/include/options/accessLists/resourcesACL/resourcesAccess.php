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

$aclId = filter_var(
    $_GET['acl_res_id'] ?? $_POST['acl_res_id'] ?? null,
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

if (isset($_POST['o1'], $_POST['o2'])) {
    if ($_POST['o1'] != '') {
        $o = $_POST['o1'];
    }
    if ($_POST['o2'] != '') {
        $o = $_POST['o2'];
    }
}

switch ($o) {
    case 'a':
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Add a LCA
    case 'w':
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Watch a LCA
    case 'c':
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Modify a LCA
    case 's':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableLCAInDB($aclId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Activate a LCA
    case 'ms':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableLCAInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Activate n LCA
    case 'u':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableLCAInDB($aclId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Desactivate a LCA
    case 'mu':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableLCAInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Desactivate n LCA
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleLCAInDB($select, $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Duplicate n LCAs
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteLCAInDB($select);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Delete n LCAs
    default:
        require_once __DIR__ . '/listsResourcesAccess.php';
        break;
}
