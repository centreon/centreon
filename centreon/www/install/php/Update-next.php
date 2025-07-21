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

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = 'xx.xx.x';
$errorMessage = '';

/**
 * Add column `show_deprecated_custom_views` to contact table.
 * @var CentreonDB $pearDB
 */
$addDeprecateCustomViewsToContact =  function () use (&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to add column show_deprecated_custom_views to contact table';
    if (! $pearDB->isColumnExist('contact', 'show_deprecated_custom_views')) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE contact ADD COLUMN show_deprecated_custom_views ENUM('0','1') DEFAULT '0'
                SQL
        );
    }
};

/**
 * Switch Topology Order between Dashboards and Custom Views.
 */
$updateDashboardAndCustomViewsTopology = function () use (&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to update topology of Custom Views';
    $pearDB->update(
        <<<'SQL'
            UPDATE topology SET topology_order = 2, is_deprecated ="1" WHERE topology_name = "Custom Views"
            SQL
    );
    $errorMessage = 'Unable to update topology of Dashboards';
    $pearDB->update(
        <<<'SQL'
            UPDATE topology SET topology_order = 1 WHERE topology_name = "Dashboards"
            SQL
    );
};

/**
 * Set Show Deprecated Custom Views to true by default is there is existing custom views.
 */
$updateContactsShowDeprecatedCustomViews = function () use (&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to retrieve custom views';
    $configuredCustomViews = $pearDB->fetchFirstColumn(
        <<<'SQL'
            SELECT 1 FROM custom_views LIMIT 1
            SQL
    );

    if (true === (bool) $configuredCustomViews) {
        $pearDB->update(
            <<<'SQL'
                UPDATE contact SET show_deprecated_custom_views = '1'
                SQL
        );
    }
};

$updateCfgParameters = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update cfg_nagios table';

    $pearDB->update(
        <<<'SQL'
                UPDATE cfg_nagios
                SET enable_flap_detection = '1',
                    host_down_disable_service_checks = '1'
                WHERE enable_flap_detection != '1'
                   OR host_down_disable_service_checks != '1'
            SQL
    );
};

/** -------------------------------------------- BBDO cfg update -------------------------------------------- */
$bbdoDefaultUpdate = function () use ($pearDB, &$errorMessage): void {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'bbdo_version') !== 1) {
        $errorMessage = "Unable to update 'bbdo_version' column to 'cfg_centreonbroker' table";
        $pearDB->executeStatement('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.1.0"');
    }
};

$bbdoCfgUpdate = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = "Unable to update 'bbdo_version' version in 'cfg_centreonbroker' table";
    $pearDB->update('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.1.0"');
};

$addResourceStatusSearchModeOption = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = "Unable to retrieve 'resource_status_search_mode' option from options table";
    $optionExists = $pearDB->fetchFirstColumn("SELECT 1 FROM options WHERE `key` = 'resource_status_search_mode'");

    $errorMessage = "Unable to insert option 'resource_status_search_mode' option into table options";
    if (false === (bool) $optionExists) {
        $pearDB->insert("INSERT INTO `options` (`key`, `value`) VALUES ('resource_status_search_mode', 1)");
    }
};

/** ------------------------------------------ Services as contacts ------------------------------------------ */
$addServiceFlagToContacts = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update contact table';
    if (! $pearDB->isColumnExist('contact', 'is_service_account')) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `contact`
                    ADD COLUMN `is_service_account` boolean DEFAULT 0 COMMENT 'Indicates if the contact is a service account (ex: centreon-gorgone)'
                SQL
        );
    }
};

// @var mixed $pearDB
$flagContactsAsServiceAccount = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update contact table';
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `contact`
            SET `is_service_account` = 1
            WHERE `contact_name` IN ('centreon-gorgone', 'CBIS', 'centreon-map')
            SQL
    );
};

/**
 * @var CentreonDB $pearDB
 */
$addImageFolderResourceAccessRelationTable = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to create relation table acl_resources_image_folder_relations';

    $pearDB->executeStatement(
        <<<'SQL'
                CREATE TABLE IF NOT EXISTS `acl_resources_image_folder_relations` (
                      `dir_id` int(11) DEFAULT NULL COMMENT 'Unique identifier of the image folder',
                      `acl_res_id` int(11) DEFAULT NULL COMMENT 'Unique identifier of the ACL resource',
                      KEY `dir_id` (`dir_id`),
                      KEY `acl_res_id` (`acl_res_id`),
                      CONSTRAINT `acl_resources_image_folder_relations_ibfk_1` FOREIGN KEY (`dir_id`) REFERENCES `view_img_dir` (`dir_id`) ON DELETE CASCADE,
                      CONSTRAINT `acl_resources_image_folder_relations_ibfk_2` FOREIGN KEY (`acl_res_id`) REFERENCES `acl_resources` (`acl_res_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Relation table between ACL resources and image folders';
            SQL
    );
};

/**
 * @var CentreonDB $pearDB
 */
$addAllImageFoldersColumn = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to add column all_image_folders to acl_resources table';

    if (! $pearDB->isColumnExist('acl_resources', 'all_image_folders')) {
        $pearDB->executeStatement(
            <<<'SQL'
                    ALTER TABLE acl_resources ADD COLUMN `all_image_folders` TINYINT NOT NULL DEFAULT '0' AFTER `all_servicegroups`
                SQL
        );
    }
};

$updateOnPremiseACLs = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to set all_image_folders to 1 for existing acl resource accesses';
    $pearDB->update(
        <<<'SQL'
                UPDATE acl_resources SET all_image_folders = '1' WHERE cloud_specific = '0'
            SQL
    );
};

try {
    $addImageFolderResourceAccessRelationTable();
    $addAllImageFoldersColumn();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateDashboardAndCustomViewsTopology();
    $updateContactsShowDeprecatedCustomViews();
    $updateCfgParameters();
    $bbdoCfgUpdate();
    $addResourceStatusSearchModeOption();
    $flagContactsAsServiceAccount();
    $updateOnPremiseACLs();

    $pearDB->commit();

} catch (Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
