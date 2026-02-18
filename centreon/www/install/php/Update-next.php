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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

require_once _CENTREON_PATH_ . '/bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------- Global macros -------------------------------------- */
$rewordingResourceToGlobalMacro = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to update Resource to Global macros';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [global_macro] Rewording Resource to Global macros",
    );
    $pearDB->update(
        <<<'SQL'
            UPDATE topology
            SET topology_name = 'Global macros'
            WHERE topology_name = 'Resources'
            SQL
    );
};
/** -------------------------------------- Host Group Topology -------------------------------------- */
$fixDuplicateHostGroupTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to fix duplicate Host Groups topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Fixing duplicate Host Groups menu entries",
    );

    $pearDB->update(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/hosts/groups',
                `is_react` = '1',
                `topology_show` = '1'
            WHERE `topology_page` = 60102
            SQL
    );

    // Remove duplicate topology entry 60105 introduced by 25.05 migration
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` = 60105
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Successfully removed duplicate Host Groups topology entry",
    );
};

/** -------------------------------------- Broker Instances CMA fields -------------------------------------- */
$updateInstancesTable = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add CMA certificate fields to broker instances table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [broker instances] Adding CMA certificate fields to broker instances table",
    );

    if (
        $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_sha'
        )
        || $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_cn'
        )
        || $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_peremption'
        )
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [broker instances] CMA certificate fields already exist in broker instances table, skipping",
        );

        return;
    }

    $pearDBO->query(
        <<<'SQL'
            ALTER TABLE `instances`
            ADD COLUMN `cma_certificate_sha` VARCHAR(255) DEFAULT NULL COMMENT 'CMA certificate fingerprint',
            ADD COLUMN `cma_certificate_cn` VARCHAR(255) DEFAULT NULL COMMENT 'CMA certificate host name',
            ADD COLUMN `cma_certificate_peremption` INT(11) DEFAULT NULL COMMENT 'CMA certificate peremption timestamp'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [broker instances] Successfully added CMA certificate fields to broker instances table",
    );
};

/** -------------------------------------- ACC -------------------------------------- */
$createAccTables = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create Additional Connector Configuration tables';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Creating Additional Connector Configuration tables",
    );

    // Add port column to additional_connector_configuration if not exists
    if (
        ! $pearDB->columnExists(
            $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
            'additional_connector_configuration',
            'port'
        )
    ) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `additional_connector_configuration`
                ADD COLUMN `port` INT UNSIGNED NOT NULL DEFAULT 443 AFTER `type`;
                SQL
        );
    }

    // acc_item
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `acc_item` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the vCenter configuration item',
                `acc_id` INT UNSIGNED NOT NULL COMMENT 'Foreign key to additional_connector_configuration',
                `name` VARCHAR(255) NOT NULL COMMENT 'Name of the vCenter',
                `url` VARCHAR(255) NOT NULL COMMENT 'vCenter server URL',
                `username` VARCHAR(255) NOT NULL COMMENT 'Username for vCenter authentication',
                `password` VARCHAR(255) NOT NULL COMMENT 'Encrypted password for vCenter authentication',
                `created_at` INT NOT NULL COMMENT 'Creation timestamp',
                `updated_at` INT NOT NULL COMMENT 'Last update timestamp',
                PRIMARY KEY (`id`),
                KEY `idx_acc_id` (`acc_id`),
                UNIQUE KEY `acc_item_unique` (`acc_id`, `id`),
                FOREIGN KEY (`acc_id`) REFERENCES `additional_connector_configuration`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Successfully created Additional Connector Configuration tables",
    );
};

$migrateAccJsonToTables = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to migrate Additional Connector Configuration from JSON to relational tables';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Starting migration of ACC parameters from JSON to relational tables",
    );

    // Get all ACC records with JSON parameters
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT *
            FROM `additional_connector_configuration`
            WHERE type = 'vmware_v6'
            SQL
    );

    $accRecords = $statement->fetchAll(PDO::FETCH_ASSOC);
    $migratedCount = 0;
    $vcenterCount = 0;
    $leftOutAccs = [];

    foreach ($accRecords as $acc) {
        // Check if parameters column exists and has data
        if (! isset($acc['parameters']) || $acc['parameters'] === null || $acc['parameters'] === '' || $acc['parameters'] === '{}') {
            continue;
        }

        // Check if this ACC has already been migrated to acc_item (by acc_id)
        $checkExisting = $pearDB->prepare(
            <<<'SQL'
                SELECT COUNT(*) FROM `acc_item` WHERE acc_id = :acc_id
                SQL
        );
        $checkExisting->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $checkExisting->execute();
        $existingCount = (int) $checkExisting->fetchColumn();

        if ($existingCount > 0) {
            // Already migrated, just clear the parameters
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] ACC ID {$acc['id']} already migrated, clearing parameters only",
            );

            $clearParams = $pearDB->prepare(
                <<<'SQL'
                    UPDATE `additional_connector_configuration`
                    SET `parameters` = '{}'
                    WHERE `id` = :acc_id
                    SQL
            );
            $clearParams->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
            $clearParams->execute();
            continue;
        }

        $parameters = json_decode($acc['parameters'], true);

        if (! is_array($parameters)) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] Skipping ACC ID {$acc['id']} - invalid JSON parameters",
            );
            $leftOutAccs[] = $acc['id'];
            continue;
        }

        // Set port in additional_connector_configuration
        $updatePort = $pearDB->prepare(
            <<<'SQL'
                UPDATE `additional_connector_configuration`
                SET `port` = :port
                WHERE `id` = :acc_id
                SQL
        );
        $updatePort->bindValue(':port', (int) ($parameters['port'] ?? 443), PDO::PARAM_INT);
        $updatePort->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $updatePort->execute();

        $migratedCount++;

        $clearParams = $pearDB->prepare(
            <<<'SQL'
                UPDATE `additional_connector_configuration`
                SET `parameters` = '{}'
                WHERE `id` = :acc_id
                SQL
        );
        $clearParams->bindValue(':acc_id', (int) $acc['id'], PDO::PARAM_INT);
        $clearParams->execute();

        // Insert vcenters
        if (isset($parameters['vcenters']) && is_array($parameters['vcenters'])) {
            $insertVcenter = $pearDB->prepare(
                <<<'SQL'
                    INSERT INTO `acc_item`
                    (acc_id, name, url, username, password, created_at, updated_at)
                    VALUES (:acc_id, :name, :url, :username, :password, :created_at, :updated_at)
                    SQL
            );

            foreach ($parameters['vcenters'] as $vcenter) {
                if (
                    ! isset($vcenter['name']) || $vcenter['name'] === ''
                    || ! isset($vcenter['url']) || $vcenter['url'] === ''
                    || ! isset($vcenter['username']) || $vcenter['username'] === ''
                    || ! isset($vcenter['password']) || $vcenter['password'] === ''
                ) {
                    CentreonLog::create()->warning(
                        logTypeId: CentreonLog::TYPE_UPGRADE,
                        message: "UPGRADE - {$version}: [acc] Skipping vcenter for ACC ID {$acc['id']} - missing mandatory fields",
                    );
                    continue;
                }

                $insertVcenter->bindValue(':acc_id', $acc['id'], PDO::PARAM_INT);
                $insertVcenter->bindValue(':name', $vcenter['name'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':url', $vcenter['url'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':username', $vcenter['username'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':password', $vcenter['password'], PDO::PARAM_STR);
                $insertVcenter->bindValue(':created_at', (int) $acc['created_at'], PDO::PARAM_INT);
                $insertVcenter->bindValue(':updated_at', (int) $acc['updated_at'], PDO::PARAM_INT);
                $insertVcenter->execute();
                $vcenterCount++;
            }
        }
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Successfully migrated {$migratedCount} ACC configurations and {$vcenterCount} vcenters",
    );
};

$dropParametersColumn = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to drop parameters column';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [acc] Checking if parameters column should be dropped",
    );

    // First, check if the parameters column exists
    if (! $pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'additional_connector_configuration',
        'parameters'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [acc] Parameters column already dropped from additional_connector_configuration table",
        );

        return;
    }

    // Check if there are any ACCs with non-empty parameters
    $checkParams = $pearDB->query(
        <<<'SQL'
            SELECT COUNT(*) as count
            FROM `additional_connector_configuration`
            WHERE parameters IS NOT NULL
            AND JSON_LENGTH(parameters) > 0
            SQL
    );
    $result = $checkParams->fetch(PDO::FETCH_ASSOC);
    $remainingCount = (int) $result['count'];

    if ($remainingCount === 0) {
        try {
            $pearDB->query(
                <<<'SQL'
                    ALTER TABLE `additional_connector_configuration`
                    DROP COLUMN `parameters`
                    SQL
            );
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] Dropped parameters column from additional_connector_configuration table",
            );
        } catch (PDOException $e) {
            CentreonLog::create()->info(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: [acc] parameters column from additional_connector_configuration already dropped",
            );
        }
    } else {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [acc] Parameters column retained in additional_connector_configuration table due to {$remainingCount} unmigrated records",
        );
    }
};

<<<<<<< HEAD
/** -------------------------------------- Command redesign updates-------------------------------------- */
$addNewCommandPage = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add new command page topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding new command page to topology",
    );
    $alreadyExist = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM topology WHERE topology_page = 60808
            SQL
    );
    if ($alreadyExist) {
        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url_substitute`)
            VALUES ( 'Commands', '/configuration/commands', '1', '1', 608, 60808, 1, 1, './include/configuration/configObject/command/command.php')
            SQL
    );
};

