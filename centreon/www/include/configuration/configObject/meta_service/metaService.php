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

if (isset($_POST['o']) && $_POST['o']) {
    $o = $_POST['o'];
}

// Path to the configuration dir
$path = './include/configuration/configObject/meta_service/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$meta_id = filter_var(
    $_GET['meta_id'] ?? $_POST['meta_id'] ?? null,
    FILTER_VALIDATE_INT
);

$host_id = filter_var(
    $_GET['host_id'] ?? $_POST['host_id'] ?? null,
    FILTER_VALIDATE_INT
);

$metric_id = filter_var(
    $_GET['metric_id'] ?? $_POST['metric_id'] ?? null,
    FILTER_VALIDATE_INT
);

$msr_id = filter_var(
    $_GET['msr_id'] ?? $_POST['msr_id'] ?? null,
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
$aclDbName = $acl->getNameDBAcl();
$metaStr = $acl->getMetaServiceString();

if (! $oreon->user->admin && $meta_id && ! str_contains($metaStr, "'" . $meta_id . "'")) {
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText(_('You are not allowed to access this meta service'));

    return null;
}

switch ($o) {
    case 'a': // Add an Meta Service
    case 'w': // Watch an Meta Service
    case 'c': // Modify an Meta Service
        require_once $path . 'formMetaService.php';
        break;
    case 's': // Activate a Meta Service
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableMetaServiceInDB($meta_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetaService.php';
        break;
    case 'u': // Desactivate a Meta Service
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableMetaServiceInDB($meta_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetaService.php';
        break;
    case 'd': // Delete n Meta Servive
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteMetaServiceInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetaService.php';
        break;
    case 'm': // Duplicate n Meta Service
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleMetaServiceInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetaService.php';
        break;
    case 'ci': // Manage Service of the MS
        require_once $path . 'listMetric.php';
        break;
    case 'as': // Add Service to a MS
        require_once $path . 'metric.php';
        break;
    case 'cs': // Change Service to a MS
        require_once $path . 'metric.php';
        break;
    case 'ss': // Activate a Metric
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            enableMetricInDB($msr_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetric.php';
        break;
    case 'us': // Desactivate a Metric
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            disableMetricInDB($msr_id);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetric.php';
        break;
    case 'ws': // View Service to a MS
        require_once $path . 'metric.php';
        break;
    case 'ds':  // Delete n Metric
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteMetricInDB(is_array($select) ? $select : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listMetric.php';
        break;
    default:
        require_once $path . 'listMetaService.php';
        break;
}
