<?php
/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

$versionOfTheUpgrade = 'UPGRADE - 25.03.0: ';
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

try {
    $createAgentInformationTable($pearDBO);
    $addConnectorToTopology($pearDB);
    $changeAccNameInTopology($pearDB);
} catch (CentreonDbException $e) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: $versionOfTheUpgrade . $errorMessage
            . ' - Code : ' . (int) $e->getCode()
            . ' - Error : ' . $e->getMessage(),
        customContext: [
            'exception' => $e->getOptions(),
            'trace' => $e->getTraceAsString(),
        ],
        exception: $e
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}