$updateCommandsParentTopology = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update parent commands topology';
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/commands', `is_react` = '1'
            WHERE `topology_page` = 608
            SQL
    );
};

$deleteCommandsTopologyRights = function (int $aclTopologyId) use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to delete from table acl_topology_relations';
    $pearDB->executeStatement(
        <<<'SQL'
            DELETE acl_topology_relations
            FROM acl_topology_relations
            WHERE acl_topology_relations.acl_topo_id = :acl_topo_id
            AND acl_topology_relations.topology_topology_id IN (
                SELECT topology_id FROM topology WHERE topology_page IN (60801, 60802, 60803, 60807)
            )
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
        ])
    );
};

$insertNewCommandsTopologyRights = function (int $aclTopologyId) use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to insert into table acl_topology_relations';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right)
            VALUES (:acl_topo_id, (SELECT topology_id from topology where topology_page = 60808), :access_right)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
            QueryParameter::int('access_right', 1),
        ])
    );
};

$getOrCreateActionGroup = function (int $aclGroupId, array &$actionGroupRelations) use ($pearDB, &$errorMessage): array {
    $actionGroup = null;
    foreach ($actionGroupRelations as $relation) {
        if ($relation['acl_group_id'] === $aclGroupId) {
            $actionGroup = $relation;
            break;
        }
    }

    if ($actionGroup !== null) {

        return $actionGroup;
    }

    $errorMessage = 'Unable to create a new acl_action';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_actions (acl_action_name, acl_action_activate)
            VALUES (CONCAT((SELECT acl_group_name FROM acl_groups WHERE acl_group_id = :acl_group_id), '_actions'), '1')
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_group_id', $aclGroupId),
        ])
    );
    $actionId = (int) $pearDB->lastInsertId();

    $errorMessage = 'Unable to link a new acl_action to an acl_group';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_group_actions_relations (acl_group_id, acl_action_id)
            VALUES (:acl_group_id, :acl_action_id)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_group_id', $aclGroupId),
            QueryParameter::int('acl_action_id', $actionId),
        ])
    );
    $actionGroup = [
        'acl_group_id' => $aclGroupId,
        'acl_action_id' => $actionId,
    ];
    $actionGroupRelations[] = $actionGroup;

    return $actionGroup;
};

