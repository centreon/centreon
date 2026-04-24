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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

/** ------------------------------------- SAML ------------------------------------- */
/**
 * Recover SAML provider configurations whose requested_authn_context_comparison field was left in an
 * invalid state by the non-idempotent 25.11.0 migration (MON-198174): a boolean, a missing value, or
 * a string outside RequestedAuthnContextComparisonEnum breaks CustomConfiguration::createFromValues()
 * and the login page. When detected, reset the value to 'exact'.
 */
$fixSamlRequestedAuthnContextComparison = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to recover SAML provider configuration';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: checking SAML provider configuration for invalid requested_authn_context_comparison"
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

    $customConfiguration = json_decode(
        json: $samlConfiguration['custom_configuration'],
        associative: true,
        flags: JSON_THROW_ON_ERROR
    );

    $validComparisonValues = ['minimum', 'exact', 'better', 'maximum'];
    $currentComparison = $customConfiguration['requested_authn_context_comparison'] ?? null;

    if (is_string($currentComparison) && in_array($currentComparison, $validComparisonValues, true)) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context_comparison already valid, no recovery needed"
        );

        return;
    }

    $customConfiguration['requested_authn_context_comparison'] = 'exact';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: requested_authn_context_comparison was not a valid string, reset to 'exact'"
    );

    $query = <<<'SQL'
            UPDATE `provider_configuration`
            SET `custom_configuration` = :custom_configuration
            WHERE `id` = :id
        SQL;
    $queryParameters = QueryParameters::create(
        [
            QueryParameter::string('custom_configuration', json_encode($customConfiguration, JSON_THROW_ON_ERROR)),
            QueryParameter::int('id', (int) $samlConfiguration['id']),
        ]
    );
    $pearDB->update($query, $queryParameters);

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration recovered successfully"
    );
};

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // SAML recovery for platforms affected by MON-198174
    $fixSamlRequestedAuthnContextComparison();

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here

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
