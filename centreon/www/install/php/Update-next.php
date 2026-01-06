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
use App\Kernel;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------- Backup updates -------------------------------------- */
$setBackupMysqlConfDefaultAsEmpty = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to reset default of database configuration path in backup configuration';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [backup] Updating default value of backup_mysql_conf in 'options' table",
    );
    $pearDB->update(
        <<<'SQL'
            UPDATE options SET value = ''
            WHERE options.key = 'backup_mysql_conf' AND options.value = '/etc/my.cnf.d/centreon.cnf'
            SQL
    );
};

$updateFreshnessforCMAServicesAndHosts = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to select CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Selecting Centreon Monitoring Agent Connector ID",
    );
    $cmaConnectorId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT id FROM connector
            WHERE name = 'Centreon Monitoring Agent'
            SQL
    );

    if ($cmaConnectorId === false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [CMA] CMA connector not found, skipping check_freshness update",
        );

        return;
    }

    $errorMessage = 'Unable to select commands for CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Selecting commands IDs for CMA connector",
    );
    $commandsIds = $pearDB->fetchFirstColumn(
        <<<'SQL'
            SELECT DISTINCT command_id
            FROM command
            WHERE connector_id = :cmaConnectorId
            SQL,
        QueryParameters::create([QueryParameter::int('cmaConnectorId', $cmaConnectorId)])
    );
    if (empty($commandsIds)) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [CMA] No commands found for CMA connector, skipping check_freshness update",
        );

        return;
    }

    $commandsIds = array_map('intval', $commandsIds);
    $commandsIdsAsString = implode(',', $commandsIds);

    $errorMessage = 'Unable to update service_check_freshness and service_freshness_threshold';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Setting service_check_freshness to true and service_freshness_threshold "
            . 'to 120 for services using CMA commands',
    );
    $pearDB->update(
        <<<SQL
            UPDATE service
            SET service_check_freshness = '1', service_freshness_threshold = 120
            WHERE command_command_id IN ({$commandsIdsAsString})
            SQL
    );

    $errorMessage = 'Unable to update host_check_freshness and host_freshness_threshold';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Setting host_check_freshness to true and host_freshness_threshold "
            . 'to 120 for hosts using CMA commands',
    );
    $pearDB->update(
        <<<SQL
            UPDATE host
            SET host_check_freshness = '1', host_freshness_threshold = 120
            WHERE command_command_id IN ({$commandsIdsAsString})
            SQL
    );
};

$addDefaultPortToAgentInitiatedAgentConfiguration = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add default port to agent initiated agent configurations';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [agent_configuration] Adding default port to agent initiated agent configurations",
    );
    $agentConfigurations = $pearDB->fetchAllAssociative(
        <<<'SQL'
                SELECT id, configuration FROM agent_configuration
            SQL
    );
    foreach ($agentConfigurations as $configurationJson) {
        $configuration = json_decode($configurationJson['configuration'], true, JSON_THROW_ON_ERROR);
        if (! isset($configuration['port'])) {
            $configuration['port'] = (bool) $configuration['agent_initiated'] === true
                ? AgentConfiguration::DEFAULT_PORT
                : null;
        }
        $updatedConfigurationJson = json_encode($configuration, JSON_THROW_ON_ERROR);
        $pearDB->update(
            <<<'SQL'
                UPDATE agent_configuration
                SET configuration = :configuration
                WHERE id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string('configuration', $updatedConfigurationJson),
                QueryParameter::int('id', (int) $configurationJson['id']),
            ])
        );
    }
};

$linkCMAConnectorToExistingRelatedCMACommands = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to select CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] select existing Centreon Monitoring Agent Connector ID",
    );
    $cmaConnectorId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT id FROM connector WHERE name = 'Centreon Monitoring Agent' LIMIT 1
            SQL
    );
    if ($cmaConnectorId === false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [CMA] No CMA connector found, skipping linking CMA commands",
        );

        return;
    }

    $errorMessage = 'Unable to update commands to link them to CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Updating commands to link them to CMA connector",
    );
    $pearDB->update(
        <<<'SQL'
            UPDATE command
            SET connector_id = :cmaConnectorId
            WHERE command_name LIKE "%Centreon-Monitoring-Agent%" OR command_name LIKE "%-CMA-%"
            SQL,
        QueryParameters::create([
            QueryParameter::int('cmaConnectorId', $cmaConnectorId),
        ])
    );
};

