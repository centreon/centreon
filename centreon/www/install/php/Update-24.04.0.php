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

require_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.04.0: ';
$errorMessage = '';

// ------------ INSERT / UPDATE / DELETE
$insertTopologyForResourceAccessManagement = function(CentreonDB $pearDB): void {
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Resource Access Management'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`,
                    `topology_order`, `topology_group`, `topology_feature_flag`)
                VALUES
                    ( 'Resource Access Management', '/administration/resource-access/rules', '1', '1', 502, 50206, 1, 1,
                    'resource_access_management');
                SQL
        );
    }
};

/**
 * $errorMessage is passed by reference to handle errors on each query instead of a global error on the function call.
 */
$createDashboardsPlaylistTables = function(CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to create table: dashboard_playlist';
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_playlist` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `rotation_time` TINYINT UNSIGNED NOT NULL,
                `created_at` INT(11) UNSIGNED NOT NULL,
                `updated_at` INT(11) UNSIGNED NULL,
                `created_by` INT NULL,
                `updated_by` INT NULL,
                `is_public` TINYINT(1) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY (`name`),
                CONSTRAINT `dashboard_playlist_author_id`
                FOREIGN KEY (`created_by`)
                REFERENCES `contact` (`contact_id`) ON DELETE SET NULL,
                CONSTRAINT `dashboard_playlist_editor_id`
                FOREIGN KEY (`updated_by`)
                REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $errorMessage = 'Unable to create table: dashboard_playlist_relation';
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_playlist_relation` (
                `dashboard_id` INT UNSIGNED NOT NULL,
                `playlist_id` INT UNSIGNED NOT NULL,
                `order` INT(11) NOT NULL,
                UNIQUE KEY(`dashboard_id`, `playlist_id`),
                CONSTRAINT `dashboard_playlist_relation_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
                CONSTRAINT `dashboard_playlist_relation_playlist_id`
                FOREIGN KEY (`playlist_id`)
                REFERENCES `dashboard_playlist` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $errorMessage = 'Unable to create table: dashboard_playlist_contact_relation';
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_playlist_contact_relation` (
                `contact_id` INT(11) NOT NULL,
                `playlist_id` INT UNSIGNED NOT NULL,
                UNIQUE KEY(`contact_id`, `playlist_id`),
                CONSTRAINT `dashboard_playlist_contact_relation_contact_id`
                FOREIGN KEY (`contact_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE,
                CONSTRAINT `dashboard_playlist_contact_relation_playlist_id`
                FOREIGN KEY (`playlist_id`)
                REFERENCES `dashboard_playlist` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $errorMessage = 'Unable to create table: dashboard_playlist_contactgroup_relation';
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_playlist_contactgroup_relation` (
                `contactgroup_id` INT(11) NOT NULL,
                `playlist_id` INT UNSIGNED NOT NULL,
                UNIQUE KEY(`contactgroup_id`, `playlist_id`),
                CONSTRAINT `dashboard_playlist_contactgroup_relation_contactgroup_id`
                FOREIGN KEY (`contactgroup_id`)
                REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE,
                CONSTRAINT `dashboard_playlist_contactgroup_relation_playlist_id`
                FOREIGN KEY (`playlist_id`)
                REFERENCES `dashboard_playlist` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );
};

$alterAclGroupsTable = function (CentreonDB $pearDB): void {
    if (! $pearDB->isColumnExist('acl_groups', 'cloud_description')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_description` TEXT DEFAULT NULL'
        );
    }

    if (! $pearDB->isColumnExist('acl_groups', 'cloud_specific')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_specific` BOOLEAN NOT NULL DEFAULT 0'
        );
    }
};

$alterAclResourceGroupRelation = function (CentreonDB $pearDB) {
    if (! $pearDB->isColumnExist('acl_res_group_relations', 'order')) {
        $pearDB->query(
            'ALTER TABLE acl_res_group_relations ADD COLUMN `order` INT NOT NULL DEFAULT 0'
        );
    }
};

try {
    $createDashboardsPlaylistTables($pearDB);

    // Tansactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $errorMessage = 'Unable to insert topology for Resource Access Management';
    $insertTopologyForResourceAccessManagement($pearDB);

    $errorMessage = 'Unable to add columns cloud_description and cloud_specific to acl_groups table';
    $alterAclGroupsTable($pearDB);

    $errorMessage = 'Unable to add column order to acl_res_group_relations table';
    $alterAclResourceGroupRelation($pearDB);
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
