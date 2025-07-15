<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = 'xx.xx.x';
$errorMessage = '';

/**
 * Add Column Encryption ready for poller configuration
 */
$addIsEncryptionReadyColumn = function() use ($pearDB, &$errorMessage) {
    if ($pearDB->isColumnExist('nagios_server', 'is_encryption_ready') !== 1) {
        $errorMessage = "Unable to add 'is_encryption_ready' column to 'nagios_server' table";
        $pearDB->query("ALTER TABLE `nagios_server` ADD COLUMN `is_encryption_ready` enum('0', '1') NOT NULL DEFAULT '1'");
    }
};

/**
 * Set encryption ready to false by default for all existing pollers to ensure retrocompatibility
 */
$setEncryptionReadyToFalseByDefault = function() use ($pearDB, &$errorMessage) {
    $errorMessage = "Unable to update 'is_encryption_ready' column on 'nagios_server' table";
    $pearDB->executeQuery(<<<'SQL'
        UPDATE nagios_server SET `is_encryption_ready` = '0';
        SQL
    );
};

try {
    $addIsEncryptionReadyColumn();

    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $setEncryptionReadyToFalseByDefault();

    $pearDB->commit();

} catch (Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
