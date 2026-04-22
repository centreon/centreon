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

$version = '25.10.6';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/**
 * Update SAML provider configuration:
 *      - If requested_authn_context exists, set requested_authn_context_comparison to its value and requested_authn_context to true
 *      - If requested_authn_context does not exist, set requested_authn_context_comparison to 'exact' and requested_authn_context to false
 */
$updateSamlProviderConfiguration = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to retrieve SAML provider configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating SAML provider configuration"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving SAML provider configuration from database..."
    );

    $samlConfiguration = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM `provider_configuration`
            WHERE `type` = 'saml'
            SQL
    );

    if (! $samlConfiguration || ! isset($samlConfiguration['custom_configuration'])) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no SAML provider configuration found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration found, checking for requested_authn_context"
    );

    $customConfiguration = json_decode($samlConfiguration['custom_configuration'], true, JSON_THROW_ON_ERROR);

    if (isset($customConfiguration['requested_authn_context'])) {
        $customConfiguration['requested_authn_context_comparison'] = $customConfiguration['requested_authn_context'];
        $customConfiguration['requested_authn_context'] = false;

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context found, requested_authn_context_comparison takes the value of requested_authn_context, and requested_authn_context is set to true"
        );
    } else {
        $customConfiguration['requested_authn_context_comparison'] = 'exact';
        $customConfiguration['requested_authn_context'] = false;

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context not found, setting requested_authn_context to false and requested_authn_context_comparison to 'exact'"
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating SAML provider configuration in database..."
    );

    $query = <<<'SQL'
            UPDATE `provider_configuration`
            SET `custom_configuration` = :custom_configuration
            WHERE `type` = 'saml'
        SQL;
    $queryParameters = QueryParameters::create(
        [QueryParameter::string('custom_configuration', json_encode($customConfiguration, JSON_THROW_ON_ERROR))]
    );
    $pearDB->update($query, $queryParameters);

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration updated successfully"
    );
};

/** -------------------------------------- Broker configuration -------------------------------------- */
$fixBrokerConfigTypo = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to fix typo in broker configuration';
    $pearDB->executeStatement(
        <<<'SQL'
            UPDATE cfg_centreonbroker_info SET config_key = 'negotiation' WHERE config_key = 'negociation'
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

/**
 * Resolve a single ACC username: de-vault it or decrypt it.
 *
 * @return string|null The resolved clear-text username, or null if unchanged
 */
$resolveAccUsername = function (
    string $currentUsername,
    string $vcenterName,
    string $itemLabel,
    EncryptionInterface $encryption,
    ?ReadVaultRepositoryInterface $readVaultRepository,
    ?WriteVaultRepositoryInterface $writeVaultRepository,
): ?string {
    $getUuidFromPath = function (string $vaultPath): ?string {
        $parts = explode('::', $vaultPath);

        $pathWithUuid = $parts[count($parts) - 2] ?? null;
        if ($pathWithUuid === null) {
            return null;
        }

        $pathSegments = explode('/', $pathWithUuid);

        return end($pathSegments);
    };

    if (str_starts_with($currentUsername, VaultConfiguration::VAULT_PATH_PATTERN)) {
        if ($readVaultRepository === null || $writeVaultRepository === null) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE: Vault not configured, cannot de-vault username for {$itemLabel}"
            );

            return null;
        }

        try {
            $vaultDatas = $readVaultRepository->findFromPath($currentUsername);
            $secretValue = null;

            if (is_array($vaultDatas) && $vaultDatas !== []) {
                $usernameKey = $vcenterName . '_username';
                if (array_key_exists($usernameKey, $vaultDatas)) {
                    $secretValue = $vaultDatas[$usernameKey];
                }
            }

            if ($secretValue !== null) {
                $uuid = $getUuidFromPath($currentUsername);

                if ($uuid !== null) {
                    $writeVaultRepository->upsert($uuid, [], [$usernameKey => true]);

                    CentreonLog::create()->info(
                        logTypeId: CentreonLog::TYPE_UPGRADE,
                        message: "UPGRADE: Successfully de-vaulted and deleted username secret for {$itemLabel}"
                    );
                } else {
                    CentreonLog::create()->warning(
                        logTypeId: CentreonLog::TYPE_UPGRADE,
                        message: "UPGRADE: Could not extract UUID from path {$currentUsername} for {$itemLabel}."
                    );
                }

                return $secretValue;
            }

            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE: Vault secret found but username key not recognized for {$itemLabel}."
            );
        } catch (Throwable $e) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE: Failed to retrieve or delete secret from Vault for {$itemLabel}: " . $e->getMessage()
            );
        }

        return null;
    }

    $decrypted = $encryption->decrypt($currentUsername);
    if ($decrypted === null || $decrypted === '') {
        CentreonLog::create()->warning(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE: Failed to decrypt username for {$itemLabel}."
        );

        return null;
    }

    return $decrypted;
};

$migrateAccUsernamesFromVault = function () use ($pearDB, &$errorMessage, $version, $resolveAccUsername): void {
    $errorMessage = 'Failed to migrate Additional Configuration usernames from vault';
    $secondKey = 'additional_connector_configuration_vmware_v6';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE {$version}: Starting migration of VMWare usernames to clear text and removing them from Vault"
    );

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

    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'additional_connector_configuration',
        'parameters')
    ) {
        // if `parameters` column exists
        $accs = $pearDB->fetchAllAssociative(
            "SELECT id, name, type, parameters FROM additional_connector_configuration WHERE type = 'vmware_v6'"
        );

        foreach ($accs as $acc) {
            try {
                $parameters = json_decode($acc['parameters'], true, 512, JSON_THROW_ON_ERROR);
                $updated = false;

                foreach ($parameters['vcenters'] as $index => $vcenter) {
                    $newUsername = $resolveAccUsername(
                        $vcenter['username'],
                        $vcenter['name'],
                        "ACC {$acc['id']}",
                        $encryption,
                        $readVaultRepository,
                        $writeVaultRepository,
                    );

                    if ($newUsername !== null) {
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
    } else {
        // vcenters have been migrated to the `acc_item` table
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE {$version}: parameters column already migrated to acc_item table, reading vcenters from acc_item"
        );

        $accItems = $pearDB->fetchAllAssociative(
            'SELECT ai.id, ai.acc_id, ai.name, ai.username FROM acc_item ai'
            . ' INNER JOIN additional_connector_configuration acc ON acc.id = ai.acc_id'
            . " WHERE acc.type = 'vmware_v6'"
        );

        foreach ($accItems as $item) {
            try {
                $newUsername = $resolveAccUsername(
                    $item['username'],
                    $item['name'],
                    "acc_item {$item['id']}",
                    $encryption,
                    $readVaultRepository,
                    $writeVaultRepository,
                );

                if ($newUsername !== null) {
                    $pearDB->update(
                        'UPDATE acc_item SET username = :username, updated_at = :updatedAt WHERE id = :id',
                        QueryParameters::create([
                            QueryParameter::string(':username', $newUsername),
                            QueryParameter::int(':updatedAt', time()),
                            QueryParameter::int(':id', (int) $item['id']),
                        ])
                    );
                }
            } catch (Throwable $e) {
                CentreonLog::create()->error(
                    logTypeId: CentreonLog::TYPE_UPGRADE,
                    message: "UPGRADE: Error processing acc_item {$item['id']}: " . $e->getMessage()
                );
            }
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

    $fixBrokerConfigTypo();
    $updateSamlProviderConfiguration();
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
