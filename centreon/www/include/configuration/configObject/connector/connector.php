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

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonConnector.class.php';
$path = _CENTREON_PATH_ . 'www/include/configuration/configObject/connector/';
require_once $path . 'DB-Func.php';

$connectorObj = new CentreonConnector($pearDB);

// Caps the duplication count, which comes straight from the request and drives
// one INSERT each. 999 is the largest value the 3-character input accepts.
const MAX_DUPLICATES_PER_CONNECTOR = 999;

// Connector ids the batch could not process; the listing reports the count.
$batchErrors = [];

$select = $_REQUEST['select'] ?? null;

if (isset($_REQUEST['id'])) {
    $connector_id = $_REQUEST['id'];
}

$options = $_REQUEST['options'] ?? null;

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
                    // An empty field or a typed 0 leaves the row out of the batch, as
                    // the legacy page did. Anything else is a mistake, and the
                    // confirmation modal has already promised a count.
                    $requested = $duplicateNbr[$connectorId] ?? '';
                    if (is_numeric($requested)) {
                        $nb = min(MAX_DUPLICATES_PER_CONNECTOR, max(0, (int) $requested));
                    } else {
                        $nb = 0;
                        if ($requested !== '') {
                            $batchErrors[] = $connectorId;
                            Logger::create(LogChannelEnum::WEB)->error(
                                'Connectors: invalid duplication count',
                                ['id' => $connectorId, 'requested' => $requested]
                            );
                        }
                    }
                    if ($nb === 0) {
                        continue;
                    }

                    try {
                        $connectorObj->copy($connectorId, $nb);
                    } catch (Throwable $exception) {
                        // copy() throws mid-loop, so an unguarded failure would leave
                        // a half-applied batch behind a broken page and no log entry.
                        $batchErrors[] = $connectorId;
                        Logger::create(LogChannelEnum::WEB)->error(
                            'Connectors: duplication failed',
                            ['id' => $connectorId, 'copies' => $nb, 'exception' => $exception]
                        );
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
                    try {
                        $connectorObj->delete($connectorId);
                    } catch (Throwable $exception) {
                        $batchErrors[] = $connectorId;
                        Logger::create(LogChannelEnum::WEB)->error(
                            'Connectors: deletion failed',
                            ['id' => $connectorId, 'exception' => $exception]
                        );
                    }
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
