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
$path = './include/configuration/configObject/timeperiod/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

$tp_id = filter_var(
    $_GET['tp_id'] ?? $_POST['tp_id'] ?? null,
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

switch ($o) {
    case 'a': // Add a Timeperiod
    case 'w': // Watch a Timeperiod
    case 'c': // Modify a Timeperiod
        require_once $path . 'formTimeperiod.php';
        break;
    case 's': // Show Timeperiod
        require_once $path . 'renderTimeperiod.php';
        break;
    case 'm': // Duplicate n Timeperiods
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            multipleTimeperiodInDB(
                is_array($select) ? $select : [],
                is_array($dupNbr) ? $dupNbr : []
            );
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listTimeperiod.php';
        break;
    case 'd': // Delete n Timeperiods
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            deleteTimePeriodInAPI(is_array($select) ? array_keys($select) : []);
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listTimeperiod.php';
        break;
    default:
        require_once $path . 'listTimeperiod.php';
        break;
}
