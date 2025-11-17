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