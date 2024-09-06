<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.09.0: ';
$errorMessage = '';

// ADDITIONAL CONNECTOR CONFIGURATION
$createAcc = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to create table additional_connector_configuration';
    $pearDB->executeQuery(
        <<<'SQL'
                CREATE TABLE IF NOT EXISTS `additional_connector_configuration` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type` enum('vmware_v6') NOT NULL DEFAULT 'vmware_v6',
                    `name` varchar(255) NOT NULL,
                    `description` text,
                    `parameters` JSON NOT NULL,
                    `created_by` int(11) DEFAULT NULL,
                    `updated_by` int(11) DEFAULT NULL,
                    `created_at` int(11) NOT NULL,
                    `updated_at` int(11) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `name_unique` (`name`),
                    CONSTRAINT `acc_contact_created_by`
                        FOREIGN KEY (`created_by`)
                        REFERENCES `contact` (`contact_id`) ON DELETE SET NULL,
                    CONSTRAINT `acc_contact_updated_by`
                        FOREIGN KEY (`updated_by`)
                        REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL
    );

    $errorMessage = 'Unable to create table acc_poller_relation';
    $pearDB->executeQuery(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `acc_poller_relation` (
                `acc_id` INT UNSIGNED NOT NULL,
                `poller_id` INT(11) NOT NULL,
                UNIQUE KEY `name_unique` (`acc_id`, `poller_id`),
                CONSTRAINT `acc_id_contraint`
                    FOREIGN KEY (`acc_id`)
                    REFERENCES `additional_connector_configuration` (`id`) ON DELETE CASCADE,
                CONSTRAINT `poller_id_contraint`
                    FOREIGN KEY (`poller_id`)
                    REFERENCES `nagios_server` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );
};

$insertIntoTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to insert data into table topology';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Additional Connector Configuration'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_show`)
                VALUES ( 'Additional Connector Configuration', '/configuration/additional-connector-configurations', '1', '1', 6, 618, 1, 1, '0')
                SQL
        );
    }
};

// CLOCK WIDGET
$insertClockWidget = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to select data into table dashboard_widgets';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `dashboard_widgets` WHERE `name` = 'centreon-widget-clock'
            SQL
    );

    $errorMessage = 'Unable to insert data into table dashboard_widgets';
    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `dashboard_widgets` (`name`)
                VALUES ('centreon-widget-clock')
                SQL
        );
    }
};

// BROKER LOGS
$addCentreonBrokerForeignKeyOnBrokerLogTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $constraintStatement = $pearDB->prepareQuery(
        <<<SQL
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_NAME='cfg_centreonbroker_log_ibfk_01'
            AND TABLE_SCHEMA = :db_name
            AND TABLE_NAME = 'cfg_centreonbroker_log'
            SQL
    );

    $pearDB->executePreparedQuery($constraintStatement, ['db_name' => db]);

    if ($constraintStatement->rowCount() === 0) {
        // Clean no more existings broker configuration in log table
        $errorMessage = 'Unable to delete no more existing Broker Configuration';
        $pearDB->executeQuery(
            <<<SQL
            DELETE FROM `cfg_centreonbroker_log`
            WHERE `id_centreonbroker` NOT IN (
                SELECT `config_id`
                FROM `cfg_centreonbroker`
            )
            SQL
        );

        // Add Foreign Key.
        $errorMessage = 'Unable to add foreign key on cfg_centreonbroker_log table';
        $pearDB->executeQuery(
            <<<'SQL'
            ALTER TABLE `cfg_centreonbroker_log`
            ADD CONSTRAINT `cfg_centreonbroker_log_ibfk_01` 
            FOREIGN KEY (`id_centreonbroker`) 
            REFERENCES `cfg_centreonbroker` (`config_id`) 
            ON DELETE CASCADE
            SQL
        );
    }
};

$insertNewBrokerLogs = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to insert new logs in cfg_centreonbroker_log table';
    $pearDB->executeQuery(
        <<<'SQL'
            INSERT INTO `cb_log`
            VALUES
                (11, 'neb'),
                (12, 'rrd'),
                (13, 'grpc'),
                (14, 'influxdb'),
                (15, 'graphite'),
                (16, 'victoria_metrics'),
                (17, 'stats')
            ON DUPLICATE KEY UPDATE `name` = `name`
            SQL
    );
};

$insertNewBrokersLogsRelations = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to find config_id in cfg_centreonbroker table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT `config_id`
            FROM `cfg_centreonbroker`
            SQL
    );
    $configIds = [];
    while (($configId = $statement->fetchColumn()) !== false) {
        $configIds[] = $configId;
    }
    $insertSubQuery = '';

    // Logs 11 to 17 are new logs, 3 is the default "error" level
    foreach ($configIds as $configId) {
        for ($logId = 11; $logId <= 17; $logId++) {
            $insertSubQuery .= "({$configId},{$logId},3),";
        }
    }
    $insertSubQuery = rtrim($insertSubQuery, ',');
    $errorMessage = 'Unable to insert new logs in cfg_centreonbroker_log table';
    $pearDB->executeQuery(
        <<<SQL
            INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`)
            VALUES {$insertSubQuery}
            SQL
    );
};

$removeDashboardFeatureFlagFromTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update topology feature dashboard entries on topology table';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE topology SET topology_feature_flag=NULL WHERE topology_feature_flag='dashboard'
            SQL
    );
};

try {
    $createAcc($pearDB);
    $addCentreonBrokerForeignKeyOnBrokerLogTable($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $insertIntoTopology($pearDB);
    $insertClockWidget($pearDB);
    $insertNewBrokerLogs($pearDB);
    $insertNewBrokersLogsRelations($pearDB);
    $removeDashboardFeatureFlagFromTopology($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
            . ' - Code : ' . (int) $e->getCode()
            . ' - Error : ' . $e->getMessage()
            . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
