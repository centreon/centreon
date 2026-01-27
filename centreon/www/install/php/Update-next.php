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

require_once _CENTREON_PATH_ . '/bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------- Host Group Topology -------------------------------------- */
$fixDuplicateHostGroupTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to fix duplicate Host Groups topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Fixing duplicate Host Groups menu entries",
    );

    $pearDB->update(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/hosts/groups',
                `is_react` = '1',
                `topology_show` = '1'
            WHERE `topology_page` = 60102
            SQL
    );

    // Remove duplicate topology entry 60105 introduced by 25.05 migration
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` = 60105
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Successfully removed duplicate Host Groups topology entry",
    );
};

/** -------------------------------------- ACC -------------------------------------- */
$createAccTables = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create Additional Connector Configuration tables';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Creating Additional Connector Configuration tables",
    );

    // Add port column to additional_connector_configuration if not exists
    $pearDB->query(
        <<<'SQL'
            ALTER TABLE `additional_connector_configuration`
            ADD COLUMN `port` INT UNSIGNED NOT NULL DEFAULT 443 AFTER `type`;
            SQL
    );

    // acc_item
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `acc_item` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the vCenter configuration item',
                `acc_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to additional_connector_configuration',
                `name` VARCHAR(255) NOT NULL COMMENT 'Name of the vCenter',
                `url` VARCHAR(255) NOT NULL COMMENT 'vCenter server URL',
                `username` VARCHAR(255) NOT NULL COMMENT 'Username for vCenter authentication',
                `password` VARCHAR(255) NOT NULL COMMENT 'Encrypted password for vCenter authentication',
                `created_at` INT NOT NULL COMMENT 'Creation timestamp',
                `updated_at` INT NOT NULL COMMENT 'Last update timestamp',
                PRIMARY KEY (`id`),
                KEY `idx_acc_id` (`acc_id`),
                UNIQUE KEY `acc_item_unique` (`acc_id`, `id`),
                FOREIGN KEY (`acc_id`) REFERENCES `additional_connector_configuration`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Successfully created Additional Connector Configuration tables",
    );
};

