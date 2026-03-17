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

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------------- BBDO cfg update -------------------------------------------- */
$bbdoDefaultUpdate = function () use ($pearDB, &$errorMessage): void {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'bbdo_version')) {
        $errorMessage = "Unable to update 'bbdo_version' column to 'cfg_centreonbroker' table";
        $pearDB->executeStatement('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.0.1"');
    }
};

$bbdoCfgUpdate = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = "Unable to update 'bbdo_version' version in 'cfg_centreonbroker' table";
    $pearDB->executeStatement('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.0.1"');
};

/** -------------------------------------------- Password encryption -------------------------------------------- */
$addIsEncryptionReadyAsBooleanColumn = function () use ($pearDB, $pearDBO, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'is_encryption_ready' column to boolean type";
    if (
        $pearDB->isColumnExist('nagios_server', 'is_encryption_ready')
        && $pearDB->isColumnExist('nagios_server', 'is_encryption_ready_old') !== 1
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Renaming column 'is_encryption_ready' on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');
    }
    if ($pearDB->isColumnExist('nagios_server', 'is_encryption_ready') !== 1) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Adding column 'is_encryption_ready' of type boolean on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 1');
    }
    if ($pearDB->isColumnExist('nagios_server', 'is_encryption_ready_old')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'nagios_server' table",
        );

        $pearDB->executeStatement(
            <<<'SQL'
                UPDATE nagios_server ns
                SET ns.is_encryption_ready = 0
                WHERE ns.is_encryption_ready_old = '0'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Dropping column 'is_encryption_ready_old' on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` DROP COLUMN `is_encryption_ready_old`');
    }

    if (
        $pearDBO->isColumnExist('instances', 'is_encryption_ready')
        && $pearDBO->isColumnExist('instances', 'is_encryption_ready_old') !== 1
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');
    }
    if ($pearDBO->isColumnExist('instances', 'is_encryption_ready') !== 1) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Adding column 'is_encryption_ready' of type boolean on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 0');
    }
    if ($pearDBO->isColumnExist('instances', 'is_encryption_ready_old')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );
        $pearDBO->executeStatement(
            <<<'SQL'
                UPDATE instances ins
                SET ins.is_encryption_ready = 1
                WHERE ins.is_encryption_ready_old = '1'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Dropping column 'is_encryption_ready_old' on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` DROP COLUMN `is_encryption_ready_old`');
    }
};

try {
    // DDL statements for real time database

    // DDL statements for configuration database
    $bbdoDefaultUpdate();
    $addIsEncryptionReadyAsBooleanColumn();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $bbdoCfgUpdate();

    $pearDB->commitTransaction();
} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
