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

    // acc_configuration
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `acc_configuration` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the ACC configuration',
                `acc_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to additional_connector_configuration',
                `port` INT UNSIGNED NOT NULL DEFAULT 443 COMMENT 'Port number for VMware connector (default 443)',
                `created_at` INT NOT NULL COMMENT 'Creation timestamp',
                `updated_at` INT NOT NULL COMMENT 'Last update timestamp',
                PRIMARY KEY (`id`),
                UNIQUE KEY `acc_id_unique` (`acc_id`),
                FOREIGN KEY (`acc_id`) REFERENCES `additional_connector_configuration`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            SQL
    );

    // acc_configuration_item
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `acc_configuration_item` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the vCenter configuration item',
                `acc_conf_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to acc_configuration',
                `name` VARCHAR(255) NOT NULL COMMENT 'Name of the vCenter',
                `url` VARCHAR(255) NOT NULL COMMENT 'vCenter server URL',
                `username` VARCHAR(255) NOT NULL COMMENT 'Username for vCenter authentication',
                `password` VARCHAR(255) NOT NULL COMMENT 'Encrypted password for vCenter authentication',
                `created_at` INT NOT NULL COMMENT 'Creation timestamp',
                `updated_at` INT NOT NULL COMMENT 'Last update timestamp',
                PRIMARY KEY (`id`),
                KEY `idx_acc_conf` (`acc_conf_id`),
                UNIQUE KEY `name_config_unique` (`acc_conf_id`, `name`),
                FOREIGN KEY (`acc_conf_id`) REFERENCES `acc_configuration`(`id`) ON DELETE CASCADE
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

    // initial count
    $initialCountResult = $pearDB->query(
        <<<'SQL'
            SELECT COUNT(*) as count
            FROM `acc_configuration`
            SQL
    )->fetch(PDO::FETCH_ASSOC);
    $initialCount = (int) $initialCountResult['count'];

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
        // for idempotency
        if (empty($acc['parameters'])) {
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

        // Insert into acc_configuration
        $insertConfig = $pearDB->prepare(
            <<<'SQL'
                INSERT INTO `acc_configuration` (acc_id, port, created_at, updated_at)
                VALUES (:acc_id, :port, :created_at, :updated_at)
                SQL
        );

        $insertConfig->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $insertConfig->bindValue(':port', (int) ($parameters['port'] ?? 443), PDO::PARAM_INT);
        $insertConfig->bindValue(':created_at', (int) $acc['created_at'], PDO::PARAM_INT);
        $insertConfig->bindValue(':updated_at', (int) $acc['updated_at'], PDO::PARAM_INT);
        $insertConfig->execute();

        $configId = (int) $pearDB->lastInsertId();
        $migratedCount++;

        $clearParams = $pearDB->prepare(
            <<<'SQL'
                UPDATE `additional_connector_configuration`
                SET `parameters` = NULL
                WHERE `id` = :acc_id
                SQL
        );
        $clearParams->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $clearParams->execute();

        // Insert vcenters
        if (isset($parameters['vcenters']) && is_array($parameters['vcenters'])) {
            $insertVcenter = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO `acc_configuration_item`
                    (acc_conf_id, name, url, username, password, created_at, updated_at)
                    VALUES (:acc_conf_id, :name, :url, :username, :password, :created_at, :updated_at)
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
                        customContext: [
                            'vcenter' => $vcenter,
                        ]
                    );
                    continue;
                }

                $insertVcenter->bindValue(':acc_conf_id', $configId, PDO::PARAM_INT);
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

    // Verify migration accounting for existing records (idempotency-safe)
    $finalCountResult = $pearDB->query(
        <<<'SQL'
            SELECT COUNT(*) as count
            FROM `acc_configuration`
            SQL
    )->fetch(PDO::FETCH_ASSOC);
    $finalCount = (int) $finalCountResult['count'];
    $expectedCount = $initialCount + $migratedCount;

    if ($finalCount !== $expectedCount) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [acc] Migration verification failed for acc_configuration table",
            customContext: [
                'left_out_acc_ids' => $leftOutAccs,
                'left_out_acc_data' => array_filter($accRecords, function ($acc) use ($leftOutAccs) {
                    return in_array($acc['id'], $leftOutAccs, true);
                }),
            ]
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Successfully migrated {$migratedCount} ACC configurations and {$vcenterCount} vcenters",
    );

    if ($leftOutAccs === []) {
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
            message: "UPGRADE - {$version}: [acc] Parameters column retained in additional_connector_configuration table due to unmigrated records",
        );
    }
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $fixDuplicateHostGroupTopology();
    $createAccTables();
    $migrateAccJsonToTables();

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
