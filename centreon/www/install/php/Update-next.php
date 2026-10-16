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
use Adaptation\Log\LoggerUpgrade;
use Core\Broker\Infrastructure\Repository\BrokerInfoRowsConverter;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

/** ------------------------------------- Broker inputs/outputs ------------------------------------- */
$createBrokerInputOutputTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create the cfg_centreonbroker_input_output table';
    LoggerUpgrade::create()->info($version, 'Creating the cfg_centreonbroker_input_output table');

    $pearDB->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `cfg_centreonbroker_input_output` (
              `config_id` int(11) NOT NULL,
              `tag` enum('input','output') NOT NULL,
              `input_output_id` int(11) NOT NULL,
              `name` varchar(255) NOT NULL,
              `type_id` int(11) DEFAULT NULL,
              `type` varchar(255) DEFAULT NULL,
              `parameters` JSON NOT NULL,
              PRIMARY KEY (`config_id`,`tag`,`input_output_id`),
              CONSTRAINT `cfg_centreonbroker_input_output_ibfk_01` FOREIGN KEY (`config_id`)
                REFERENCES `cfg_centreonbroker` (`config_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            SQL
    );
};

/**
 * Converts the rows of cfg_centreonbroker_info into one row per input/output.
 * The legacy table has no primary key: reading it without ORDER BY returns the rows in insertion order,
 * which decides the order of list values and which value of a repeated key is kept.
 * The new table is emptied first so that the conversion can be replayed.
 */
$migrateBrokerInputsOutputs = function () use ($pearDB, &$errorMessage, $version): void {
    $legacyTableExists = (bool) $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = 'cfg_centreonbroker_info'
            LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string('db_name', $pearDB->getConnectionConfig()->getDatabaseNameConfiguration()),
        ])
    );
    if (! $legacyTableExists) {
        LoggerUpgrade::create()->info($version, 'cfg_centreonbroker_info does not exist, skipping the conversion');

        return;
    }

    $errorMessage = 'Unable to convert the broker inputs/outputs into cfg_centreonbroker_input_output';
    LoggerUpgrade::create()->info($version, 'Converting the broker inputs/outputs into cfg_centreonbroker_input_output');

    $rowsByConfiguration = [];
    foreach ($pearDB->iterateAssociative(
        <<<'SQL'
            SELECT config_id, config_key, config_value, config_group, config_group_id, subgrp_id, fieldIndex
            FROM `cfg_centreonbroker_info`
            SQL
    ) as $row) {
        $rowsByConfiguration[(int) $row['config_id']][] = $row;
    }

    $pearDB->delete('DELETE FROM `cfg_centreonbroker_input_output`');

    $count = 0;
    foreach ($rowsByConfiguration as $configId => $rows) {
        foreach (BrokerInfoRowsConverter::convert($rows) as $inputOutput) {
            $pearDB->insert(
                <<<'SQL'
                    INSERT INTO `cfg_centreonbroker_input_output`
                        (config_id, tag, input_output_id, name, type_id, type, parameters)
                    VALUES (:config_id, :tag, :input_output_id, :name, :type_id, :type, :parameters)
                    SQL,
                QueryParameters::create([
                    QueryParameter::int('config_id', $configId),
                    QueryParameter::string('tag', $inputOutput->tag),
                    QueryParameter::int('input_output_id', $inputOutput->id),
                    QueryParameter::string('name', $inputOutput->name),
                    QueryParameter::int('type_id', $inputOutput->typeId),
                    QueryParameter::string('type', $inputOutput->type),
                    QueryParameter::string('parameters', $inputOutput->encodeParameters()),
                ])
            );
            ++$count;
        }
    }

    LoggerUpgrade::create()->info($version, "Converted {$count} broker inputs/outputs");
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    $createBrokerInputOutputTable();

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $migrateBrokerInputsOutputs();

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
