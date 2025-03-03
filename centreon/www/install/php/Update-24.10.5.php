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

$versionOfTheUpgrade = 'UPGRADE - 24.10.5: ';
$errorMessage = '';

// -------------------------------------------- CEIP Agent Information -------------------------------------------- //

/**
 * @param CentreonDB $pearDBO
 *
 * @throws PDOException
 * @return void
 */
$createAgentInformationTable = function (CentreonDB $pearDBO) use (&$errorMessage): void {
    $errorMessage = 'Unable to create table agent_information';
    $pearDBO->exec(
        <<<SQL
            CREATE TABLE IF NOT EXISTS `agent_information` (
                `poller_id` bigint(20) unsigned NOT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `infos` JSON NOT NULL,
            PRIMARY KEY (`poller_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        SQL
    );
};

try {
    // DDL statements for real time database
    $createAgentInformationTable($pearDBO);

    // DDL statements for configuration database


    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $pearDB->commit();
} catch (\Exception $e) {
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
            customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            exception: $e
        );
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
