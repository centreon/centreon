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

$cG = $_GET['cg_id'] ?? null;
$cP = $_POST['cg_id'] ?? null;
$cg_id = $cG ?: $cP;

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?: $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?: $cP;

// Path to the configuration dir
$path = './include/configuration/configObject/contactgroup/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$allowedContacts = $acl->getContactAclConf(['fields' => ['contact_id', 'contact_name'], 'keys' => ['contact_id'], 'get_row' => 'contact_name', 'order' => 'contact_name']);
$allowedAclGroups = $acl->getAccessGroups();
$contactstring = '';
if (count($allowedContacts)) {
    $first = true;
    foreach ($allowedContacts as $key => $val) {
        if ($first) {
            $first = false;
        } else {
            $contactstring .= ',';
        }
        $contactstring .= "'" . $key . "'";
    }
} else {
    $contactstring = "''";
}

switch ($o) {
    case 'a':
        // Add a contactgroup
        require_once $path . 'formContactGroup.php';
        break;
    case 'w':
        // Watch a contactgroup
        require_once $path . 'formContactGroup.php';
        break;
    case 'c':
        // Modify a contactgroup
        require_once $path . 'formContactGroup.php';
        break;
    case 's':
        // Activate a contactgroup
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableContactGroupInDB($cg_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactGroup.php';
        break;
    case 'u':
        // Desactivate a contactgroup
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableContactGroupInDB($cg_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactGroup.php';
        break;
    case 'm':
        // Duplicate n contact group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleContactGroupInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactGroup.php';
        break;
    case 'd':
        // Delete a contact group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteContactGroupInDB($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listContactGroup.php';
        break;
    case 'dn':
        require_once $path . 'displayNotification.php';
        break;
    default:
        require_once $path . 'listContactGroup.php';
        break;
}
