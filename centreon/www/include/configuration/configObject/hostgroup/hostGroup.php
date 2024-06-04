<?php

/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

const HOST_GROUP_ADD = 'a';
const HOST_GROUP_WATCH = 'w';
const HOST_GROUP_MODIFY = 'c';
const HOST_GROUP_ACTIVATION = 's';
const HOST_GROUP_MASSIVE_ACTIVATION = 'ms';
const HOST_GROUP_DEACTIVATION = 'u';
const HOST_GROUP_MASSIVE_DEACTIVATION = 'mu';
const HOST_GROUP_DUPLICATION = 'm';
const HOST_GROUP_DELETION = 'd';

$hostGroupId = filter_var(
    $_GET['hg_id'] ?? $_POST['hg_id'] ?? null,
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

// Path to the configuration dir
$path = './include/configuration/configObject/hostgroup/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

global $isCloudPlatform;

$isCloudPlatform = isCloudPlatform();

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] !== '' && $p !== $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl();
$hgs = $acl->getHostGroupAclConf(null, 'broker');

$hostGroupIds = array_keys($hgs);
$hoststring = $acl->getHostsString('ID', $dbmon);

switch ($o) {
    case HOST_GROUP_ADD:
        require_once $path . 'formHostGroup.php';
        break; // Add a Hostgroup
    case HOST_GROUP_WATCH:
        require_once $path . 'formHostGroup.php';
        break; // Watch a Hostgroup
    case HOST_GROUP_MODIFY:
        require_once $path . 'formHostGroup.php';
        break; // Modify a Hostgroup
    case HOST_GROUP_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostGroupInDB($hostGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Activate a Hostgroup
    case HOST_GROUP_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostGroupInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break;
    case HOST_GROUP_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostGroupInDB($hostGroupId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Desactivate a Hostgroup
    case HOST_GROUP_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostGroupInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break;
    case HOST_GROUP_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostGroupInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        $acl = $centreon->user->access;
        $hgs = $acl->getHostGroupAclConf(null, 'broker');
        $hoststring = $acl->getHostsString('ID', $dbmon);
        require_once $path . 'listHostGroup.php';
        break; // Duplicate n Host Groups
    case HOST_GROUP_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostGroupInDB($isCloudPlatform, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostGroup.php';
        break; // Delete n Host group
    default:
        require_once $path . 'listHostGroup.php';
        break;
}
