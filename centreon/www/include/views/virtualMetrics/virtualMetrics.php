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
$path = './include/views/virtualMetrics/';

require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

define('METRIC_ADD', 'a');
define('METRIC_MODIFY', 'c');
define('METRIC_DELETE', 'd');
define('METRIC_DUPLICATE', 'm');
define('METRIC_ENABLE', 's');
define('METRIC_DISABLE', 'u');
define('METRIC_WATCH', 'w');

$action = filter_var(
    $_POST['o1'] ?? $_POST['o2'] ?? null,
    FILTER_VALIDATE_REGEXP,
    ['options' => ['regexp' => '/([a|c|d|m|s|u|w]{1})/']]
);
if ($action !== false) {
    $o = $action;
}

$vmetricId = filter_var(
    $_GET['vmetric_id'] ?? $_POST['vmetric_id'] ?? null,
    FILTER_VALIDATE_INT
);

$selectIds = filter_var_array(
    $_GET['select'] ?? $_POST['select'] ?? [],
    FILTER_VALIDATE_INT
);

$duplicateNbr = filter_var_array(
    $_GET['dupNbr'] ?? $_POST['dupNbr'] ?? [],
    FILTER_VALIDATE_INT
);

switch ($o) {
    case METRIC_ADD:
        require_once $path . 'formVirtualMetrics.php';
        break;
    case METRIC_WATCH:
        if (is_int($vmetricId)) {
            require_once $path . 'formVirtualMetrics.php';
        } else {
            require_once $path . 'listVirtualMetrics.php';
        }
        break;
    case METRIC_MODIFY:
        if (is_int($vmetricId)) {
            require_once $path . 'formVirtualMetrics.php';
        } else {
            require_once $path . 'listVirtualMetrics.php';
        }
        break;
    case METRIC_ENABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (is_int($vmetricId)) {
                enableVirtualMetricInDB($vmetricId);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listVirtualMetrics.php';
        break;
    case METRIC_DISABLE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (is_int($vmetricId)) {
                disableVirtualMetricInDB($vmetricId);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listVirtualMetrics.php';
        break;
    case METRIC_DUPLICATE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds) && ! in_array(false, $duplicateNbr)) {
                multipleVirtualMetricInDB($selectIds, $duplicateNbr);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listVirtualMetrics.php';
        break;
    case METRIC_DELETE:
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if (! in_array(false, $selectIds)) {
                deleteVirtualMetricInDB($selectIds);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listVirtualMetrics.php';
        break;
    default:
        require_once $path . 'listVirtualMetrics.php';
        break;
}
