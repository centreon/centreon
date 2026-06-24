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
use Adaptation\Log\LoggerUpgrade;
use App\MonitoringConfiguration\Infrastructure\Service\SnowflakePollerUidGenerator;
use Godruoyi\Snowflake\Snowflake;

require_once __DIR__ . '/../../../bootstrap.php';

$version = '26.07.0';

$errorMessage = '';

$addNewGorgoneCommunicationTypes = function () use ($pearDB, &$errorMessage, $version): void {
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Updating gorgone_communication_type in nagios_server",
        customContext: [
            'New values' => [
                '3' => 'Pull',
                '4' => 'PullWSS',
            ],
        ]
    );
    $errorMessage = 'Unable to update gorgone_communication_type in nagios_server';
    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
              MODIFY COLUMN `gorgone_communication_type`
              enum('1','2','3','4') NOT NULL DEFAULT '1'
              COMMENT '1: SSH, 2: ZMQ, 3: Pull, 4: PullWSS';
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully updated gorgone_communication_type",
    );
};

$updateGorgoneCommunicationTypeForCloudPlatform = function () use ($pearDB, &$errorMessage, $version): void {
    $isCloudPlatform = filter_var(
        $_ENV['IS_CLOUD_PLATFORM'] ?? null,
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    );
    if ($isCloudPlatform !== true) {
        return;
    }

    $errorMessage = "Unable to update gorgone_communication_type to '4' for all pollers";
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Updating gorgone_communication_type to '4' for all pollers",
        customContext: [
            'New values' => [
                '3' => 'Pull',
                '4' => 'PullWSS',
            ],
        ]
    );
    $pearDB->executeStatement(
        <<<'SQL'
                UPDATE nagios_server SET gorgone_communication_type = '4';
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully updated gorgone_communication_type to '4' for all pollers",
    );
};

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
        ['instances', 'instance_id', 'BIGINT UNSIGNED NOT NULL'],
        ['acknowledgements', 'instance_id', 'BIGINT UNSIGNED DEFAULT NULL'],
        ['comments', 'instance_id', 'BIGINT UNSIGNED DEFAULT NULL'],
        ['downtimes', 'instance_id', 'BIGINT UNSIGNED DEFAULT NULL'],
        ['hosts', 'instance_id', 'BIGINT UNSIGNED NOT NULL'],
        ['modules', 'instance_id', 'BIGINT UNSIGNED NOT NULL'],
        ['nagios_stats', 'instance_id', 'BIGINT UNSIGNED NOT NULL'],
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

/** ------------------------------------- Module tables referencing nagios_server (optional) --------------- */
$migrateModuleTableInstanceIds = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to migrate instance_id on module tables';
    $dbName = $pearDB->getConnectionConfig()->getDatabaseNameConfiguration();
    $table = 'mod_auto_disco_inst_rule_relation';
    $constraint = 'mod_auto_disco_inst_rule_relation_fk_1';

    $tableExists = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $dbName),
            QueryParameter::string('table_name', $table),
        ])
    );

    if (! $tableExists) {
        return;
    }

    $columnType = $pearDB->fetchOne(
        <<<'SQL'
            SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name AND COLUMN_NAME = 'instance_id'
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $dbName),
            QueryParameter::string('table_name', $table),
        ])
    );

    if (is_string($columnType) && str_starts_with($columnType, 'bigint')) {
        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Migrating {$table}.instance_id to BIGINT UNSIGNED",
    );

    $fkExists = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name
              AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $dbName),
            QueryParameter::string('table_name', $table),
            QueryParameter::string('constraint_name', $constraint),
        ])
    );

    if ($fkExists) {
        $pearDB->executeStatement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    }

    $pearDB->executeStatement("ALTER TABLE `{$table}` MODIFY COLUMN `instance_id` BIGINT UNSIGNED NOT NULL");

    if ($fkExists) {
        $pearDB->executeStatement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}`"
            . ' FOREIGN KEY (`instance_id`) REFERENCES `nagios_server` (`id`) ON DELETE CASCADE'
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully migrated {$table}.instance_id",
    );
};

/** ------------------------------------- Additional configuration ------------------------------------- */
$addVmwareUpdatedField = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add vmware_updated field into nagios_server table';
    LoggerUpgrade::create()->info($version, 'Adding vmware_updated field into nagios_server table');

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'vmware_updated'
    )) {
        LoggerUpgrade::create()->info(
            $version,
            'Field vmware_updated already exists in nagios_server table, skipping modification'
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
            ADD COLUMN `vmware_updated` BOOLEAN NOT NULL DEFAULT 0 AFTER `updated`
            SQL
    );

    LoggerUpgrade::create()->info($version, 'Successfully added vmware_updated field into nagios_server table');
};

/** -------------------------------------- Poller UUID to UID -------------------------------------- */
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

/** -------------------------------------- Broker event_script logger -------------------------------------- */
$addEventScriptLogger = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add event_script logger to broker configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding 'event_script' logger to broker configuration",
    );

    if (! $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cb_log` WHERE `name` = 'event_script'
            SQL
    )) {
        $pearDB->executeStatement(
            <<<'SQL'
                INSERT INTO `cb_log` (`name`) VALUES ('event_script')
                SQL
        );
    }

    $logId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `id` FROM `cb_log` WHERE `name` = 'event_script'
            SQL
    );
    if ($logId === false) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Failed to retrieve 'event_script' log id from cb_log, skipping cfg_centreonbroker_log population",
        );

        return;
    }

    $errorLevelId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `id` FROM `cb_log_level` WHERE `name` = 'error'
            SQL
    );
    if ($errorLevelId === false) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Failed to retrieve 'error' level id from cb_log_level, skipping cfg_centreonbroker_log population",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`)
            SELECT `config_id`, :id_log, :id_level
            FROM `cfg_centreonbroker` cb
            WHERE NOT EXISTS (
                SELECT 1 FROM `cfg_centreonbroker_log` cbl
                WHERE cbl.`id_centreonbroker` = cb.`config_id`
                  AND cbl.`id_log` = :id_log
            )
            SQL,
        QueryParameters::create([
            QueryParameter::int('id_log', $logId),
            QueryParameter::int('id_level', $errorLevelId),
        ])
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added 'event_script' logger to broker configuration",
    );
};

