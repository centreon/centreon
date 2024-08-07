<?php

/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (! isset($centreon)) {
    exit();
}

const SERVICE_GROUP_ADD = 'a';
const SERVICE_GROUP_WATCH = 'w';
const SERVICE_GROUP_MODIFY = 'c';
const SERVICE_GROUP_ACTIVATION = 's';
const SERVICE_GROUP_DEACTIVATION = 'u';
const SERVICE_GROUP_DUPLICATION = 'm';
const SERVICE_GROUP_DELETION = 'd';

// Path to the configuration dir
$path = './include/configuration/configObject/servicegroup/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

global $isCloudPlatform;

$isCloudPlatform = isCloudPlatform();

$serviceGroupId = filter_var(
    $_GET['sg_id'] ?? $_POST['sg_id'] ?? null,
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
if (isset($ret) && is_array($ret) && $ret['topology_page'] !== '' && $p !== $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $oreon->user->access;
$aclDbName = $acl->getNameDBAcl();
$dbmon = new CentreonDB('centstorage');
$sgs = $acl->getServiceGroupAclConf(null, 'broker');

function mywrap($el)
{
    return "'" . $el . "'";
}
$sgString = implode(',', array_map('mywrap', array_keys($sgs)));

switch ($o) {
    case SERVICE_GROUP_ADD: // Add a service group
    case SERVICE_GROUP_WATCH: // Watch a service group
    case SERVICE_GROUP_MODIFY: // Modify a service group
        require_once $path . 'formServiceGroup.php';
        break;
    case SERVICE_GROUP_ACTIVATION: // Activate a service group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceGroupInDB($serviceGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DEACTIVATION: // Deactivate a service group
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceGroupInDB($serviceGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DUPLICATION: // Duplicate n service groups
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleServiceGroupInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    case SERVICE_GROUP_DELETION: // Delete n service groups
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteServiceGroupInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceGroup.php';
        break;
    default:
        require_once $path . 'listServiceGroup.php';
        break;
}
