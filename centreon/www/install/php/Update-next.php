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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

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
    $dbName = $pearDBO->getDatabaseName();

    $isColumnBigint = static function (string $table, string $column) use ($pearDBO, $dbName): bool {
        $type = $pearDBO->fetchOne(
            <<<'SQL'
                SELECT COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :db_name
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                SQL,
            QueryParameters::create([
                QueryParameter::string('db_name', $dbName),
                QueryParameter::string('table_name', $table),
                QueryParameter::string('column_name', $column),
            ])
        );

        return is_string($type) && str_starts_with($type, 'bigint');
    };

    $foreignKeyExists = static function (string $table, string $constraint) use ($pearDBO, $dbName): bool {
        return (bool) $pearDBO->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = :db_name
                  AND TABLE_NAME = :table_name
                  AND CONSTRAINT_NAME = :constraint_name
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                LIMIT 1
                SQL,
            QueryParameters::create([
                QueryParameter::string('db_name', $dbName),
                QueryParameter::string('table_name', $table),
                QueryParameter::string('constraint_name', $constraint),
            ])
        );
    };

    $columnsToMigrate = [
        ['instances', 'instance_id', 'BIGINT NOT NULL'],
        ['acknowledgements', 'instance_id', 'BIGINT DEFAULT NULL'],
        ['comments', 'instance_id', 'BIGINT DEFAULT NULL'],
        ['downtimes', 'instance_id', 'BIGINT DEFAULT NULL'],
        ['hosts', 'instance_id', 'BIGINT NOT NULL'],
        ['modules', 'instance_id', 'BIGINT NOT NULL'],
        ['nagios_stats', 'instance_id', 'BIGINT NOT NULL'],
    ];

    $foreignKeys = [
        ['acknowledgements', 'acknowledgements_ibfk_2', 'FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL'],
        ['comments', 'comments_ibfk_2', 'FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL'],
        ['downtimes', 'downtimes_ibfk_2', 'FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE SET NULL'],
        ['hosts', 'hosts_ibfk_1', 'FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE CASCADE'],
        ['modules', 'modules_ibfk_1', 'FOREIGN KEY (`instance_id`) REFERENCES `instances` (`instance_id`) ON DELETE CASCADE'],
    ];

    $pendingColumns = array_filter($columnsToMigrate, fn ($col) => ! $isColumnBigint($col[0], $col[1]));
    if ($pendingColumns === []) {
        $missingFks = array_filter($foreignKeys, fn ($fk) => ! $foreignKeyExists($fk[0], $fk[1]));
        if ($missingFks === []) {
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: instance_id is already BIGINT on all centreon_storage tables, skipping",
            );

            return;
        }
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Migrating instance_id columns to BIGINT on centreon_storage",
    );

    foreach ($foreignKeys as [$table, $constraint]) {
        if ($foreignKeyExists($table, $constraint)) {
            $pearDBO->executeStatement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    foreach ($pendingColumns as [$table, $column, $definition]) {
        $pearDBO->executeStatement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$definition}");
    }

    foreach ($foreignKeys as [$table, $constraint, $definition]) {
        if (! $foreignKeyExists($table, $constraint)) {
            $pearDBO->executeStatement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` {$definition}");
        }
    }

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
