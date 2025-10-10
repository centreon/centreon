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

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface|CentreonDB $pearDB
 * @var ConnectionInterface $pearDBO
 */

$addProviderNameColumn = function () use ($pearDB, $errorMessage): void {
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: 'Adding `provider_name` column to configuration table mod_open_tickets_rule'
    );

    $errorMessage = 'Failed to add `provider_name` column to mod_open_tickets_rule table';

    if ($pearDB->isColumnExist('mod_open_tickets_rule', 'provider_name')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: 'Nothing to do, column provider_name already defined in mod_open_tickets_rule table'
        );

        return;
    }

    $pearDB->executeStatement(
        query: <<<SQL
            ALTER TABLE `mod_open_tickets_rule` ADD COLUMN `provider_name` VARCHAR(255) NOT NULL AFTER `provider_id`
        SQL
    );
};

try {
    $addProviderNameColumn();
} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE Open Tickets - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE Open Tickets - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE Open Tickets - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE Open Tickets -  {$version}: " . $errorMessage,
        previous: $throwable
    );
}
