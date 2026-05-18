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
use Adaptation\Database\Query\QueryParameter;
use Adaptation\Database\Query\QueryParameters;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */
/** ------------------------------------- Centreon Storage ------------------------------------- */
$migrateInstanceIdToBigint = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to migrate instance_id columns to BIGINT on centreon_storage';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Migrating instance_id columns to BIGINT on centreon_storage",
    );

    $columnType = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'instances'
              AND COLUMN_NAME = 'instance_id'
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDBO->getDatabaseName()),
        ])
    );

    if (is_string($columnType) && str_starts_with($columnType, 'bigint')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: instance_id is already BIGINT on centreon_storage, skipping",
        );

        return;
    }

    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `acknowledgements` DROP FOREIGN KEY `acknowledgements_ibfk_2`
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `comments` DROP FOREIGN KEY `comments_ibfk_2`
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `downtimes` DROP FOREIGN KEY `downtimes_ibfk_2`
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `hosts` DROP FOREIGN KEY `hosts_ibfk_1`
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `modules` DROP FOREIGN KEY `modules_ibfk_1`
            SQL
    );

    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `instances` MODIFY COLUMN `instance_id` BIGINT NOT NULL
            SQL
    );

    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `acknowledgements` MODIFY COLUMN `instance_id` BIGINT DEFAULT NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `comments` MODIFY COLUMN `instance_id` BIGINT DEFAULT NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `downtimes` MODIFY COLUMN `instance_id` BIGINT DEFAULT NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `hosts` MODIFY COLUMN `instance_id` BIGINT NOT NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `modules` MODIFY COLUMN `instance_id` BIGINT NOT NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_stats` MODIFY COLUMN `instance_id` BIGINT NOT NULL
            SQL
    );

    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `acknowledgements` ADD CONSTRAINT `acknowledgements_ibfk_2` FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `comments` ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `downtimes` ADD CONSTRAINT `downtimes_ibfk_2` FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `hosts` ADD CONSTRAINT `hosts_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE CASCADE
            SQL
    );
    $pearDBO->executeStatement(
        <<<'SQL'
            ALTER TABLE `modules` ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE CASCADE
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully migrated instance_id columns to BIGINT on centreon_storage",
    );
};

/** ------------------------------------- Additional configuration ------------------------------------- */
$addVmwareUpdatedField = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add vmware_updated field into nagios_server table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding vmware_updated field into nagios_server table",
    );
    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'vmware_updated'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Field vmware_updated already exists in nagios_server table, skipping modification",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
            ADD COLUMN `vmware_updated` BOOLEAN NOT NULL DEFAULT 0 AFTER `updated`
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added vmware_updated field into nagios_server table",
    );
};

try {
    // DDL statements for real time database
    $migrateInstanceIdToBigint();

    // DDL statements for configuration database
    $addVmwareUpdatedField();
    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

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
