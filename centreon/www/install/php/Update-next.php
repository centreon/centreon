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
use App\MonitoringConfiguration\Infrastructure\Service\SnowflakePollerUidGenerator;
use Godruoyi\Snowflake\Snowflake;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */
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

$renamePollerUuidToUid = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to rename uuid column to uid on nagios_server';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Renaming uuid column to uid on nagios_server",
    );

    $hasUidColumn = $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'uid'
    );

    if ($hasUidColumn) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Column uid already exists on nagios_server, skipping",
        );

        return;
    }

    $hasUuidColumn = $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'uuid'
    );

    if (! $hasUuidColumn) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `nagios_server`
                    ADD COLUMN `uid` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Snowflake 64-bit unique identifier',
                    ADD UNIQUE KEY `uniq_uid` (`uid`)
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Column uuid not found, added uid column directly on nagios_server",
        );

        generateMissingPollerUids($pearDB, $version);

        return;
    }

    $hasUniqUuidIndex = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'nagios_server'
              AND INDEX_NAME = 'uniq_uuid'
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDB->getDatabaseName()),
        ])
    );

    if ($hasUniqUuidIndex) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `nagios_server` DROP INDEX `uniq_uuid`
                SQL
        );
    }

    // Existing VARCHAR uuid values are incompatible with the new BIGINT type
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `nagios_server` SET `uuid` = NULL
            SQL
    );

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
                CHANGE COLUMN `uuid` `uid` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Snowflake 64-bit unique identifier'
            SQL
    );

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
                ADD UNIQUE KEY `uniq_uid` (`uid`)
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully renamed uuid column to uid on nagios_server",
    );

    generateMissingPollerUids($pearDB, $version);
};

/**
 * Generates Snowflake UIDs for existing pollers that have none, then makes the column NOT NULL.
 */
function generateMissingPollerUids(ConnectionInterface $pearDB, string $version): void
{
    $snowflake = new Snowflake(0, 0);
    $snowflake->setStartTimeStamp(SnowflakePollerUidGenerator::CUSTOM_EPOCH_MS);

    $pollerIds = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT `id` FROM `nagios_server` WHERE `uid` IS NULL
            SQL
    );

    foreach ($pollerIds as $row) {
        $pearDB->executeStatement(
            <<<'SQL'
                UPDATE `nagios_server` SET `uid` = :uid WHERE `id` = :id
                SQL,
            QueryParameters::create([
                QueryParameter::int('uid', (int) $snowflake->id()),
                QueryParameter::int('id', (int) $row['id']),
            ])
        );
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
                MODIFY COLUMN `uid` BIGINT UNSIGNED NOT NULL COMMENT 'Snowflake 64-bit unique identifier'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Generated UIDs for " . count($pollerIds) . ' existing pollers, column is now NOT NULL',
    );
}

try {
    // DDL statements for real time database

    // DDL statements for configuration database
    $addVmwareUpdatedField();
    $renamePollerUuidToUid();

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