/** -------------------------------------- Command redesign updates-------------------------------------- */
$addNewCommandPage = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to add new command page topology';
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
            INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`)
            VALUES ( 'Commands', '/configuration/commands', '1', '1', 608, 60808, 1, 1)
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
            VALUES (:acl_topo_id, :topology_topology_id, :access_right)
            SQL,
        QueryParameters::create([
            QueryParameter::int('acl_topo_id', $aclTopologyId),
            QueryParameter::int('topology_topology_id', 60808),
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

$moveCommandACLTopologyIntoACLActions = function () use ($pearDB, &$errorMessage, $deleteCommandsTopologyRights, $getOrCreateActionGroup, $addCommandRightIntoAction, $insertNewCommandsTopologyRights): void {
    $errorMessage = 'Unable to read acl topology';
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
/** -------------------------------------- Additional configuration - de-vault username -------------------------------------- */
$migrateAccUsernamesFromVault = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to migrate Additional Configuration usernames from vault';
    $secondKey = 'additional_connector_configuration_vmware_v6';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE {$version}: Starting migration of VMWare usernames to clear text and removing them from Vault"
    );

    $getUuidFromPath = function (string $vaultPath): ?string {
        $parts = explode('::', $vaultPath);

        $pathWithUuid = $parts[count($parts) - 2] ?? null;
        if ($pathWithUuid === null) {
            return null;
        }

        $pathSegments = explode('/', $pathWithUuid);

        return end($pathSegments);
    };

    $kernel = Kernel::createForWeb();
    $container = $kernel->getContainer();

    $encryption = $container->get(EncryptionInterface::class);
    $encryption->setSecondKey($secondKey);

    $readVaultRepository = null;
    $writeVaultRepository = null;
    if ($container->has(ReadVaultRepositoryInterface::class)) {
        $readVaultRepository = $container->get(ReadVaultRepositoryInterface::class);
    }
    if ($container->has(WriteVaultRepositoryInterface::class)) {
        $writeVaultRepository = $container->get(WriteVaultRepositoryInterface::class);
    }

    if ($readVaultRepository !== null) {
        $readVaultRepository->setCustomPath(AbstractVaultRepository::ACC_VAULT_PATH);
    }
    if ($writeVaultRepository !== null) {
        $writeVaultRepository->setCustomPath(AbstractVaultRepository::ACC_VAULT_PATH);
    }

    // get all VMWare v6 ACCs
    $accs = $pearDB->fetchAllAssociative(
        "SELECT id, name, type, parameters FROM additional_connector_configuration WHERE type = 'vmware_v6'"
    );

    foreach ($accs as $acc) {
        try {
            $parameters = json_decode($acc['parameters'], true, 512, JSON_THROW_ON_ERROR);
            $updated = false;

            foreach ($parameters['vcenters'] as $index => $vcenter) {
                $currentUsername = $vcenter['username'];
                $newUsername = $currentUsername;

                // if Vaulted username
                if (str_starts_with($currentUsername, VaultConfiguration::VAULT_PATH_PATTERN)) {
                    if ($readVaultRepository === null || $writeVaultRepository === null) {
                        CentreonLog::create()->warning(
                            logTypeId: CentreonLog::TYPE_UPGRADE,
                            message: "UPGRADE: Vault not configured, cannot de-vault username for ACC {$acc['id']}"
                        );
                        continue;
                    }

                    try {
                        $vaultDatas = $readVaultRepository->findFromPath($currentUsername);
                        $secretValue = null;

                        if (is_array($vaultDatas) && $vaultDatas !== []) {
                            $usernameKey = $vcenter['name'] . '_username';
                            if (array_key_exists($usernameKey, $vaultDatas)) {
                                $secretValue = $vaultDatas[$usernameKey];
                            }
                        }

                        if ($secretValue !== null) {
                            $newUsername = $secretValue;

                            $uuid = $getUuidFromPath($currentUsername);

                            if ($uuid !== null) {
                                $writeVaultRepository->upsert($uuid, [], [$usernameKey => true]);

                                CentreonLog::create()->info(
                                    logTypeId: CentreonLog::TYPE_UPGRADE,
                                    message: "UPGRADE: Successfully de-vaulted and deleted username secret for ACC {$acc['id']}"
                                );
                            } else {
                                CentreonLog::create()->warning(
                                    logTypeId: CentreonLog::TYPE_UPGRADE,
                                    message: "UPGRADE: Could not extract UUID from path {$currentUsername} for ACC {$acc['id']}."
                                );
                            }
                        } else {
                            CentreonLog::create()->warning(
                                logTypeId: CentreonLog::TYPE_UPGRADE,
                                message: "UPGRADE: Vault secret found but username key not recognized for ACC {$acc['id']}."
                            );
                        }
                    } catch (Throwable $e) {
                        CentreonLog::create()->warning(
                            logTypeId: CentreonLog::TYPE_UPGRADE,
                            message: "UPGRADE: Failed to retrieve or delete secret from Vault for ACC {$acc['id']}: " . $e->getMessage()
                        );
                    }
                } else {
                    $decrypted = $encryption->decrypt($currentUsername);
                    if ($decrypted === null || $decrypted === '') {
                        CentreonLog::create()->warning(
                            logTypeId: CentreonLog::TYPE_UPGRADE,
                            message: "UPGRADE: Failed to decrypt username for ACC {$acc['id']}."
                        );
                    } else {
                        $newUsername = $decrypted;
                    }
                }

                if ($newUsername !== $currentUsername) {
                    $parameters['vcenters'][$index]['username'] = $newUsername;
                    $updated = true;
                }
            }

            if ($updated) {
                $pearDB->update(
                    'UPDATE additional_connector_configuration SET parameters = :parameters, updated_at = :updatedAt WHERE id = :id',
                    QueryParameters::create([
                        QueryParameter::string(':parameters', json_encode($parameters, JSON_THROW_ON_ERROR)),
                        QueryParameter::int(':updatedAt', time()),
                        QueryParameter::int(':id', (int) $acc['id']),
                    ])
                );
            }

        } catch (Throwable $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE: Error processing ACC {$acc['id']}: " . $e->getMessage()
            );
        }
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE {$version}: ACC usernames migration completed."
    );
};

try {
    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $setBackupMysqlConfDefaultAsEmpty();
    $updateFreshnessforCMAServicesAndHosts();
    $addDefaultPortToAgentInitiatedAgentConfiguration();
    $linkCMAConnectorToExistingRelatedCMACommands();
    $migrateAccUsernamesFromVault();
    // Command redesign updates
    $addNewCommandPage();
    $updateCommandsParentTopology();
    $moveCommandACLTopologyIntoACLActions();

    $pearDB->commitTransaction();

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
