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

$versionOfTheUpgrade = 'UPGRADE - 24.10.6: ';
$errorMessage = '';

// -------------------------------------------- Connectors configurations -------------------------------------------- //

/**
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$insertAccConnectors = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add data to connector table';
    $pearDB->executeQuery(
        <<<SQL
        INSERT INTO `connector` (`id`, `name`, `description`, `command_line`, `enabled`, `created`, `modified`) VALUES
        (null,'Centreon Monitoring Agent', 'Centreon Monitoring Agent', 'opentelemetry --processor=centreon_agent --extractor=attributes --host_path=resource_metrics.resource.attributes.host.name --service_path=resource_metrics.resource.attributes.service.name', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
        (null, 'Telegraf', 'Telegraf', 'opentelemetry --processor=nagios_telegraf --extractor=attributes --host_path=resource_metrics.scope_metrics.data.data_points.attributes.host --service_path=resource_metrics.scope_metrics.data.data_points.attributes.service', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
        SQL
    );
};

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

// -------------------------------------------- Resource Status -------------------------------------------- //

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


try {
    $createIndexForDowntimes($pearDBO);
    $createIndexesForResourceStatus($pearDBO);

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertAccConnectors($pearDB);
    $addConnectorToTopology($pearDB);
    $changeAccNameInTopology($pearDB);

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