$addCommandRightIntoAction = function (string $commandType, int $accessRight, int $aclActionId) use ($pearDB, &$errorMessage): void {
    if ($accessRight === 0) {
        return;
    }
    $actionName = match($accessRight) {
        1 => "manage_{$commandType}_commands",
        2 => "see_{$commandType}_commands",
        default => null,
    };

    if ($actionName === null) {
        // Should never occur
        return;
    }

    $errorMessage = 'Unable to read into table acl_actions_rules';
    $alreadyExist = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM acl_actions_rules
            WHERE acl_action_rule_id = :acl_action_id
            AND acl_action_name = :action_name
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_action_id', $aclActionId),
            QueryParameter::string('action_name', $actionName),
        ])
    );

    if ($alreadyExist) {
        return;
    }

    $errorMessage = 'Unable to insert into table acl_actions_rules';
    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO acl_actions_rules (acl_action_rule_id, acl_action_name)
            VALUES (:acl_action_id, :action_name)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_action_id', $aclActionId),
            QueryParameter::string('action_name', $actionName),
        ])
    );
};

$moveCommandACLTopologyIntoACLActions = function () use ($pearDB, &$errorMessage, $deleteCommandsTopologyRights, $getOrCreateActionGroup, $addCommandRightIntoAction, $insertNewCommandsTopologyRights, $version): void {
    $errorMessage = 'Unable to read acl topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Moving command ACL topology into ACL actions",
    );
    $topologyGroupRelations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT acl_topology.acl_topo_id, group_topo_rel.acl_group_id FROM acl_topology
            LEFT JOIN acl_group_topology_relations as group_topo_rel
                ON acl_topology.acl_topo_id = group_topo_rel.acl_topology_id
            SQL
    );

    $errorMessage = 'Unable to read acl actions';
    $actionGroupRelations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT action.acl_action_id, group_action_rel.acl_group_id FROM acl_actions as action
            LEFT JOIN acl_group_actions_relations as group_action_rel
                ON action.acl_action_id = group_action_rel.acl_action_id
            SQL
    );

    foreach ($topologyGroupRelations as $topoGroup) {
        $aclGroupId = $topoGroup['acl_group_id'];
        $aclTopologyId = $topoGroup['acl_topo_id'];
        $commandAccessRights = $pearDB->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    topology.topology_name,
                    acl_topo_rel.topology_topology_id as topology_id,
                    acl_topo_rel.access_right
                FROM topology
                INNER JOIN acl_topology_relations as acl_topo_rel
                    ON topology.topology_id = acl_topo_rel.topology_topology_id
                    AND acl_topo_rel.acl_topo_id = :acl_topo_id
                WHERE topology.topology_page IN (60801, 60802, 60803, 60807)
                SQL,
            QueryParameters::create([
                QueryParameter::int('acl_topo_id', $aclTopologyId),
            ])
        );
        if ($commandAccessRights === []) {
            continue;
        }
        if ($topoGroup['acl_group_id'] === null) {
            $deleteCommandsTopologyRights($aclTopologyId);
            continue;
        }

        $actionGroup = $getOrCreateActionGroup($aclGroupId, $actionGroupRelations);

        foreach ($commandAccessRights as $commandRights) {
            $commandType = match($commandRights['topology_name']) {
                'Checks' => 'check',
                'Notifications' => 'notification',
                'Discovery' => 'discovery',
                'Miscellaneous' => 'miscellaneous',
            };

            $addCommandRightIntoAction($commandType, $commandRights['access_right'], $actionGroup['acl_action_id']);
        }
        $topologyToClean[] = $aclTopologyId;
    }

    $topologyToClean = array_unique($topologyToClean ?? []);
    foreach ($topologyToClean ?? [] as $aclTopologyId) {
        $insertNewCommandsTopologyRights($aclTopologyId);
        $deleteCommandsTopologyRights($aclTopologyId);
    }
};

$deleteOldCommandsTopologies = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to remove old command pages from topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Removing old command pages from topology",
    );
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` IN (60801, 60802, 60803, 60807)
            SQL
    );
};

=======
>>>>>>> e2cabb3daecd53c90bfa7935d962b76b74232bb8
try {
    // DDL statements for real time database
    $updateInstancesTable();

    // DDL statements for configuration database
    $createAccTables();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $rewordingResourceToGlobalMacro();
    $fixDuplicateHostGroupTopology();
    $migrateAccJsonToTables();

<<<<<<< HEAD
    // Command redesign updates
    $addNewCommandPage();
    $updateCommandsParentTopology();
    $moveCommandACLTopologyIntoACLActions();
    $deleteOldCommandsTopologies();

=======
>>>>>>> e2cabb3daecd53c90bfa7935d962b76b74232bb8
    if ($pearDB->isTransactionActive()) {
        $pearDB->commitTransaction();
    }

    $dropParametersColumn();

} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
