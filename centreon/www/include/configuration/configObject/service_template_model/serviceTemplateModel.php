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

$service_id = filter_var(
    $_GET['service_id'] ?? $_POST['service_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($o == 'c' && $service_id == null) {
    $o = '';
}

// Path to the configuration dir
$path = './include/configuration/configObject/service_template_model/';
$path2 = './include/configuration/configObject/service/';

// PHP functions
require_once $path2 . 'DB-Func.php';
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

$serviceObj = new CentreonService($pearDB);
$lockedElements = $serviceObj->getLockedServiceTemplates();

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

const SERVICE_TEMPLATE_ADD = 'a';
const SERVICE_TEMPLATE_WATCH = 'w';
const SERVICE_TEMPLATE_MODIFY = 'c';
const SERVICE_TEMPLATE_MASSIVE_CHANGE = 'mc';
const SERVICE_TEMPLATE_ACTIVATION = 's';
const SERVICE_TEMPLATE_MASSIVE_ACTIVATION = 'ms';
const SERVICE_TEMPLATE_DEACTIVATION = 'u';
const SERVICE_TEMPLATE_MASSIVE_DEACTIVATION = 'mu';
const SERVICE_TEMPLATE_DUPLICATION = 'm';
const SERVICE_TEMPLATE_DELETION = 'd';

switch ($o) {
    case SERVICE_TEMPLATE_ADD:
    case SERVICE_TEMPLATE_WATCH:
    case SERVICE_TEMPLATE_MODIFY:
    case SERVICE_TEMPLATE_MASSIVE_CHANGE:
        require_once $path . 'formServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceInDB($service_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableServiceInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceInDB($service_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableServiceInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleServiceInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    case SERVICE_TEMPLATE_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteServiceTemplateByApi($select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listServiceTemplateModel.php';
        break;
    default:
        require_once $path . 'listServiceTemplateModel.php';
        break;
}
