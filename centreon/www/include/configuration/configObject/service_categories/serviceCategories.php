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
$path = './include/configuration/configObject/service_categories/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$sc_id = filter_var(
    $_GET['sc_id'] ?? $_POST['sc_id'] ?? null,
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

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $oreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl();
$scString = $acl->getServiceCategoriesString();

switch ($o) {
    case 'a': // Add a service category
    case 'w': // Watch a service category
    case 'c': // Modify a service category
        require_once $path . 'formServiceCategories.php';
        break;
    case 's': // Activate a ServiceCategories
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceCategorieInDB($sc_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    case 'ms':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceCategorieInDB(null, is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    case 'u': // Desactivate a service category
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceCategorieInDB($sc_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    case 'mu':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceCategorieInDB(null, is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    case 'm': // Duplicate n service categories
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleServiceCategorieInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    case 'd': // Delete n service categories
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteServiceCategorieInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceCategories.php';
        break;
    default:
        require_once $path . 'listServiceCategories.php';
        break;
}
