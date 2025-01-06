<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.10.1: ';
$errorMessage = '';

/**
 * Updates the display name and description for the connections_count field in the cb_field table.
 * @param CentreonDB $pearDB
 * @throws Exception
 */
$updateConnectionsCountDescription = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update description in cb_field table';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `cb_field`
            SET `displayname` = "Number of connections to the database",
                `description` = "1: all queries are sent through one connection\n 2: one connection for data_bin and logs, one for the rest\n 3: one connection for data_bin, one for logs, one for the rest"
            WHERE `fieldname` = "connections_count"
        SQL
    );
};

try {
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateConnectionsCountDescription($pearDB);

    $pearDB->commit();
} catch (Exception $e) {

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
