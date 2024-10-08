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
$versionOfTheUpgrade = 'UPGRADE - 23.10.0: ';
$errorMessage = '';


// ------------ CREATE TABLE
$createTablesForDashboard = function(CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(200) NOT NULL,
            `description` text,
            `created_by` int(11) NULL,
            `updated_by` int(11) NULL,
            `created_at` int(11) NOT NULL,
            `updated_at` int(11) NOT NULL,
            `refresh_type` enum('global', 'manual') NOT NULL DEFAULT 'global',
            `refresh_interval` int(11) NULL,
            PRIMARY KEY (`id`),
            KEY `name_index` (`name`),
            CONSTRAINT `contact_created_by`
                FOREIGN KEY (`created_by`)
                REFERENCES `contact` (`contact_id`) ON DELETE SET NULL,
            CONSTRAINT `contact_updated_by`
                FOREIGN KEY (`updated_by`)
                REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_panel` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dashboard_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `widget_type` VARCHAR(200) NOT NULL,
            `widget_settings` text NOT NULL,
            `layout_x` smallint(6) NOT NULL,
            `layout_y` smallint(6) NOT NULL,
            `layout_width` smallint(6) NOT NULL,
            `layout_height` smallint(6) NOT NULL,
            `layout_min_width` smallint(6) NOT NULL,
            `layout_min_height` smallint(6) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `name_index` (`name`),
            CONSTRAINT `parent_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_contact_relation` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dashboard_id` INT UNSIGNED NOT NULL,
            `contact_id` int(11) NOT NULL,
            `role` enum('viewer','editor') NOT NULL DEFAULT 'viewer',
            PRIMARY KEY (`id`),
            KEY `role_index` (`role`),
            UNIQUE KEY `dashboard_contact_relation_unique` (`dashboard_id`,`contact_id`),
            CONSTRAINT `dashboard_contact_relation_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
            CONSTRAINT `dashboard_contact_relation_contact_id`
                FOREIGN KEY (`contact_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_contactgroup_relation` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dashboard_id` INT UNSIGNED NOT NULL,
            `contactgroup_id` int(11) NOT NULL,
            `role` enum('viewer','editor') NOT NULL DEFAULT 'viewer',
            PRIMARY KEY (`id`),
            KEY `role_index` (`role`),
            UNIQUE KEY `dashboard_contactgroup_relation_unique` (`dashboard_id`,`contactgroup_id`),
            CONSTRAINT `dashboard_contactgroup_relation_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
            CONSTRAINT `dashboard_contactgroup_relation_contactgroup_id`
                FOREIGN KEY (`contactgroup_id`)
                REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `version` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );
};

$createTablesForNotificationConfiguration = function(CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(250) NOT NULL,
            `is_activated` BOOLEAN NOT NULL DEFAULT 1,
            `timeperiod_id` INT NOT NULL,
            `hostgroup_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `servicegroup_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `included_service_events` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`timeperiod_id`) REFERENCES `timeperiod` (`tp_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification_message` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `notification_id` INT UNSIGNED NOT NULL,
            `channel` enum('Email','Slack','Sms') DEFAULT 'Email',
            `subject` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `formatted_message` TEXT NOT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `notification_message_notification_id`
                FOREIGN KEY (`notification_id`)
                REFERENCES `notification` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification_user_relation` (
            `notification_id` INT UNSIGNED NOT NULL,
            `user_id` INT NOT NULL,
            UNIQUE KEY `notification_user_relation_unique_index` (`notification_id`,`user_id`),
            CONSTRAINT `notification_user_relation_notification_id`
                FOREIGN KEY (`notification_id`)
                REFERENCES `notification` (`id`) ON DELETE CASCADE,
            CONSTRAINT `notification_user_relation_user_id`
                FOREIGN KEY (`user_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification_hg_relation` (
            `notification_id` INT UNSIGNED NOT NULL,
            `hg_id` INT NOT NULL,
            UNIQUE KEY `notification_hg_relation_unique_index` (`notification_id`,`hg_id`),
            CONSTRAINT `notification_hg_relation_notification_id`
                FOREIGN KEY (`notification_id`)
                REFERENCES `notification` (`id`) ON DELETE CASCADE,
            CONSTRAINT `notification_hg_relation_hg_id`
                FOREIGN KEY (`hg_id`)
                REFERENCES `hostgroup` (`hg_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );

    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification_sg_relation` (
            `notification_id` INT UNSIGNED NOT NULL,
            `sg_id` INT NOT NULL,
            UNIQUE KEY `notification_sg_relation_unique_index` (`notification_id`,`sg_id`),
            CONSTRAINT `notification_sg_relation_notification_id`
                FOREIGN KEY (`notification_id`)
                REFERENCES `notification` (`id`) ON DELETE CASCADE,
            CONSTRAINT `notification_sg_relation_hg_id`
                FOREIGN KEY (`sg_id`)
                REFERENCES `servicegroup` (`sg_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL
    );
};

// ------------ ALTER TABLE
$alterResourceTableStmnt = function (CentreonDB $pearDBO): void {
    $pearDBO->query(
        <<<'SQL'
            ALTER TABLE `resources`
            MODIFY `check_attempts` SMALLINT UNSIGNED,
            MODIFY `max_check_attempts` SMALLINT UNSIGNED
            SQL
    );
};

$alterMetricsTable = function(CentreonDB $pearDBO): void {
    $pearDBO->query(
        <<<'SQL'
            ALTER TABLE `metrics`
            MODIFY COLUMN `metric_name` VARCHAR(1021) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL
            SQL
    );
};

$alterTopologyForFeatureFlag = function(CentreonDB $pearDB): void {
    if (!$pearDB->isColumnExist('topology', 'topology_feature_flag')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `topology`
                ADD COLUMN `topology_feature_flag` varchar(255) DEFAULT NULL
                AFTER `topology_OnClick`
                SQL
        );
    }
};

$alterSecurityTokenTable = function (CentreonDB $pearDB): void {
    if (!$pearDB->isColumnExist('security_authentication_tokens', 'token_name')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `security_authentication_tokens`
                ADD COLUMN `token_name` varchar(255) DEFAULT NULL,
                ADD COLUMN `token_type` enum('auto', 'manual') NOT NULL DEFAULT 'auto',
                ADD COLUMN `creator_id` int(11) DEFAULT NULL,
                ADD COLUMN `creator_name` varchar(255) DEFAULT NULL,
                ADD COLUMN `is_revoked` BOOLEAN NOT NULL DEFAULT 0,
                ADD KEY `security_authentication_tokens_creator_id_fk` (`creator_id`),
                ADD CONSTRAINT `security_authentication_tokens_creator_id_fk` FOREIGN KEY (`creator_id`)
                    REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
                SQL
        );
    }
};

// ------------ INSERT / UPDATE / DELETE
$removeNagiosPathImg = function(CentreonDB $pearDB): void {
    $selectStatement = $pearDB->query("SELECT 1 FROM options WHERE `key`='nagios_path_img'");
    if ($selectStatement->rowCount() > 0) {
        $pearDB->query("DELETE FROM options WHERE `key`='nagios_path_img'");
    }
};

$enableDisabledServiceTemplates = function(CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            UPDATE `service`
                SET service_activate = '1'
            WHERE service_register = '0'
                AND service_activate = '0'
            SQL
    );
};

$enableDisabledHostTemplates = function(CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            UPDATE `host`
                SET host_activate = '1'
            WHERE host_register = '0'
                AND host_activate = '0'
            SQL
    );
};

$updateTopologyForDashboards = function(CentreonDB $pearDB): void {
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Dashboards'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`,
                    `topology_group`, `topology_order`, `topology_feature_flag`, `topology_url_opt`)
                VALUES
                    ('Dashboards', '/home/dashboards', '1', '1', 1, 104, 1, 2, 'dashboard', 'Beta')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`,
                    `topology_show`, `topology_feature_flag`)
                VALUES
                    ('Viewer', '/home/dashboards', '1', '0', 104, 10401, '0', 'dashboard'),
                    ('Creator', '/home/dashboards', '1', '0', 104, 10402, '0', 'dashboard'),
                    ('Administrator', '/home/dashboards', '1', '0', 104, 10403, '0', 'dashboard')
                SQL
        );
    }
};

$updateTopologyForApiTokens = function(CentreonDb $pearDB): void {
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'API Tokens'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_order`, `topology_group`, `topology_url`, `readonly`, `is_react`,
                    `topology_parent`, `topology_page`, `topology_show`)
                VALUES ('API Tokens', 16, 1, null, '1', '0', 5, 515, '0')

                SQL
        );
    }
};

$populateDahsboardTables = function(CentreonDb $pearDB): void {
    if ($pearDB->isColumnExist('dashboard_widgets', 'name')) {
        $statement = $pearDB->query(
            <<<'SQL'
                SELECT 1 FROM `dashboard_widgets` WHERE `name` = 'centreon-widget-generictext'
                SQL
        );
        if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
            $pearDB->query(
                <<<'SQL'
                    INSERT INTO `dashboard_widgets` (`name`, `version`)
                    VALUES
                        ('centreon-widget-generictext', '23.10.0'),
                        ('centreon-widget-singlemetric', '23.10.0'),
                        ('centreon-widget-graph', '23.10.0'),
                        ('centreon-widget-topbottom', '23.10.0')
                    SQL
            );
        }
    }
};

$renameLegacyDashboardInTopology = function (CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            UPDATE `topology` SET `topology_name` = 'Availability'
            WHERE `topology_name` = 'Dashboard' AND `topology_parent` IN (3, 307)
            SQL
    );
};

