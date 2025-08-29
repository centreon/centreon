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

const RESOURCE_ACCESS_ADD = 'a';
const RESOURCE_ACCESS_WATCH = 'w';
const RESOURCE_ACCESS_MODIFY = 'c';
const RESOURCE_ACCESS_MASSIVE_CHANGE = 'mc';
const RESOURCE_ACCESS_ACTIVATION = 's';
const RESOURCE_ACCESS_MASSIVE_ACTIVATION = 'ms';
const RESOURCE_ACCESS_DEACTIVATION = 'u';
const RESOURCE_ACCESS_MASSIVE_DEACTIVATION = 'mu';
const RESOURCE_ACCESS_DUPLICATION = 'm';
const RESOURCE_ACCESS_DELETION = 'd';

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
    case RESOURCE_ACCESS_ADD:
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Add a LCA
    case RESOURCE_ACCESS_WATCH:
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Watch a LCA
    case RESOURCE_ACCESS_MODIFY:
        require_once __DIR__ . '/formResourcesAccess.php';
        break; // Modify a LCA
    case RESOURCE_ACCESS_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableLCAInDB($aclId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Activate a LCA
    case RESOURCE_ACCESS_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableLCAInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Activate n LCA
    case RESOURCE_ACCESS_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableLCAInDB($aclId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Desactivate a LCA
    case RESOURCE_ACCESS_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableLCAInDB(null, $select);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Desactivate n LCA
    case RESOURCE_ACCESS_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleLCAInDB($select, $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsResourcesAccess.php';
        break; // Duplicate n LCAs
    case RESOURCE_ACCESS_DELETION:
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
