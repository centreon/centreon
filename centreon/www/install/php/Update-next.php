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
/** ------------------------------------- Additional configuration ------------------------------------- */
$addVmwareUpdatedField = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add vmware_updated field into nagios_server table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding vmware_updated field into nagios_server table",
    );
    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'nagios_server',
        'vmware_updated'
    )) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Field vmware_updated already exists in nagios_server table, skipping modification",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `nagios_server`
            ADD COLUMN `vmware_updated` BOOLEAN NOT NULL DEFAULT 0 AFTER `updated`
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added vmware_updated field into nagios_server table",
    );
};

/** -------------------------------------- Broker event_script logger -------------------------------------- */
$addEventScriptLogger = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add event_script logger to broker configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Adding 'event_script' logger to broker configuration",
    );

    if (! $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `cb_log` WHERE `name` = 'event_script'
            SQL
    )) {
        $pearDB->executeStatement(
            <<<'SQL'
                INSERT INTO `cb_log` (`name`) VALUES ('event_script')
                SQL
        );
    }

    $logId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `id` FROM `cb_log` WHERE `name` = 'event_script'
            SQL
    );
    if ($logId === false) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Failed to retrieve 'event_script' log id from cb_log, skipping cfg_centreonbroker_log population",
        );

        return;
    }

    $errorLevelId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT `id` FROM `cb_log_level` WHERE `name` = 'error'
            SQL
    );
    if ($errorLevelId === false) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Failed to retrieve 'error' level id from cb_log_level, skipping cfg_centreonbroker_log population",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `cfg_centreonbroker_log` (`id_centreonbroker`, `id_log`, `id_level`)
            SELECT `config_id`, :id_log, :id_level
            FROM `cfg_centreonbroker` cb
            WHERE NOT EXISTS (
                SELECT 1 FROM `cfg_centreonbroker_log` cbl
                WHERE cbl.`id_centreonbroker` = cb.`config_id`
                  AND cbl.`id_log` = :id_log
            )
            SQL,
        QueryParameters::create([
            QueryParameter::int('id_log', $logId),
            QueryParameter::int('id_level', $errorLevelId),
        ])
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully added 'event_script' logger to broker configuration",
    );
};

try {
    // DDL statements for real time database

    // DDL statements for configuration database
    $addVmwareUpdatedField();
    $addEventScriptLogger();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

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
