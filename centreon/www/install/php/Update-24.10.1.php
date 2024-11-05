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
$versionOfTheUpgrade = 'UPGRADE - 24.10.1: ';
$errorMessage = '';

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
                INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_show`, `is_react`)
                VALUES (92,'Agent configurations',609,60905,50,1,'/configuration/pollers/agent-configurations', :show, '1');
                SQL
        );
        $pearDB->executePreparedQuery($constraintStatement, [':show' => $isCentral === 'yes' ? '1' : '0']);
    }
};

try {
    $createAgentConfiguration($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertAgentConfigurationTopology($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->log(
        4,
        strtoupper($centreonLog::LEVEL_ERROR),
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
