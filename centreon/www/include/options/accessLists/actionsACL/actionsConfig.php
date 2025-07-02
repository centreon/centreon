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

$aclActionId = filter_var(
    $_GET['acl_action_id'] ?? $_POST['acl_action_id'] ?? null,
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

const ACL_ACTION_ADD = 'a';
const ACL_ACTION_WATCH = 'w';
const ACL_ACTION_MODIFY = 'c';
const ACL_ACTION_ACTIVATION = 's';
const ACL_ACTION_MASSIVE_ACTIVATION = 'ms';
const ACL_ACTION_DEACTIVATION = 'u';
const ACL_ACTION_MASSIVE_DEACTIVATION = 'mu';
const ACL_ACTION_DUPLICATION = 'm';
const ACL_ACTION_DELETION = 'd';

switch ($o) {
    case ACL_ACTION_ADD:
    case ACL_ACTION_WATCH:
    case ACL_ACTION_MODIFY:
        require_once __DIR__ . '/formActionsAccess.php';
        break; // Modify an Actions Access
    case ACL_ACTION_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableActionInDB($aclActionId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Activate an Actions Access
    case ACL_ACTION_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableActionInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Activate an Actions Access
    case ACL_ACTION_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableActionInDB($aclActionId);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Desactivate an an Actions Access
    case ACL_ACTION_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableActionInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Desactivate n Actions Access
    case ACL_ACTION_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleActionInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Duplicate n Actions Access
    case ACL_ACTION_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteActionInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once __DIR__ . '/listsActionsAccess.php';
        break; // Delete n Actions Access
    default:
        require_once __DIR__ . '/listsActionsAccess.php';
        break;
}
