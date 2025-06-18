<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

global $form_service_type;
$form_service_type = 'BYHOST';

const SERVICE_ADD = 'a';
const SERVICE_WATCH = 'w';
const SERVICE_MODIFY = 'c';
const SERVICE_MASSIVE_CHANGE = 'mc';
const SERVICE_DIVISION = 'dv';
const SERVICE_ACTIVATION = 's';
const SERVICE_MASSIVE_ACTIVATION = 'ms';
const SERVICE_DEACTIVATION = 'u';
const SERVICE_MASSIVE_DEACTIVATION = 'mu';
const SERVICE_DUPLICATION = 'm';
const SERVICE_DELETION = 'd';

// Check options
if (isset($_POST['o1'], $_POST['o2'])) {
    if ($_POST['o1'] !== '') {
        $o = $_POST['o1'];
    }
    if ($_POST['o2'] !== '') {
        $o = $_POST['o2'];
    }
}

$service_id = $o === SERVICE_MASSIVE_CHANGE ? false : filter_var(
    $_GET['service_id'] ?? $_POST['service_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Path to the configuration dir
$path = './include/configuration/configObject/service/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

global $isCloudPlatform;

$isCloudPlatform = isCloudPlatform();

$select = filter_var_array(
    getSelectOption(),
    FILTER_VALIDATE_INT
);
$dupNbr = filter_var_array(
    getDuplicateNumberOption(),
    FILTER_VALIDATE_INT
);

/*
 * Create a suffix for file name in order to redirect service by hostgroup
 * on a good page.
 */
$linkType = '';

if ($service_id !== false) {
    // Check if a service is a service by hostgroup or not
    $statement = $pearDB->prepare('SELECT * FROM host_service_relation WHERE service_service_id = :service_id');
    $statement->bindValue(':service_id', $service_id, \PDO::PARAM_INT);
    $statement->execute();
    while ($data = $statement->fetch()) {
        if (isset($data['hostgroup_hg_id']) && $data['hostgroup_hg_id'] !== '') {
            $linkType = 'Group';
            $form_service_type = 'BYHOSTGROUP';
        }
    }
}

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] !== '' && $p !== $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$aclDbName = $acl->getNameDBAcl();

switch ($o) {
    case SERVICE_ADD:
    case SERVICE_WATCH:
    case SERVICE_MODIFY:
    case SERVICE_MASSIVE_CHANGE:
        require_once $path . 'formService.php';
        break;
    case SERVICE_DIVISION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            divideGroupedServiceInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceInDB($service_id);
        } else {
            unvalidFormMessage();
        }
        unset($_GET['service_id']);
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceInDB($service_id);
        } else {
            unvalidFormMessage();
        }
        unset($_GET['service_id']);
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleServiceInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    case SERVICE_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteServiceByApi($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
    default:
        require_once $path . "listServiceByHost{$linkType}.php";
        break;
}
