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
$versionOfTheUpgrade = 'UPGRADE - 24.04.0: ';
$errorMessage = '';

// ------------ Widgets database updates ---------------- //
$updateWidgetModelsTable = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to add column is_internal to table widget_models';
    if (!$pearDB->isColumnExist('widget_models', 'is_internal')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `widget_models`
                ADD COLUMN `is_internal` BOOLEAN NOT NULL DEFAULT FALSE
                AFTER `version`
                SQL
        );
    }

    $errorMessage = 'Unable to modify column version on table widget_models';
    $pearDB->query(
        <<<'SQL'
            ALTER TABLE `widget_models`
            MODIFY COLUMN `version` varchar(255) DEFAULT NULL
            SQL
    );
};

$installCoreWidgets = function(): void {
    $moduleService = \Centreon\LegacyContainer::getInstance()[\CentreonModule\ServiceProvider::CENTREON_MODULE];
    $widgets = $moduleService->getList(null, false, null, ['widget']);
    foreach ($widgets['widget'] as $widget) {
        if ($widget->isInternal()) {
            $moduleService->install($widget->getId(), 'widget');
        }
    }
};

$setCoreWidgetsToInternal = function(CentreonDB $pearDB): void {
    $pearDB->query(
        <<<'SQL'
            UPDATE `widget_models`
            SET version = NULL, is_internal = TRUE
            WHERE `directory` IN (
                'engine-status',
                'global-health',
                'graph-monitoring',
                'grid-map',
                'httploader',
                'host-monitoring',
                'hostgroup-monitoring',
                'live-top10-cpu-usage',
                'live-top10-memory-usage',
                'ntopng-listing',
                'service-monitoring',
                'servicegroup-monitoring',
                'single-metric',
                'tactical-overview'
            )
            SQL
    );
};

$dropColumnVersionFromDashboardWidgetsTable = function(CentreonDB $pearDB): void {
    if($pearDB->isColumnExist('dashboard_widgets', 'version')) {
        $pearDB->query(
            <<<'SQL'
                    ALTER TABLE dashboard_widgets
                    DROP COLUMN `version`
                SQL
        );
    }
};

$insertResourcesTableWidget = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to insert centreon-widget-resourcestable in dashboard_widgets';
    $statement = $pearDB->query("SELECT 1 from dashboard_widgets WHERE name = 'centreon-widget-resourcestable'");
    if((bool) $statement->fetchColumn() === false) {
        $pearDB->query(
            <<<SQL
                INSERT INTO dashboard_widgets (`name`)
                VALUES ('centreon-widget-resourcestable')
                SQL
        );
    }
};

$updateTopologyForApiTokens = function(CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = "Could not update topology for API tokens";
    $pearDB->query(
            <<<'SQL'
                UPDATE `topology`
                SET topology_url = '/administration/api-token', is_react = '1', topology_show='1'
                WHERE `topology_name` = 'API Tokens'
                SQL
    );
};

// ------------ Resource Access Management database updates ---------------- //
$insertTopologyForResourceAccessManagement = function(CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to insert topology for Resource Access Management';
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

$addCloudDescriptionToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add cloud_description column to acl_groups table';
    if (! $pearDB->isColumnExist('acl_groups', 'cloud_description')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_description` TEXT DEFAULT NULL'
        );
    }
};

$addCloudSpecificToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add cloud_specific column to acl_groups table';
    if (! $pearDB->isColumnExist('acl_groups', 'cloud_specific')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_specific` BOOLEAN NOT NULL DEFAULT 0'
        );
    }
};

$addCloudSpecificToAclResources = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add cloud_specific column to acl_resources table';
    if (! $pearDB->isColumnExist('acl_resources', 'cloud_specific')) {
        $pearDB->query(
            'ALTER TABLE `acl_resources` ADD COLUMN `cloud_specific` BOOLEAN NOT NULL DEFAULT 0'
        );
    }
};

$createDatasetFiltersTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to create dataset_filters configuration table';
    $pearDB->query(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `dataset_filters` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `parent_id` int(11) DEFAULT NULL,
            `type` enum('host', 'hostgroup', 'host_category', 'servicegroup', 'service_category', 'meta_service', 'service') DEFAULT NULL,
            `acl_resource_id` int(11) DEFAULT NULL,
            `acl_group_id` int(11) DEFAULT NULL,
            `resource_ids` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `acl_resources_dataset_relations` FOREIGN KEY (`acl_resource_id`) REFERENCES `acl_resources` (`acl_res_id`) ON DELETE CASCADE,
            CONSTRAINT `acl_groups_dataset_relations` FOREIGN KEY (`acl_group_id`) REFERENCES `acl_groups` (`acl_group_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        SQL
    );
};

$alterTypeDefinitionDatasetFilterTable = function (CentreonDB $pearDB) use (&$errorMessage): void
{
    $errorMessage = 'Unable to change `type` from enum to varchar in dataset_filters table';
    $pearDB->query(
        <<<SQL
            ALTER TABLE `dataset_filters` MODIFY COLUMN `type` VARCHAR(255) DEFAULT NULL
        SQL
    );
};

$insertGroupMonitoringWidget = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to insert centreon-widget-groupmonitoring in dashboard_widgets';
    $statement = $pearDB->query("SELECT 1 from dashboard_widgets WHERE name = 'centreon-widget-groupmonitoring'");
    if((bool) $statement->fetchColumn() === false) {
        $pearDB->query(
            <<<SQL
                INSERT INTO dashboard_widgets (`name`)
                VALUES ('centreon-widget-groupmonitoring')
                SQL
        );
    }
};

$addDefaultValueforTaskTable = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to alter created_at for task table';
    $pearDB->query("ALTER TABLE task MODIFY COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
};

$insertStatusChartWidget = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to insert centreon-widget-statuschart in dashboard_widgets';
    $statement = $pearDB->query("SELECT 1 from dashboard_widgets WHERE name = 'centreon-widget-statuschart'");
    if((bool) $statement->fetchColumn() === false) {
        $pearDB->query(
            <<<SQL
                INSERT INTO dashboard_widgets (`name`)
                VALUES ('centreon-widget-statuschart')
                SQL
        );
    }
};

$removeBetaTagFromDashboards = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to remove the dashboard beta tag';
    $pearDB->query(
        <<<SQL
            UPDATE topology
            SET topology_url_opt=NULL
            WHERE topology_name='Dashboards'
            AND topology_url_opt = 'Beta'
            SQL
    );
};

$updateHostGroupsTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update topology_url_substitute to NULL for host group configuration page (60102)';
    $pearDB->query(
        <<<SQL
            UPDATE `topology` SET `topology_url_substitute` = NULL WHERE `topology_page` = 60102
            SQL
    );
};

$updateDatasetFilterResourceIdsColumn = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to change resourceIds column type from VARCHAR to TEXT';
    $pearDB->query(
        <<<'SQL'
            ALTER TABLE `dataset_filters` MODIFY COLUMN `resource_ids` TEXT DEFAULT NULL
            SQL
    );
};

$addAllContactsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void
{
    $errorMessage = 'Unable to add the colum all_contacts to the table acl_groups';
    if(! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contacts')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_groups` ADD COLUMN `all_contacts` TINYINT(1) DEFAULT 0 NOT NULL
            SQL
        );
    }
};

$addAllContactGroupsColumnToAclGroups = function (CentreonDB $pearDB) use (&$errorMessage): void
{
    $errorMessage = 'Unable to add the colum all_contact_groups to the table acl_groups';
    if(! $pearDB->isColumnExist(table: 'acl_groups', column: 'all_contact_groups')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `acl_groups` ADD COLUMN `all_contact_groups` TINYINT(1) DEFAULT 0 NOT NULL
            SQL
        );
    }
};

try {
    $updateWidgetModelsTable($pearDB);

    $errorMessage = "Unable to install core widgets";
    $installCoreWidgets();

    $dropColumnVersionFromDashboardWidgetsTable($pearDB);

    $addCloudSpecificToAclGroups($pearDB);
    $addCloudDescriptionToAclGroups($pearDB);
    $addCloudSpecificToAclResources($pearDB);
    $createDatasetFiltersTable($pearDB);
    $alterTypeDefinitionDatasetFilterTable($pearDB);

    $addDefaultValueforTaskTable($pearDB);
    $updateDatasetFilterResourceIdsColumn($pearDB);
    $addAllContactsColumnToAclGroups($pearDB);
    $addAllContactGroupsColumnToAclGroups($pearDB);

    // Tansactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $errorMessage = "Could not set core widgets to internal";
    $setCoreWidgetsToInternal($pearDB);
    $insertResourcesTableWidget($pearDB);
    $insertGroupMonitoringWidget($pearDB);
    $insertStatusChartWidget($pearDB);

    $insertTopologyForResourceAccessManagement($pearDB);

    $updateTopologyForApiTokens($pearDB);

    $removeBetaTagFromDashboards($pearDB);

    $updateHostGroupsTopology($pearDB);

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
