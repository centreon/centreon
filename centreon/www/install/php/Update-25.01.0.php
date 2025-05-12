<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

$version = '25.01.0';
$errorMessage = '';

// -------------------------------------------- Dashboard -------------------------------------------- //

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$createUserProfileTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile';
    $pearDB->executeQuery(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `user_profile` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `contact_id` INT(11) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_user_profile` (`id`, `contact_id`),
          CONSTRAINT `fk_user_profile_contact_id`
            FOREIGN KEY (`contact_id`)
            REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL
    );
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$createUserProfileFavoriteDashboards = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile_favorite_dashboards';
    $pearDB->executeQuery(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `user_profile_favorite_dashboards` (
          `profile_id` INT UNSIGNED NOT NULL,
          `dashboard_id` INT UNSIGNED NOT NULL,
          CONSTRAINT `fk_user_profile_favorite_dashboards_profile_id`
            FOREIGN KEY (`profile_id`)
            REFERENCES `user_profile` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_user_profile_favorite_dashboards_dashboard_id`
            FOREIGN KEY (`dashboard_id`)
            REFERENCES `dashboard` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL
    );
};

// -------------------------------------------- Dahsboard thumbnail -------------------------------------------- //
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

// -------------------------------------------- Agent configuration -------------------------------------------- //
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

try {
    $createUserProfileTable($pearDB);
    $createUserProfileFavoriteDashboards($pearDB);
    $createAgentConfiguration($pearDB);
    $createDashboardThumbnailTable($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertAgentConfigurationTopology($pearDB);

    $pearDB->commit();
} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (\PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new \Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new \Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
