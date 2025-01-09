<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

$centreonLog = CentreonLog::create();

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 25.01.0: ';
$errorMessage = '';

$addColumnToResourcesTable = function (CentreonDB $pearDBO) use (&$errorMessage): void {
    $errorMessage = 'Unable to add column flapping to table resources';
    if (! $pearDBO->isColumnExist('resources', 'flapping')) {
        $pearDBO->exec(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `flapping` TINYINT(1) NOT NULL DEFAULT 0
            SQL
        );
    }

    $errorMessage = 'Unable to add column percent_state_change to table resources';
    if (! $pearDBO->isColumnExist('resources', 'percent_state_change')) {
        $pearDBO->exec(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `percent_state_change` FLOAT DEFAULT NULL
            SQL
        );
    }
};

try {
    $addColumnToResourcesTable($pearDBO);
} catch (\PDOException $e) {
    try {
        if ($pearDBO->inTransaction()) {
            $pearDBO->rollBack();
        }
    } catch (\PDOException $rollbackException) {
        $rollbackErrorMessage = $versionOfTheUpgrade . "error while rolling back transaction : {$rollbackException->getMessage()}";
        $centreonLog->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: $rollbackErrorMessage,
            customContext: [
                'exception_message' => $rollbackException->getMessage(),
                'pdo_error_code' => $rollbackException->getCode(),
                'pdo_error_info' => $rollbackException->errorInfo,
                'trace' => $rollbackException->getTraceAsString(),
            ],
            exception: $rollbackException
        );

        throw new Exception($rollbackErrorMessage, (int) $rollbackException->getCode(), $rollbackException);
    }

    $centreonLog->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString(),
        customContext: [
            'exception_message' => $e->getMessage(),
            'pdo_error_code' => $e->getCode(),
            'pdo_error_info' => $e->errorInfo,
            'trace' => $e->getTraceAsString(),
        ],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
