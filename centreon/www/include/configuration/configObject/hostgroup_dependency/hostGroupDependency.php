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

const ADD_DEPENDENCY = 'a';
const WATCH_DEPENDENCY = 'w';
const MODIFY_DEPENDENCY = 'c';
const DUPLICATE_DEPENDENCY = 'm';
const DELETE_DEPENDENCY = 'd';
// Path to the configuration dir
$path = './include/configuration/configObject/hostgroup_dependency/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$depId = filter_var(
    $_GET['dep_id'] ?? $_POST['dep_id'] ?? null,
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
$hgs = $acl->getHostGroupAclConf(null, 'broker');
$hgstring = CentreonUtils::toStringWithQuotes($hgs);

switch ($o) {
    case ADD_DEPENDENCY:
    case WATCH_DEPENDENCY:
    case MODIFY_DEPENDENCY:
        require_once $path . 'formHostGroupDependency.php';
        break;
    case DUPLICATE_DEPENDENCY:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostGroupDependencyInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroupDependency.php';
        break;
    case DELETE_DEPENDENCY:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostGroupDependencyInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroupDependency.php';
        break;
    default:
        require_once $path . 'listHostGroupDependency.php';
        break;
}
