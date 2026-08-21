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

require_once _CENTREON_PATH_ . 'www/class/centreonConnector.class.php';
$path = _CENTREON_PATH_ . 'www/include/configuration/configObject/connector/';
require_once $path . 'DB-Func.php';

$connectorObj = new CentreonConnector($pearDB);

// Matches the maxlength of the duplication field in the listing.
const MAX_DUPLICATES_PER_CONNECTOR = 999;

if (isset($_REQUEST['select'])) {
    $select = $_REQUEST['select'];
}

if (isset($_REQUEST['id'])) {
    $connector_id = $_REQUEST['id'];
}

if (isset($_REQUEST['options'])) {
    $options = $_REQUEST['options'];
}

// Access level
$lvl_access = ($centreon->user->access->page($p) == 1) ? 'w' : 'r';

switch ($o) {
    case 'a':
        require_once $path . 'formConnector.php';
        break;
    case 'w':
        require_once $path . 'formConnector.php';
        break;
    case 'c':
        require_once $path . 'formConnector.php';
        break;
    case 's':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($lvl_access == 'w') {
                $myConnector = $connectorObj->read($connector_id);
                $myConnector['enabled'] = '1';
                $connectorObj->update((int) $connector_id, $myConnector);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listConnector.php';
        break;
    case 'u':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($lvl_access == 'w') {
                $myConnector = $connectorObj->read($connector_id);
                $myConnector['enabled'] = '0';
                $connectorObj->update((int) $connector_id, $myConnector);
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listConnector.php';
        break;
    case 'm':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($lvl_access == 'w') {
                $duplicateNbr = $_REQUEST['dupNbr'] ?? $options ?? [];
                if (! is_array($duplicateNbr)) {
                    $duplicateNbr = [];
                }
                $selectedConnectors = array_keys($select ?? []);
                foreach ($selectedConnectors as $connectorId) {
                    // An empty or non-numeric field casts to 0, which would copy
                    // nothing and report nothing: duplicate once in that case.
                    // The upper bound mirrors the three-digit form field, as the
                    // request is the only thing standing between a forged value
                    // and one insert per requested copy.
                    $nb = min(MAX_DUPLICATES_PER_CONNECTOR, max(1, (int) ($duplicateNbr[$connectorId] ?? 1)));
                    $connectorObj->copy($connectorId, $nb);
                }
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listConnector.php';
        break;
    case 'd':
        purgeOutdatedCSRFTokens();
        if (isCSRFTokenValid()) {
            purgeCSRFToken();
            if ($lvl_access == 'w') {
                $selectedConnectors = array_keys($select ?? []);
                foreach ($selectedConnectors as $connectorId) {
                    $connectorObj->delete($connectorId);
                }
            }
        } else {
            unvalidFormMessage();
        }
        require_once $path . 'listConnector.php';
        break;
    default:
        require_once $path . 'listConnector.php';
        break;
}
