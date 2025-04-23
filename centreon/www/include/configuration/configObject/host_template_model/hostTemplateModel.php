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

$hostId = filter_var(
    $_GET['host_id'] ?? $_POST['host_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Path to the configuration dir
$path = './include/configuration/configObject/host_template_model/';
$hostConfigurationPath = './include/configuration/configObject/host/';

// PHP functions
require_once $hostConfigurationPath . 'DB-Func.php';
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

$hostObj = new CentreonHost($pearDB);
$lockedElements = $hostObj->getLockedHostTemplates();

// Set the real page
if (
    isset($ret)
    && is_array($ret)
    && $ret['topology_page'] !== ''
    && $p !== $ret['topology_page']
) {
    $p = $ret['topology_page'];
}

const HOST_TEMPLATE_ADD = 'a',
      HOST_TEMPLATE_WATCH = 'w',
      HOST_TEMPLATE_MODIFY = 'c',
      HOST_TEMPLATE_MASSIVE_CHANGE = 'mc',
      HOST_TEMPLATE_ACTIVATION = 's',
      HOST_TEMPLATE_MASSIVE_ACTIVATION = 'ms',
      HOST_TEMPLATE_DEACTIVATION = 'u',
      HOST_TEMPLATE_MASSIVE_DEACTIVATION = 'mu',
      HOST_TEMPLATE_DUPLICATION = 'm',
      HOST_TEMPLATE_DELETION = 'd';

switch ($o) {
    case HOST_TEMPLATE_ADD:
    case HOST_TEMPLATE_WATCH:
    case HOST_TEMPLATE_MODIFY:
    case HOST_TEMPLATE_MASSIVE_CHANGE:
        require_once $path . 'formHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostInDB($hostId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_MASSIVE_ACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableHostInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostInDB($hostId);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_MASSIVE_DEACTIVATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableHostInDB(null, $select ?? []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_DUPLICATION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleHostInDB($select ?? [], $dupNbr);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    case HOST_TEMPLATE_DELETION:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteHostInApi(hosts: is_array($select) ? array_keys($select) : [], isTemplate: true);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listHostTemplateModel.php';
        break;
    default:
        require_once $path . 'listHostTemplateModel.php';
        break;
}
