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

$downtime_id = filter_var(
    $_GET['hg_id'] ?? $_POST['hg_id'] ?? null,
    FILTER_VALIDATE_INT
);

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?: $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?: $cP;

$path = './include/monitoring/recurrentDowntime/';

require_once './class/centreonDowntime.class.php';
$downtime = new CentreonDowntime($pearDB);

require_once './include/common/common-Func.php';

if (isset($_POST['o1'], $_POST['o2'])) {
    if ($_POST['o1'] != '') {
        $o = $_POST['o1'];
    }
    if ($_POST['o2'] != '') {
        $o = $_POST['o2'];
    }
}

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

if (isset($_GET['period_form']) || isset($_GET['period']) && $o == '') {
    require_once $path . 'ajaxForms.php';
} else {
    switch ($o) {
        case 'a':
            require_once $path . 'formDowntime.php';
            break; // Add a downtime
        case 'w':
            require_once $path . 'formDowntime.php';
            break; // Watch a downtime
        case 'c':
            require_once $path . 'formDowntime.php';
            break; // Modify a downtime
        case 'e':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($downtime_id) {
                    $downtime->enable($downtime_id);
                }
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break; // Activate a service
        case 'ms':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiEnable($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break;
        case 'u':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                if ($downtime_id) {
                    $downtime->disable($downtime_id);
                }
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break; // Desactivate a service
        case 'mu':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiDisable($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break;
        case 'm':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->duplicate($select ?? [], $dupNbr);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break; // Duplicate n services
        case 'd':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                $downtime->multiDelete(isset($select) ? array_keys($select) : []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listDowntime.php';
            break; // Delete n services
        default:
            require_once $path . 'listDowntime.php';
            break;
    }
}
