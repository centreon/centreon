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

require_once __DIR__ . '/../../../providers/register.php';

$version = '24.10.7';

$errorMessage = '';

/**
 * @var ConnectionInterface|CentreonDB $pearDB
 * @var ConnectionInterface $pearDBO
 */
$addProviderNameColumn = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Failed to add `provider_name` column to mod_open_tickets_rule table';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: 'UPGRADE Open Tickets - adding `provider_name` column to configuration table mod_open_tickets_rule'
    );

    if ($pearDB->columnExists($pearDB->getConnectionConfig()->getDatabaseNameConfiguration(), 'mod_open_tickets_rule', 'provider_name')) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: 'UPGRADE Open Tickets - nothing to do, column provider_name already defined in mod_open_tickets_rule table'
        );

        return;
    }

    $pearDB->executeStatement(
        query: <<<'SQL'
                ALTER TABLE `mod_open_tickets_rule` ADD COLUMN `provider_name` VARCHAR(255) NOT NULL AFTER `provider_id`
            SQL
    );
};

$migrateExistingRules = function () use ($pearDB, &$errorMessage, $register_providers): void {
    $errorMessage = 'Failed to update provider names for existing rules';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: 'UPGRADE Open Tickets - finding rules to migrate (adding provider name regarding provider id configured)'
    );

    $rules = $pearDB->fetchAllAssociativeIndexed(
        query: <<<'SQL'
                SELECT rule_id, provider_id FROM mod_open_tickets_rule
            SQL
    );

    if ($rules === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: 'UPGRADE Open Tickets - nothing to do as no rules were configured'
        );
    }

    foreach ($rules as $ruleId => $provider) {
        $providerId = $provider['provider_id'];
        if (! in_array($providerId, $register_providers)) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: 'UPGRADE Open Tickets - provider not found in registry, skipping rule',
                customContext: [
                    'rule_id' => $ruleId,
                    'provider_id' => $providerId,
                ]
            );
            continue;
        }

        $providerName = array_search($providerId, $register_providers);

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: 'UPGRADE Open Tickets - updating provider name for rule',
            customContext: [
                'rule_id' => $ruleId,
                'provider_id' => $providerId,
                'provider_name' => $providerName,
            ]
        );
        $pearDB->update(
            query: <<<'SQL'
                    UPDATE mod_open_tickets_rule SET provider_name = :providerName WHERE rule_id = :ruleId
                SQL,
            queryParameters: QueryParameters::create([QueryParameter::int('ruleId', $ruleId), QueryParameter::string('providerName', $providerName)])
        );
    }
};

try {
    $addProviderNameColumn();
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $migrateExistingRules();

    $pearDB->commitTransaction();
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
