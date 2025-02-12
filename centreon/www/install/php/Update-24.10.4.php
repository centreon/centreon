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

$versionOfTheUpgrade = 'UPGRADE - 24.10.4: ';
$errorMessage = '';

/**
 * @param CentreonDB $pearDB
 *
 * @throws PDOException
 * @return void
 */
$addAllContactsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add the colum all_contacts to the table acl_groups';
    if (! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contacts')) {
        $pearDB->exec('ALTER TABLE `acl_groups` ADD COLUMN `all_contacts` TINYINT(1) DEFAULT 0 NOT NULL');
    }
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws PDOException
 * @return void
 */
$addAllContactGroupsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add the colum all_contact_groups to the table acl_groups';
    if (! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contact_groups')) {
        $pearDB->exec('ALTER TABLE `acl_groups` ADD COLUMN `all_contact_groups` TINYINT(1) DEFAULT 0 NOT NULL');
    }
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDBException
 * @return void
 */
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

/**
 * @param CentreonDB $pearDB
 *
 * @throws PDOException
 * @return void
 */
$createUserProfileTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile';
    $pearDB->exec(
        <<<'SQL'
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
 * @throws PDOException
 * @return void
 */
$createUserProfileFavoriteDashboards = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile_favorite_dashboards';
    $pearDB->exec(
        <<<'SQL'
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

// -------------------------------------------- Resource Status -------------------------------------------- //

/**
 * @param CentreonDB $pearDBO
 *
 * @throws PDOException
 * @return void
 */
$addColumnToResourcesTable = function (CentreonDB $pearDBO) use (&$errorMessage): void {
    $errorMessage = 'Unable to add column flapping to table resources';
    if (! $pearDBO->isColumnExist('resources', 'flapping')) {
        $pearDBO->exec(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `flapping` TINYINT(1) NOT NULL DEFAULT 0
            SQL
        );
    }

    $errorMessage = 'Unable to add column percent_state_change to table resources';
    if (! $pearDBO->isColumnExist('resources', 'percent_state_change')) {
        $pearDBO->exec(
            <<<'SQL'
                ALTER TABLE `resources`
                ADD COLUMN `percent_state_change` FLOAT DEFAULT NULL
            SQL
        );
    }
};

try {
    // DDL statements for real time database
    $addColumnToResourcesTable($pearDBO);

    // DDL statements for configuration database
    $addAllContactsColumnToAclGroups($pearDB);
    $addAllContactGroupsColumnToAclGroups($pearDB);
    $createUserProfileTable($pearDB);
    $createUserProfileFavoriteDashboards($pearDB);

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $fixNamingOfAccTopology($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "{$versionOfTheUpgrade} error while rolling back the upgrade operation",
            customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            exception: $e
        );
    }

    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage,
        customContext: ['error_message' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
