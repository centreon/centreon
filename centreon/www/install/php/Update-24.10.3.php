<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.10.3: ';
$errorMessage = '';

$createDashboardThumbnailTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table dashboard_thumbnail_relation';
    $pearDB->executeQuery(
        <<<SQL
            CREATE TABLE IF NOT EXISTS `dashboard_thumbnail_relation` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `dashboard_id` INT UNSIGNED NOT NULL,
              `img_id` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `dashboard_thumbnail_relation_unique` (`dashboard_id`,`img_id`),
              CONSTRAINT `dashboard_thumbnail_relation_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
              CONSTRAINT `dashboard_thumbnail_relation_img_id`
                FOREIGN KEY (`img_id`)
                REFERENCES `view_img` (`img_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
};

// Agent Configuration
$createAgentConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to create agent_configuration table';
    $pearDB->executeQuery(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `agent_configuration` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` enum('telegraf', 'centreon-agent') NOT NULL,
                `name` varchar(255) NOT NULL,
                `configuration` JSON NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name_unique` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $errorMessage = 'Unable to create ac_poller_relation table';
    $pearDB->executeQuery(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ac_poller_relation` (
                `ac_id` INT UNSIGNED NOT NULL,
                `poller_id` INT(11) NOT NULL,
                UNIQUE KEY `rel_unique` (`ac_id`, `poller_id`),
                CONSTRAINT `ac_id_contraint`
                    FOREIGN KEY (`ac_id`)
                    REFERENCES `agent_configuration` (`id`) ON DELETE CASCADE,
                CONSTRAINT `ac_poller_id_contraint`
                    FOREIGN KEY (`poller_id`)
                    REFERENCES `nagios_server` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );
};

$insertAgentConfigurationTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve data from topology table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Agent configurations'
            SQL
    );
    $topologyAlreadyExists = (bool) $statement->fetch(\PDO::FETCH_COLUMN);

    $errorMessage = 'Unable to retrieve data from informations table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT `value` FROM `informations` WHERE `key` = 'isCentral'
            SQL
    );
    $isCentral = $statement->fetch(\PDO::FETCH_COLUMN);

    $errorMessage = 'Unable to insert data into table topology';
    if (false === $topologyAlreadyExists) {
        $constraintStatement = $pearDB->prepareQuery(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_show`, `is_react`)
                VALUES ('Agent configurations',609,60905,50,1,'/configuration/pollers/agent-configurations', :show, '1');
                SQL
        );
        $pearDB->executePreparedQuery($constraintStatement, [':show' => $isCentral === 'yes' ? '1' : '0']);
    }
};

/**
 * Updates the display name and description for the connections_count field in the cb_field table.
 *
 * @param CentreonDB $pearDB
 *
 * @throws Exception
 */
$updateConnectionsCountDescription = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update description in cb_field table';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `cb_field`
            SET `displayname` = "Number of connections to the database",
                `description` = "1: all queries are sent through one connection\n 2: one connection for data_bin and logs, one for the rest\n 3: one connection for data_bin, one for logs, one for the rest"
            WHERE `fieldname` = "connections_count"
        SQL
    );
};

// ACC
$fixNamingOfAccTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update table topology';
    $constraintStatement = $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_name` = 'Additional connector configurations'
            WHERE `topology_url` = '/configuration/additional-connector-configurations'
            SQL
    );
};


try {
    $createAgentConfiguration($pearDB);
    $createDashboardThumbnailTable($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertAgentConfigurationTopology($pearDB);
    $updateConnectionsCountDescription($pearDB);
    $fixNamingOfAccTopology($pearDB);

    $pearDB->commit();
} catch (Exception $e) {
    if ($pearDB->inTransaction()) {
        try {
            $pearDB->rollBack();
        } catch (PDOException $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
                customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                exception: $e
            );
        }
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
