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
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

$removeDuplicateHostGroupRelations = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to remove the duplicate host group relations';
    LoggerUpgrade::create()->info($version, 'Removing the duplicate host group relations');

    // Saving a host group as a non-admin user used to insert again every kept host,
    // so the same host/host group pair could be stored several times. Keep the oldest row.
    $deletedRows = $pearDB->delete(
        <<<'SQL'
            DELETE duplicate
            FROM `hostgroup_relation` duplicate
            INNER JOIN `hostgroup_relation` kept
                ON kept.`hostgroup_hg_id` = duplicate.`hostgroup_hg_id`
                AND kept.`host_host_id` = duplicate.`host_host_id`
                AND kept.`hgr_id` < duplicate.`hgr_id`
            SQL
    );

    LoggerUpgrade::create()->info($version, "Successfully removed {$deletedRows} duplicate host group relations");
};

$addDowntimeTimezoneColumn = function () use ($pearDB, &$errorMessage, $version): void {
    if ($pearDB->columnExists(
        $pearDB->getConnectionConfig()->getDatabaseNameConfiguration(),
        'downtime',
        'dt_timezone_id'
    )) {
        LoggerUpgrade::create()->info($version, 'dt_timezone_id column already exists, skipping');

        return;
    }

    $errorMessage = 'Unable to add dt_timezone_id column to downtime';
    LoggerUpgrade::create()->info($version, 'Adding dt_timezone_id column to downtime');

    // Recurrent downtime hours are wall-clock times: they are stored as entered and only
    // the timezone they are expressed in is added, so that DST is applied at runtime.
    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `downtime`
            ADD COLUMN `dt_timezone_id` int(11) unsigned DEFAULT NULL
                COMMENT 'Timezone of the period hours (NULL = host timezone)' AFTER `dt_activate`,
            ADD KEY `downtime_timezone_id` (`dt_timezone_id`),
            ADD CONSTRAINT `downtime_ibfk_1` FOREIGN KEY (`dt_timezone_id`)
                REFERENCES `timezone` (`timezone_id`) ON DELETE SET NULL
            SQL
    );

    LoggerUpgrade::create()->info($version, 'Successfully added dt_timezone_id column');
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here
    $addDowntimeTimezoneColumn();

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here
    $removeDuplicateHostGroupRelations();

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
