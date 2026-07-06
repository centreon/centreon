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

require_once __DIR__ . '/../../../bootstrap.php';

$version = '24.10.29';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

/** -------------------------------------- Resources performance indexes -------------------------------------- */
$addResourcesPerformanceIndexes = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add performance indexes to resources table';
    LoggerUpgrade::create()->info($version, 'Adding performance indexes to centreon_storage.resources');

    // Add is_module virtual column (pre-computes the NOT LIKE filter used to exclude internal Module/BAM resources)
    LoggerUpgrade::create()->info($version, 'Adding is_module virtual column to centreon_storage.resources');
    $hasIsModule = $pearDBO->columnExists(
        $pearDBO->getConnectionConfig()->getDatabaseNameRealTime(),
        'resources',
        'is_module'
    );
    if ($hasIsModule) {
        LoggerUpgrade::create()->info($version, 'Column is_module already exists on resources, skipping');
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `is_module` TINYINT(1) GENERATED ALWAYS AS (
                    CASE WHEN `name` LIKE '\_Module\_%' OR `parent_name` LIKE '\_Module\_BAM%' THEN 1 ELSE 0 END
                ) VIRTUAL COMMENT 'computed flag: 1 if internal Module/BAM resource to exclude from listings'
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully added is_module virtual column to resources');
    }

    // Add/recreate sort index to allow MariaDB to satisfy the default
    // ORDER BY status_ordered DESC, last_status_change DESC without a filesort.
    // Also includes resource_id DESC as a stable tiebreaker for deterministic ordering.
    // Drop and recreate if the old index (without resource_id) exists.
    // Note: descending index columns (DESC keyword) are fully honoured only from MariaDB 10.8+.
    // On MariaDB 10.5–10.7 they are silently treated as ascending; the optimizer can still use
    // the index via a backward scan for DESC ORDER BY, so the index remains beneficial but slightly
    // less efficient than on 10.8+.
    LoggerUpgrade::create()->info($version, 'Adding resources_enabled_status_sort_idx index to centreon_storage.resources');
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
        LoggerUpgrade::create()->info($version, 'Index resources_enabled_status_sort_idx already up to date, skipping');
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
            LoggerUpgrade::create()->info($version, 'Dropping outdated resources_enabled_status_sort_idx index (missing resource_id column)');
            $pearDBO->executeStatement(
                <<<'SQL'
                    ALTER TABLE `resources` DROP INDEX `resources_enabled_status_sort_idx`
                    SQL
            );
            LoggerUpgrade::create()->info($version, 'Successfully dropped outdated resources_enabled_status_sort_idx index');
        }
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_enabled_status_sort_idx` (`enabled`, `status_ordered` DESC, `last_status_change` DESC, `resource_id` DESC)
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully added resources_enabled_status_sort_idx index to resources');
    }

    // resources_enabled_type_ismodule_idx is a left-prefix of resources_name_search_idx
    // (enabled, type, is_module, poller_id, name) so it is redundant — drop it if present.
    LoggerUpgrade::create()->info($version, 'Checking for redundant resources_enabled_type_ismodule_idx index on centreon_storage.resources');
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
        LoggerUpgrade::create()->info($version, 'Dropping redundant resources_enabled_type_ismodule_idx index (covered by resources_name_search_idx)');
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources` DROP INDEX `resources_enabled_type_ismodule_idx`
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully dropped resources_enabled_type_ismodule_idx index');
    } else {
        LoggerUpgrade::create()->info($version, 'Index resources_enabled_type_ismodule_idx not present, skipping');
    }

    // Add covering index for status/state filter COUNT queries (status-first for tight seek on status IN (...))
    LoggerUpgrade::create()->info($version, 'Adding resources_status_filter_idx index to centreon_storage.resources');
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
        LoggerUpgrade::create()->info($version, 'Index resources_status_filter_idx already exists on resources, skipping');
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_status_filter_idx` (`enabled`, `status`, `type`, `is_module`, `acknowledged`, `in_downtime`, `status_confirmed`, `poller_id`)
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully added resources_status_filter_idx index to resources');
    }

    // Add covering index for name search queries (includes name column to avoid row reads for REGEXP/LIKE)
    LoggerUpgrade::create()->info($version, 'Adding resources_name_search_idx index to centreon_storage.resources');
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
        LoggerUpgrade::create()->info($version, 'Index resources_name_search_idx already exists on resources, skipping');
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_name_search_idx` (`enabled`, `type`, `is_module`, `poller_id`, `name`)
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully added resources_name_search_idx index to resources');
    }

    // Add index for severity filter queries: allows direct seeks on severity_id instead of a full scan.
    // Used by the non-correlated IN subquery rewrite in DbReadResourceRepository::addSeveritySubRequest().
    LoggerUpgrade::create()->info($version, 'Adding resources_severity_filter_idx index to centreon_storage.resources');
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
        LoggerUpgrade::create()->info($version, 'Index resources_severity_filter_idx already exists on resources, skipping');
    } else {
        $pearDBO->executeStatement(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD INDEX `resources_severity_filter_idx` (`severity_id`, `enabled`, `is_module`, `type`)
                SQL
        );
        LoggerUpgrade::create()->info($version, 'Successfully added resources_severity_filter_idx index to resources');
    }

    LoggerUpgrade::create()->info($version, 'Successfully completed performance indexes setup on centreon_storage.resources');
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    $addResourcesPerformanceIndexes();
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
