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
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

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
        $configuration = json_decode($configurationJson['configuration'], true);
        if (! isset($configuration['port'])) {
            $configuration['port'] = $configuration['agent_initiated'] === true
                ? AgentConfiguration::DEFAULT_PORT
                : null ;
        }
        $updatedConfigurationJson = json_encode($configuration);
        $pearDB->update(
            <<<'SQL'
                UPDATE agent_configuration
                SET configuration = :configuration
                WHERE id = :id
                SQL,
                QueryParameters::create([
                    QueryParameter::string('configuration', $updatedConfigurationJson),
                    QueryParameter::int('id', (int) $configurationJson['id'])
            ])
        );
    }
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here
    $setBackupMysqlConfDefaultAsEmpty();
    $addDefaultPortToAgentInitiatedAgentConfiguration();

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
