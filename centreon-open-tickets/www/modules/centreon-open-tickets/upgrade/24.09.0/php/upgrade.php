<?php declare(strict_types=1);

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

require __DIR__ . '/../../../php/generate_routes.php';
require_once __DIR__ . '/../../../../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.09.0';
$errorMessage = '';

$insertSubmitTicketTopology = function (CentreonDB $pearDB) use (&$errorMessage): void
{
    $errorMessage = 'Could not insert SubmitTicket form topology';
    $statement = $pearDB->query('SELECT 1 FROM topology WHERE topology_page = 60421');
    if ((bool) $statement->fetchColumn() === false) {
        $pearDB->query(
            <<<'SQL'
                    INSERT INTO topology
                    (
                        topology_name,
                        topology_parent,
                        topology_page,
                        topology_url,
                        topology_show,
                        readonly
                    ) VALUES (
                        'Submit Ticket',
                        604,
                        60421,
                        './modules/centreon-open-tickets/views/rules/submitTicket/action.php',
                        '0',
                        '0'
                    )
                SQL
        );
    }
};

try {
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $insertSubmitTicketTopology($pearDB);
    $pearDB->commit();
} catch (Exception $e) {
    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
