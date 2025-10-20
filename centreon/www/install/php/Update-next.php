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
$alignCMAAgentConfigurationWithNewSchema = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to align agent configuration with new schema';
    $agentConfigurations = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT * FROM `agent_configuration`
            WHERE `type` = 'centreon-agent'
            SQL
    );
    if ($agentConfigurations === []) {
        return;
    }
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
};

$cleanGlobalMacrosName = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to update cfg_resource table';
    $invalidMacros = $pearDB->fetchAllAssociative(
        <<<'SQL'
            SELECT resource_id, resource_name FROM cfg_resource
            WHERE resource_name NOT LIKE '\$%' OR resource_name NOT LIKE '%\$'
            SQL
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
};

$fixTypoInStandardMacroName = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to fix typo in standard macro name';
    $pearDB->update(
        <<<'SQL'
                UPDATE nagios_macro SET macro_name = '$TOTALHOSTSUNREACHABLEUNHANDLED$' WHERE macro_id = 65
            SQL
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

/** -------------------------------------- Engine Configuration updates -------------------------------------- */
$addOpentelemetryLogLevelColumn = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to add log_level_otl column to cfg_nagios_logger table';
    if (! $pearDB->isColumnExist('cfg_nagios_logger', 'log_level_otl')) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `cfg_nagios_logger`
                ADD COLUMN `log_level_otl` enum('trace', 'debug', 'info', 'warning', 'err', 'critical', 'off') DEFAULT 'err'
                SQL
        );
    }
};

/** -------------------------------------------- BBDO cfg update -------------------------------------------- */
$bbdoDefaultUpdate = function () use ($pearDB, &$errorMessage): void {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'bbdo_version')) {
        $errorMessage = "Unable to update 'bbdo_version' column to 'cfg_centreonbroker' table";
        $pearDB->executeStatement('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.0.1"');
    }
};

$bbdoCfgUpdate = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = "Unable to update 'bbdo_version' version in 'cfg_centreonbroker' table";
    $pearDB->executeStatement('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.0.1"');
};

/** -------------------------------------------- Password encryption -------------------------------------------- */
$addIsEncryptionReadyAsBooleanColumn = function () use ($pearDB, $pearDBO, &$errorMessage, $version): void {
    $errorMessage = "Unable to update 'is_encryption_ready' column to boolean type";
    if (
        $pearDB->isColumnExist('nagios_server', 'is_encryption_ready')
        && $pearDB->isColumnExist('nagios_server', 'is_encryption_ready_old') !== 1
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Renaming column 'is_encryption_ready' on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');
    }
    if ($pearDB->isColumnExist('nagios_server', 'is_encryption_ready') !== 1) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Adding column 'is_encryption_ready' of type boolean on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 1');
    }
    if ($pearDB->isColumnExist('nagios_server', 'is_encryption_ready_old')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'nagios_server' table",
        );

        $pearDB->executeStatement(
            <<<'SQL'
                UPDATE nagios_server ns
                SET ns.is_encryption_ready = 0
                WHERE ns.is_encryption_ready_old = '0'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Dropping column 'is_encryption_ready_old' on 'nagios_server' table",
        );
        $pearDB->executeStatement('ALTER TABLE `nagios_server` DROP COLUMN `is_encryption_ready_old`');
    }

    if (
        $pearDBO->isColumnExist('instances', 'is_encryption_ready')
        && $pearDBO->isColumnExist('instances', 'is_encryption_ready_old') !== 1
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` RENAME COLUMN `is_encryption_ready` TO `is_encryption_ready_old`');
    }
    if ($pearDBO->isColumnExist('instances', 'is_encryption_ready') !== 1) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Adding column 'is_encryption_ready' of type boolean on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` ADD COLUMN `is_encryption_ready` BOOLEAN NOT NULL DEFAULT 0');
    }
    if ($pearDBO->isColumnExist('instances', 'is_encryption_ready_old')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Moving 'is_encryption_ready' value of existing pollers on 'instances' table",
        );
        $pearDBO->executeStatement(
            <<<'SQL'
                UPDATE instances ins
                SET ins.is_encryption_ready = 1
                WHERE ins.is_encryption_ready_old = '1'
                SQL
        );

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Dropping column 'is_encryption_ready_old' on 'instances' table",
        );
        $pearDBO->executeStatement('ALTER TABLE `instances` DROP COLUMN `is_encryption_ready_old`');
    }
};

/** -------------------------------------- Backup updates -------------------------------------- */
$setBackupMysqlConfDefaultAsEmpty = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to reset default of database configuration path in backup configuration';
    $pearDB->update(
        <<<'SQL'
            UPDATE options SET value = ''
            WHERE options.key = 'backup_mysql_conf' AND options.value = '/etc/my.cnf.d/centreon.cnf'
            SQL
    );
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    $bbdoDefaultUpdate();
    $addOpentelemetryLogLevelColumn();
    $addIsEncryptionReadyAsBooleanColumn();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $alignCMAAgentConfigurationWithNewSchema();
    $cleanGlobalMacrosName();
    $fixTypoInStandardMacroName();
    $fixBrokerConfigTypo();
    $bbdoCfgUpdate();
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