$createHostCategoriesIndex = function(CentreonDb $pearDB): void {
    if (! $pearDB->isIndexExists('hostcategories', 'level_index')) {
        $pearDB->query('CREATE INDEX `level_index` ON `hostcategories` (`level`)');
    }
};

$createAclResourcesHcRelationsConstraint = function(CentreonDB $pearDB): void {
    if (! $pearDB->isConstraintExists('acl_resources_hc_relations', 'acl_resources_hc_relations_pk')) {
        $pearDB->query(<<<'SQL'
            ALTER TABLE `acl_resources_hc_relations`
                ADD CONSTRAINT `acl_resources_hc_relations_pk` UNIQUE (`hc_id`, `acl_res_id`)
            SQL
        );
    }
};

try {

    $errorMessage = "Couldn't create tables for Dashboards configuration";
    $createTablesForDashboard($pearDB);

    $errorMessage = "Couldn't create tables for Notifications configuration";
    $createTablesForNotificationConfiguration($pearDB);

    $errorMessage = "Couldn't modify resources table";
    $alterResourceTableStmnt($pearDBO);

    $errorMessage = 'Impossible to alter metrics table';
    $alterMetricsTable($pearDBO);

    $errorMessage = 'Impossible to add column topology_feature_flag to topology table';
    $alterTopologyForFeatureFlag($pearDB);

    $errorMessage = 'Unable to alter security_authentication_tokens table';
    $alterSecurityTokenTable($pearDB);

    $errorMessage = 'Unable to create index on hostcategories table';
    $createHostCategoriesIndex($pearDB);

    $errorMessage = 'Unable to create constraints on acl_resources_hc_relations table';
    $createAclResourcesHcRelationsConstraint($pearDB);

    $errorMessage = '';
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $errorMessage = "Unable to Delete nagios_path_img from options table";
    $removeNagiosPathImg($pearDB);

    $errorMessage = 'Unable to activate deactivated service templates';
    $enableDisabledServiceTemplates($pearDB);

    $errorMessage = 'Unable to activate deactivated host templates';
    $enableDisabledHostTemplates($pearDB);

    $errorMessage = "Unable to update topology for Dashboard";
    $updateTopologyForDashboards($pearDB);

    $errorMessage = "Unable to update topology for API Tokens";
    $updateTopologyForApiTokens($pearDB);

    $errorMessage = 'Unable to populate dashboard_widgets table';
    $populateDahsboardTables($pearDB);

    $errorMessage = 'Unable to rename legacy Dashboard topology';
    $renameLegacyDashboardInTopology($pearDB);

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