$migrateAccJsonToTables = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to migrate Additional Connector Configuration from JSON to relational tables';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Starting migration of ACC parameters from JSON to relational tables",
    );

    // Get all ACC records with JSON parameters
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT *
            FROM `additional_connector_configuration`
            WHERE type = 'vmware_v6'
            SQL
    );

    $accRecords = $statement->fetchAll(PDO::FETCH_ASSOC);
    $migratedCount = 0;
    $vcenterCount = 0;
    $leftOutAccs = [];

    foreach ($accRecords as $acc) {
        // Check if parameters column exists and has data
        if (! isset($acc['parameters']) || $acc['parameters'] === null || $acc['parameters'] === '' || $acc['parameters'] === '{}') {
            continue;
        }

        // Check if this ACC has already been migrated to acc_item (by acc_id)
        $checkExisting = $pearDB->prepare(
            <<<'SQL'
                SELECT COUNT(*) FROM `acc_item` WHERE acc_id = :acc_id
                SQL
        );
        $checkExisting->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $checkExisting->execute();
        $existingCount = (int) $checkExisting->fetchColumn();

        if ($existingCount > 0) {
            // Already migrated, just clear the parameters
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] ACC ID {$acc['id']} already migrated, clearing parameters only",
            );

            $clearParams = $pearDB->prepare(
                <<<'SQL'
                    UPDATE `additional_connector_configuration`
                    SET `parameters` = '{}'
                    WHERE `id` = :acc_id
                    SQL
            );
            $clearParams->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
            $clearParams->execute();
            continue;
        }

        $parameters = json_decode($acc['parameters'], true);

        if (! is_array($parameters)) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] Skipping ACC ID {$acc['id']} - invalid JSON parameters",
            );
            $leftOutAccs[] = $acc['id'];
            continue;
        }

        // Set port in additional_connector_configuration
        $updatePort = $pearDB->prepare(
            <<<'SQL'
                UPDATE `additional_connector_configuration`
                SET `port` = :port
                WHERE `id` = :acc_id
                SQL
        );
        $updatePort->bindValue(':port', (int) ($parameters['port'] ?? 443), PDO::PARAM_INT);
        $updatePort->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $updatePort->execute();

        $migratedCount++;

        $clearParams = $pearDB->prepare(
            <<<'SQL'
                UPDATE `additional_connector_configuration`
                SET `parameters` = '{}'
                WHERE `id` = :acc_id
                SQL
        );
        $clearParams->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $clearParams->execute();

        // Insert vcenters
        if (isset($parameters['vcenters']) && is_array($parameters['vcenters'])) {
            $insertVcenter = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO `acc_item`
                    (acc_id, name, url, username, password, created_at, updated_at)
                    VALUES (:acc_id, :name, :url, :username, :password, :created_at, :updated_at)
                    SQL
            );

            foreach ($parameters['vcenters'] as $vcenter) {
                if (
                    ! isset($vcenter['name']) || $vcenter['name'] === ''
                    || ! isset($vcenter['url']) || $vcenter['url'] === ''
                    || ! isset($vcenter['username']) || $vcenter['username'] === ''
                    || ! isset($vcenter['password']) || $vcenter['password'] === ''
                ) {
                    CentreonLog::create()->warning(
                        logTypeId: CentreonLog::TYPE_UPGRADE,
                        message: "UPGRADE - {$version}: [acc] Skipping vcenter for ACC ID {$acc['id']} - missing mandatory fields",
                    );
                    continue;
                }

                $insertVcenter->bindValue(':acc_id', $acc['id'], PDO::PARAM_INT);
                $insertVcenter->bindValue(':name', $vcenter['name'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':url', $vcenter['url'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':username', $vcenter['username'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':password', $vcenter['password'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':created_at', (int) $acc['created_at'], PDO::PARAM_INT);
                $insertVcenter->bindValue(':updated_at', (int) $acc['updated_at'], PDO::PARAM_INT);
                $insertVcenter->execute();
                $vcenterCount++;
            }
        }
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Successfully migrated {$migratedCount} ACC configurations and {$vcenterCount} vcenters",
    );
};

$dropParametersColumn = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to drop parameters column';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Checking if parameters column should be dropped",
    );

    // First, check if the parameters column exists
    $columnExists = $pearDB->query(
        <<<'SQL'
            SELECT COUNT(*) as count
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'additional_connector_configuration'
            AND COLUMN_NAME = 'parameters'
            SQL
    );
    $columnExistsResult = $columnExists->fetch(PDO::FETCH_ASSOC);

    if ((int) $columnExistsResult['count'] === 0) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [acc] Parameters column already dropped from additional_connector_configuration table",
        );

        return;
    }

    // Check if there are any ACCs with non-empty parameters
    $checkParams = $pearDB->query(
        <<<'SQL'
            SELECT COUNT(*) as count
            FROM `additional_connector_configuration`
            WHERE type = 'vmware_v6'
            AND parameters IS NOT NULL
            AND JSON_LENGTH(parameters) > 0
            SQL
    );
    $result = $checkParams->fetch(PDO::FETCH_ASSOC);
    $remainingCount = (int) $result['count'];

    if ($remainingCount === 0) {
        try {
            $pearDB->query(
                <<<'SQL'
                    ALTER TABLE `additional_connector_configuration`
                    DROP COLUMN `parameters`
                    SQL
            );
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] Dropped parameters column from additional_connector_configuration table",
            );
        } catch (PDOException $e) {
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] parameters column from additional_connector_configuration already dropped",
            );
        }
    } else {
        CentreonLog::create()->warning(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [acc] Parameters column retained in additional_connector_configuration table due to {$remainingCount} unmigrated records",
        );
    }
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here
    $createAccTables();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $fixDuplicateHostGroupTopology();
    $migrateAccJsonToTables();

    if ($pearDB->isTransactionActive()) {
        $pearDB->commitTransaction();
    }

    $dropParametersColumn();

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
