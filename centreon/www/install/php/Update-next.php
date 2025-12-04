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

    $setBackupMysqlConfDefaultAsEmpty();
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
