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

$createAcc = function(CentreonDB $pearDB) use(&$errorMessage): void {
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

$insertIntoTopology = function(CentreonDB $pearDB) use(&$errorMessage): void {
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

$insertClockWidget = function(CentreonDB $pearDB) use(&$errorMessage): void {
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

try {
    $createAcc($pearDB);

    // Tansactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $insertIntoTopology($pearDB);
    $insertClockWidget($pearDB);

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