/** -------------------------------------- Resources performance indexes -------------------------------------- */
$addResourcesPerformanceIndexes = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add performance indexes to resources table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding performance indexes to centreon_storage.resources",
    );

    // Add is_module virtual column (pre-computes the NOT LIKE filter used to exclude internal Module/BAM resources)
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding is_module virtual column to centreon_storage.resources",
    );
    $hasIsModule = $pearDBO->columnExists(
        $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
        'resources',
        'is_module'
    );
    if ($hasIsModule) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Column is_module already exists on resources, skipping",
        );
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `is_module` TINYINT(1) GENERATED ALWAYS AS (
                    CASE WHEN `name` LIKE '\_Module\_%' OR `parent_name` LIKE '\_Module\_BAM%' THEN 1 ELSE 0 END
                ) VIRTUAL COMMENT 'computed flag: 1 if internal Module/BAM resource to exclude from listings'
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully added is_module virtual column to resources",
        );
    }

    // Add/recreate sort index to allow MariaDB to satisfy the default
    // ORDER BY status_ordered DESC, last_status_change DESC without a filesort.
    // Also includes resource_id DESC as a stable tiebreaker for deterministic ordering.
    // Drop and recreate if the old index (without resource_id) exists.
    // Note: descending index columns (DESC keyword) are fully honoured only from MariaDB 10.8+.
    // On MariaDB 10.5–10.7 they are silently treated as ascending; the optimizer can still use
    // the index via a backward scan for DESC ORDER BY, so the index remains beneficial but slightly
    // less efficient than on 10.8+.
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding resources_enabled_status_sort_idx index to centreon_storage.resources",
    );
    $dbRealTime = $pearDBO->getConnectionConfig()->getDatabaseNameRealTime();
    $hasStatusSortIdxWithResourceId = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'resources'
              AND INDEX_NAME = 'resources_enabled_status_sort_idx'
              AND COLUMN_NAME = 'resource_id'
            SQL,
        QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
    );
    if ($hasStatusSortIdxWithResourceId) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Index resources_enabled_status_sort_idx already up to date, skipping",
        );
    } else {
        $hasStatusSortIdx = $pearDBO->fetchOne(
            <<<'SQL'
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = :db_name
                  AND TABLE_NAME = 'resources'
                  AND INDEX_NAME = 'resources_enabled_status_sort_idx'
                SQL,
            QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
        );
        if ($hasStatusSortIdx) {
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: Dropping outdated resources_enabled_status_sort_idx index (missing resource_id column)",
            );
            $pearDBO->executeStatement(
                <<<'SQL'
                    ALTER TABLE `resources` DROP INDEX `resources_enabled_status_sort_idx`
                    SQL
            );
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: Successfully dropped outdated resources_enabled_status_sort_idx index",
            );
        }
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_enabled_status_sort_idx` (`enabled`, `status_ordered` DESC, `last_status_change` DESC, `resource_id` DESC)
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully added resources_enabled_status_sort_idx index to resources",
        );
    }

    // resources_enabled_type_ismodule_idx is a left-prefix of resources_name_search_idx
    // (enabled, type, is_module, poller_id, name) so it is redundant — drop it if present.
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Checking for redundant resources_enabled_type_ismodule_idx index on centreon_storage.resources",
    );
    $hasIsModuleIdx = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'resources'
              AND INDEX_NAME = 'resources_enabled_type_ismodule_idx'
            SQL,
        QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
    );
    if ($hasIsModuleIdx) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Dropping redundant resources_enabled_type_ismodule_idx index (covered by resources_name_search_idx)",
        );
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources` DROP INDEX `resources_enabled_type_ismodule_idx`
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully dropped resources_enabled_type_ismodule_idx index",
        );
    } else {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Index resources_enabled_type_ismodule_idx not present, skipping",
        );
    }

    // Add covering index for status/state filter COUNT queries (status-first for tight seek on status IN (...))
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding resources_status_filter_idx index to centreon_storage.resources",
    );
    $hasStatusFilterIdx = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'resources'
              AND INDEX_NAME = 'resources_status_filter_idx'
            SQL,
        QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
    );
    if ($hasStatusFilterIdx) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Index resources_status_filter_idx already exists on resources, skipping",
        );
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_status_filter_idx` (`enabled`, `status`, `type`, `is_module`, `acknowledged`, `in_downtime`, `status_confirmed`, `poller_id`)
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully added resources_status_filter_idx index to resources",
        );
    }

    // Add covering index for name search queries (includes name column to avoid row reads for REGEXP/LIKE)
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding resources_name_search_idx index to centreon_storage.resources",
    );
    $hasNameSearchIdx = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'resources'
              AND INDEX_NAME = 'resources_name_search_idx'
            SQL,
        QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
    );
    if ($hasNameSearchIdx) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Index resources_name_search_idx already exists on resources, skipping",
        );
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_name_search_idx` (`enabled`, `type`, `is_module`, `poller_id`, `name`)
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully added resources_name_search_idx index to resources",
        );
    }

    // Add index for severity filter queries: allows direct seeks on severity_id instead of a full scan.
    // Used by the non-correlated IN subquery rewrite in DbReadResourceRepository::addSeveritySubRequest().
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding resources_severity_filter_idx index to centreon_storage.resources",
    );
    $hasSeverityFilterIdx = $pearDBO->fetchOne(
        <<<'SQL'
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = 'resources'
              AND INDEX_NAME = 'resources_severity_filter_idx'
            SQL,
        QueryParameters::create([QueryParameter::string('db_name', $dbRealTime)])
    );
    if ($hasSeverityFilterIdx) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Index resources_severity_filter_idx already exists on resources, skipping",
        );
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_severity_filter_idx` (`severity_id`, `enabled`, `is_module`, `type`)
                SQL
        );
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Successfully added resources_severity_filter_idx index to resources",
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully completed performance indexes setup on centreon_storage.resources",
    );
};

/** -------------------------------------- BBDO default version -------------------------------------- */
$updateBbdoVersionDefault = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = "Unable to modify 'bbdo_version' column default in 'cfg_centreonbroker' table";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: modifying 'bbdo_version' column default to '3.1.0'",
    );

    $pearDB->executeStatement('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.1.0"');

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: 'bbdo_version' column default modified successfully",
    );
};

$updateBbdoVersionValues = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'bbdo_version' values in 'cfg_centreonbroker' table";

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating 'bbdo_version' to '3.1.0' in 'cfg_centreonbroker' table",
    );

    $pearDB->executeStatement('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.1.0"');

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: 'bbdo_version' values updated successfully",
    );
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    $addResourcesPerformanceIndexes();
    $migrateInstanceIdToBigint();

    // DDL statements for configuration database
    $addVmwareUpdatedField();
    $migrateModuleTableInstanceIds();
    $renamePollerUuidToUid();
    $addEventScriptLogger();
    $updateBbdoVersionDefault();
    $updateBbdoVersionValues();
    $addNewGorgoneCommunicationTypes();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }
    $updateGorgoneCommunicationTypeForCloudPlatform();

    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    LoggerUpgrade::create()->stepFailure(
        "UPGRADE - {$version}: {$errorMessage}",
        $version,
        'php_script',
        $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $version,
            'php_script_rollback',
            $rollbackException
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
