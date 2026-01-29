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
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\TrimmedString;
use Core\Security\Token\Domain\Model\NewJwtToken;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = '24.10.13';
$errorMessage = '';

$alterContactPagerSize = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to alter contact_pager column size in contact table';
    if ($pearDB->isColumnExist('contact', 'contact_pager')) {
        $pearDB->executeStatement(
            <<<'SQL'
                ALTER TABLE `contact`
                    MODIFY COLUMN `contact_pager` VARCHAR(300)
                SQL
        );
    }
};

/**
 * Generate a token based on the first found admin contact to update old agent_configurations
 *
 * @return array{token_name: string, creator_id: int}
 */
$generateToken = function () use ($pearDB): array {
    $admin = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT contact_id, contact_name
            FROM contact
            WHERE contact_admin = '1'
            LIMIT 1
            SQL
    );

    // Reuse an existing cma-default token if available for this creator
    $existing = $pearDB->fetchAssociative(
        <<<'SQL'
                SELECT token_name, creator_id
                FROM jwt_tokens
                WHERE token_name = :token_name AND creator_id = :creator_id
                LIMIT 1
            SQL,
        QueryParameters::create([
            QueryParameter::string(':token_name', 'cma-default'),
            QueryParameter::int(':creator_id', (int) $admin['contact_id']),
        ])
    );
    if (! empty($existing)) {
        return ['name' => 'cma-default', 'creator_id' => (int) $admin['contact_id']];
    }

    $token = new NewJwtToken(
        name: new TrimmedString('cma-default'),
        creatorId: (int) $admin['contact_id'],
        creatorName: new TrimmedString((string) $admin['contact_name']),
        expirationDate: null
    );

    $pearDB->executeStatement(
        <<<'SQL'
                INSERT INTO `jwt_tokens` (token_string,token_name,creator_id,creator_name,encoding_key,is_revoked,creation_date,expiration_date)
                VALUES (:token_string,:token_name,:creator_id,:creator_name,:encoding_key,:is_revoked,:creation_date,:expiration_date)
            SQL,
        QueryParameters::create([
            QueryParameter::string(':token_string', (string) $token->getToken()),
            QueryParameter::string(':token_name', (string) $token->getName()),
            QueryParameter::int(':creator_id', (int) $token->getCreatorId()),
            QueryParameter::string(':creator_name', (string) $token->getCreatorName()),
            QueryParameter::string(':encoding_key', (string) $token->getEncodingKey()),
            QueryParameter::bool(':is_revoked', false),
            QueryParameter::int(':creation_date', $token->getCreationDate()->getTimestamp()),
            QueryParameter::null(':expiration_date'),
        ])
    );

    return ['name' => 'cma-default', 'creator_id' => (int) $admin['contact_id']];
};

/**
 * Align inconsistent Agent Configuration with the new schema:
 *      - Add a `tokens` key for each configuration in non reverse
 *      - Add a `token` key for each configuration in reverse
 *      - Add a `id` key for each hosts in each in reverse configuration
 *          - host id is based on the first ID found for this address.
 *          - As many hosts could have the same address, users should validate that the picken host is the good one.
 */
$alignCMAAgentConfigurationWithNewSchema = function () use ($pearDB, &$errorMessage, $generateToken): void {
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
    $tokenInformation = $generateToken();
    foreach ($agentConfigurations as $agentConfiguration) {
        $configuration = json_decode(
            json: $agentConfiguration['configuration'],
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
        if ($configuration['is_reverse']) {
            // `tokens` should be an empty array for reverse connection
            if (! array_key_exists('tokens', $configuration)) {
                $configuration['tokens'] = [];
            }
            if (! isset($configuration['hosts']) || ! is_array($configuration['hosts'])) {
                $configuration['hosts'] = [];
            }
            foreach ($configuration['hosts'] as &$host) {
                if (! array_key_exists('token', $host)) {
                    $host['token'] = $tokenInformation;
                }
                if (! array_key_exists('id', $host)) {
                    $hostId = $pearDB->fetchOne(
                        <<<'SQL'
                            SELECT host_id
                            FROM host
                            WHERE host_address = :hostAddress
                            LIMIT 1
                            SQL,
                        QueryParameters::create([QueryParameter::string(':hostAddress', $host['address'])])
                    );
                    $host['id'] = $hostId;
                }
            }
        } else {
            // `hosts` should be an empty array for not reverse connection
            if (! array_key_exists('hosts', $configuration)) {
                $configuration['hosts'] = [];
            }
            if (! array_key_exists('tokens', $configuration)) {
                $configuration['tokens'] = [$tokenInformation];
            }
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

try {
    // DDL statements for real time database

    // DDL statements for configuration database
    $alterContactPagerSize();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $alignCMAAgentConfigurationWithNewSchema();
    $pearDB->commit();

} catch (Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
