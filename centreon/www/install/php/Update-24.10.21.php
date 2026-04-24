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

$version = '24.10.21';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/**
 * Update SAML provider configuration:
 *      - If requested_authn_context_comparison is already a valid comparison value, keep it (idempotent rerun).
 *      - Else if requested_authn_context is a valid legacy comparison value, move it to requested_authn_context_comparison.
 *      - Else default requested_authn_context_comparison to 'exact' (recovers from a prior buggy run that stored a boolean or an invalid string).
 *      - In all cases, force requested_authn_context to false.
 *
 * Valid comparison values mirror RequestedAuthnContextComparisonEnum and are whitelisted locally so the
 * migration never writes a value that CustomConfiguration::createFromValues() would reject.
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

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: SAML provider configuration found, checking for requested_authn_context"
    );

    $customConfiguration = json_decode(
        json: $samlConfiguration['custom_configuration'],
        associative: true,
        flags: JSON_THROW_ON_ERROR
    );

    $validComparisonValues = ['minimum', 'exact', 'better', 'maximum'];
    $existingComparison = $customConfiguration['requested_authn_context_comparison'] ?? null;
    $legacyValue = $customConfiguration['requested_authn_context'] ?? null;

    if (is_string($existingComparison) && in_array($existingComparison, $validComparisonValues, true)) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context_comparison already set to a valid value, keeping existing value"
        );
    } elseif (is_string($legacyValue) && in_array($legacyValue, $validComparisonValues, true)) {
        $customConfiguration['requested_authn_context_comparison'] = $legacyValue;

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context holds a valid legacy value, moved it to requested_authn_context_comparison"
        );
    } else {
        $customConfiguration['requested_authn_context_comparison'] = 'exact';

        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: requested_authn_context_comparison missing or invalid, defaulting to 'exact'"
        );
    }

    $customConfiguration['requested_authn_context'] = false;

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: updating SAML provider configuration in database..."
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
        message: "UPGRADE - {$version}: SAML provider configuration updated successfully"
    );
};

$linkCMAConnectorToExistingRelatedCMACommands = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to select CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] select existing Centreon Monitoring Agent Connector ID",
    );
    $cmaConnectorId = $pearDB->fetchOne(
        <<<'SQL'
            SELECT id FROM connector WHERE name = 'Centreon Monitoring Agent' LIMIT 1
            SQL
    );
    if ($cmaConnectorId === false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [CMA] No CMA connector found, skipping linking CMA commands",
        );

        return;
    }

    $errorMessage = 'Unable to update commands to link them to CMA connector';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [CMA] Updating commands to link them to CMA connector",
    );
    $pearDB->update(
        <<<'SQL'
            UPDATE command
            SET connector_id = :cmaConnectorId
            WHERE command_name LIKE "%Centreon-Monitoring-Agent%" OR command_name LIKE "%-CMA-%"
            SQL,
        QueryParameters::create([
            QueryParameter::int('cmaConnectorId', $cmaConnectorId),
        ])
    );
};

try {
    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $linkCMAConnectorToExistingRelatedCMACommands();
    $updateSamlProviderConfiguration();

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
