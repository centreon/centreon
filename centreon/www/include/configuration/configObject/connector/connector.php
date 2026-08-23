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

// Mirrors the three-digit duplication field: the request is the only thing
// between a forged value and one insert per requested copy.
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
                $selectedConnectors = is_array($select) ? array_keys($select) : [];
                foreach ($selectedConnectors as $connectorId) {
                    // An untouched field means one copy; a typed 0 means none, as
                    // it did before, so a row can still be left out of a batch.
                    $requested = $duplicateNbr[$connectorId] ?? '';
                    $nb = ($requested === '' || ! is_numeric($requested))
                        ? 1
                        : min(MAX_DUPLICATES_PER_CONNECTOR, max(0, (int) $requested));
                    if ($nb > 0) {
                        $connectorObj->copy($connectorId, $nb);
                    }
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
                $selectedConnectors = is_array($select) ? array_keys($select) : [];
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
