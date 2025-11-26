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

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\Kernel;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */
// -------------------------------------- AgentConfiguration updates --------------------------------------

/**
 * Align preexisting Agent Configuration with the new schema:
 *      - Add is_poller_initiated bool
 *      - Add is_agent_initiated bool
 *      - Remove is_reverse bool
 */
$alignCMAAgentConfigurationWithNewSchema = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to align agent configuration with new schema';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: aligning agent configuration with new schema"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving agent configurations from database..."
    );

    $agentConfigurations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT * FROM `agent_configuration`
            WHERE `type` = 'centreon-agent'
            SQL
    );
    if ($agentConfigurations === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no agent configurations found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: found " . count($agentConfigurations) . ' agent configurations, updating...'
    );

    foreach ($agentConfigurations as $agentConfiguration) {
        $configuration = json_decode(
            json: $agentConfiguration['configuration'],
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
        $configuration['agent_initiated'] = false;
        $configuration['poller_initiated'] = false;

        if ($configuration['is_reverse']) {
            $configuration['poller_initiated'] = true;
            unset($configuration['is_reverse']);
        } else {
            $configuration['agent_initiated'] = true;
            unset($configuration['is_reverse']);
        }

        $pearDB->update(
            <<<'SQL'
                    UPDATE agent_configuration
                    SET configuration = :configuration
                    WHERE id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string(':configuration', json_encode($configuration, JSON_THROW_ON_ERROR)),
                QueryParameter::int(':id', $agentConfiguration['id']),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: agent configurations aligned successfully"
    );
};

$cleanGlobalMacrosName = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to clean global macros name';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: cleaning global macros name"
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: retrieving invalid macros from database..."
    );

    $invalidMacros = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT resource_id, resource_name FROM cfg_resource
            WHERE resource_name NOT LIKE '\$%' OR resource_name NOT LIKE '%\$'
            SQL
    );

    if ($invalidMacros === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no invalid macros found, skipping"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: found " . count($invalidMacros) . ' invalid macros, updating...'
    );

    foreach ($invalidMacros as $macro) {
        $newName = $macro['resource_name'];
        if (str_starts_with($newName, '$') === false) {
            $newName = '$' . $newName;
        }
        if (str_ends_with($newName, '$') === false) {
            $newName .= '$';
        }
        $pearDB->update(
            <<<'SQL'
                UPDATE cfg_resource
                SET resource_name = :resource_name
                WHERE resource_id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string(':resource_name', $newName),
                QueryParameter::int(':id', (int) $macro['resource_id']),
            ])
        );
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: global macros name cleaned successfully"
    );
};

$fixTypoInStandardMacroName = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to fix typo in standard macro name';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: fixing typo in standard macro name..."
    );

    $nbUpdate = $pearDB->update(
        <<<'SQL'
                UPDATE nagios_macro SET macro_name = '$TOTALHOSTSUNREACHABLEUNHANDLED$' WHERE macro_id = 65
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: {$nbUpdate} typo in standard macro name fixed successfully"
    );
};

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

$migrateAccUsernamesFromVault = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Failed to migrate Additional Configuration usernames from vault';
    $secondKey = 'additional_connector_configuration_vmware_v6';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: migrating Additional Configuration usernames from vault"
    );

    $kernel = Kernel::createForWeb();
    $container = $kernel->getContainer();
    $encryption = $container->get(EncryptionInterface::class);
    $encryption->setSecondKey($secondKey);

    // Retrieve all VMWARE_V6 Additional Configurations
    $accs = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT id, name, type, parameters
            FROM additional_connector_configuration
            WHERE type = 'vmware_v6'
            SQL
    );

    // If no ACC found, skip migration
    if ($accs === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: no VMWARE_V6 Additional Configurations found, skipping username migration"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: found " . count($accs) . ' VMWARE_V6 Additional Configurations, checking for usernames in vault...'
    );

    foreach ($accs as $acc) {
        $parameters = json_decode($acc['parameters'], true, 512, JSON_THROW_ON_ERROR);
        $updated = false;

        // Retrieve and decrypt usernames from vault
        foreach ($parameters['vcenters'] as $index => $vcenter) {
            $parameters['vcenters'][$index]['username'] = str_starts_with(
                $vcenter['username'],
                VaultConfiguration::VAULT_PATH_PATTERN
            ) ? $vcenter['username'] : $encryption->decrypt($vcenter['username']) ?? '';

            $updated = true;
        }

        // Update ACC in DB
        if ($updated) {
            $pearDB->update(
                <<<'SQL'
                    UPDATE additional_connector_configuration
                    SET parameters = :parameters, updated_at = :updatedAt
                    WHERE id = :id
                    SQL,
                QueryParameters::create([
                    QueryParameter::string(':parameters', json_encode($parameters, JSON_THROW_ON_ERROR)),
                    QueryParameter::int(':updatedAt', time()),
                    QueryParameter::int(':id', $acc['id']),
                ])
            );
        }
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: ACC usernames migrated from vault successfully"
    );
};

try {
    // DDL statements for configuration database

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $alignCMAAgentConfigurationWithNewSchema();
    $cleanGlobalMacrosName();
    $fixTypoInStandardMacroName();
    $fixBrokerConfigTypo();
    $bbdoCfgUpdate();
    $updateSamlProviderConfiguration();
    $migrateAccUsernamesFromVault();
    $setBackupMysqlConfDefaultAsEmpty();

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
