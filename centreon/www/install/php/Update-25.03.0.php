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

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = '25.03.0';
$errorMessage = '';

// -------------------------------------------- CEIP Agent Information -------------------------------------------- //

/**
 * @param CentreonDB $pearDBO
 *
 * @throws CentreonDbException
 *
 */
$createAgentInformationTable = function (CentreonDB $pearDBO) use (&$errorMessage): void {
    $errorMessage = 'Unable to create table agent_information';
    $pearDBO->executeQuery(
        <<<SQL
            CREATE TABLE IF NOT EXISTS `agent_information` (
                `poller_id` bigint(20) unsigned NOT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `infos` JSON NOT NULL,
            PRIMARY KEY (`poller_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        SQL
    );
};

// -------------------------------------------- Additional Configurations -------------------------------------------- //

/**
 * @param centreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 */
$addConnectorToTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve data from topology table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `topology`
            WHERE `topology_name` = 'Connectors'
                AND `topology_parent` = 6
                AND `topology_page` = 620
        SQL
    );
    $topologyAlreadyExists = (bool) $statement->fetch(\PDO::FETCH_COLUMN);

    $errorMessage = 'Unable to insert into topology';
    if (! $topologyAlreadyExists) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `topology` (
                    `topology_name`,
                    `topology_parent`,
                    `topology_page`,
                    `topology_order`,
                    `topology_group`,
                    `topology_show`
                )
                VALUES ('Connectors', 6, 620, 92, 1, '1')
            SQL
        );
    }
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$changeAccNameInTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update table topology';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_name` = 'Additional Configurations',
                `topology_parent` = 620,
                `topology_page` = 62002
            WHERE `topology_url` = '/configuration/additional-connector-configurations'
        SQL
    );
};

// -------------------------------------------- Connectors configurations -------------------------------------------- //

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$insertAccConnectors = function (CentreonDB $pearDB) use (&$errorMessage): void {

    $errorMessage = 'Unable to select data from connector table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `connector`
            WHERE `name` = 'Centreon Monitoring Agent'
        SQL
    );

    if (false === (bool)$statement->fetch(\PDO::FETCH_COLUMN)) {
        $errorMessage = 'Unable to add data to connector table';
        $pearDB->executeQuery(
            <<<SQL
            REPLACE INTO `connector` (`id`, `name`, `description`, `command_line`, `enabled`, `created`, `modified`) VALUES
            (null,'Centreon Monitoring Agent', 'Centreon Monitoring Agent', 'opentelemetry --processor=centreon_agent --extractor=attributes --host_path=resource_metrics.resource.attributes.host.name --service_path=resource_metrics.resource.attributes.service.name', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
            (null, 'Telegraf', 'Telegraf', 'opentelemetry --processor=nagios_telegraf --extractor=attributes --host_path=resource_metrics.scope_metrics.data.data_points.attributes.host --service_path=resource_metrics.scope_metrics.data.data_points.attributes.service', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
            SQL
        );
    }
};

// -------------------------------------------- Dashboard Panel -------------------------------------------- //
/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 * @return void
 */
$updatePanelsLayout = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to update table dashboard_panel';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `dashboard_panel`
            SET `layout_x` = `layout_x` * 2,
                `layout_width` = `layout_width` * 2
            SQL
    );
};

// -------------------------------------------- Resource Status -------------------------------------------- //
/**
 * @param CentreonDB $pearDBO
 *
 * @throws CentreonDbException
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

$createIndexesForResourceStatus = function (CentreonDB $realtimeDb) use (&$errorMessage): void {
    if (! $realtimeDb->isIndexExists('resources', 'resources_poller_id_index')) {
        $errorMessage = 'Unable to create index resources_poller_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_poller_id_index` ON resources (`poller_id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_id_index')) {
        $errorMessage = 'Unable to create index resources_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_id_index` ON resources (`id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_parent_id_index')) {
        $errorMessage = 'Unable to create index resources_parent_id_index';
        $realtimeDb->exec('CREATE INDEX `resources_parent_id_index` ON resources (`parent_id`)');
    }

    if (! $realtimeDb->isIndexExists('resources', 'resources_enabled_type_index')) {
        $errorMessage = 'Unable to create index resources_enabled_type_index';
        $realtimeDb->exec('CREATE INDEX `resources_enabled_type_index` ON resources (`enabled`, `type`)');
    }

    if (! $realtimeDb->isIndexExists('tags', 'tags_type_name_index')) {
        $errorMessage = 'Unable to create index tags_type_name_index';
        $realtimeDb->exec('CREATE INDEX `tags_type_name_index` ON tags (`type`, `name`(10))');
    }
};

// -------------------------------------------- Broker I/O Configuration -------------------------------------------- //

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$removeConstraintFromBrokerConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    // prevent side effect on the $removeFieldFromBrokerConfiguration function
    $errorMessage = 'Unable to update table cb_list_values';
    if ($pearDB->isConstraintExists('cb_list_values', 'fk_cb_list_values_1')) {
        $pearDB->executeQuery(
            <<<SQL
            ALTER TABLE cb_list_values DROP CONSTRAINT `fk_cb_list_values_1`
            SQL
        );
    }
};

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$removeFieldFromBrokerConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to remove data from cb_field';
    $pearDB->executeQuery(
        <<<SQL
        DELETE FROM cb_field WHERE fieldname = 'check_replication'
        SQL
    );

    $errorMessage = 'Unable to remove data from cfg_centreonbroker_info';
    $pearDB->executeQuery(
        <<<SQL
        DELETE FROM cfg_centreonbroker_info WHERE config_key = 'check_replication'
        SQL
    );
};

// -------------------------------------------- Downtimes -------------------------------------------- //
/**
 * Create index for resources table.
 *
 * @param CentreonDB $realtimeDb
 *
 * @throws CentreonDbException
 */
$createIndexForDowntimes = function (CentreonDB $realtimeDb) use (&$errorMessage): void {
    if (! $realtimeDb->isIndexExists('downtimes', 'downtimes_end_time_index')) {
        $errorMessage = 'Unable to create index for downtimes table';
        $realtimeDb->executeQuery('CREATE INDEX `downtimes_end_time_index` ON downtimes (`end_time`)');
    }
};

try {
    // DDL statements for real time database
    $createAgentInformationTable($pearDBO);
    $addColumnToResourcesTable($pearDBO);
    $createIndexForDowntimes($pearDBO);
    $createIndexesForResourceStatus($pearDBO);

    // DDL statements for configuration database
    $addConnectorToTopology($pearDB);
    $changeAccNameInTopology($pearDB);
    $removeConstraintFromBrokerConfiguration($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertAccConnectors($pearDB);
    $updatePanelsLayout($pearDB);
    $removeFieldFromBrokerConfiguration($pearDB);

    $pearDB->commit();

} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        customContext: [
            'exception' => [
                'error_message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]
        ],
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
            customContext: [
                'error_to_rollback' => $errorMessage,
                'exception' => [
                    'error_message' => $rollbackException->getMessage(),
                    'trace' => $rollbackException->getTraceAsString()
                ]
            ],
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
