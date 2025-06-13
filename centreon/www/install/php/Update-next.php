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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';
$errorMessage = '';

// -------------------------------------------- SAML configuration -------------------------------------------- //

$updateSamlProviderConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve SAML provider configuration';
    $samlConfiguration = $pearDB->fetchAssociative(
        <<<'SQL'
            SELECT * FROM `provider_configuration`
            WHERE `type` = 'saml'
            SQL
    );

    if (! $samlConfiguration || ! isset($samlConfiguration['custom_configuration'])) {
        throw new \Exception('SAML configuration is missing');
    }

    $customConfiguration = json_decode($samlConfiguration['custom_configuration'], true, JSON_THROW_ON_ERROR);

    if (!isset($customConfiguration['requested_authn_context'])) {
        $customConfiguration['requested_authn_context'] = 'minimum';
        $query = <<<'SQL'
                UPDATE `provider_configuration`
                SET `custom_configuration` = :custom_configuration
                WHERE `type` = 'saml'
            SQL;
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::string(
                    'custom_configuration',
                    json_encode($customConfiguration, JSON_THROW_ON_ERROR)
                )
            ]
        );

        $pearDB->update($query, $queryParameters);
    }
};

// -------------------------------------------- Token -------------------------------------------- //

$createJwtTable = function () use ($pearDB, &$errorMessage) {
    $errorMessage = 'Failed to create table jwt_tokens';

    $pearDB->executeQuery(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `jwt_tokens` (
                `token_string` varchar(4096) DEFAULT NULL COMMENT 'Encoded JWT token',
                `token_name` VARCHAR(255) NOT NULL COMMENT 'Token name',
                `creator_id` INT(11) DEFAULT NULL COMMENT 'User ID of the token creator',
                `creator_name` VARCHAR(255) DEFAULT NULL COMMENT 'User name of the token creator',
                `encoding_key` VARCHAR(255) DEFAULT NULL COMMENT 'encoding key',
                `is_revoked` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Define if token is revoked',
                `creation_date` bigint UNSIGNED NOT NULL COMMENT 'Creation date of the token',
                `expiration_date` bigint UNSIGNED DEFAULT NULL COMMENT 'Expiration date of the token',
                PRIMARY KEY (`token_name`),
                CONSTRAINT `jwt_tokens_user_id_fk` FOREIGN KEY (`creator_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table for JWT tokens'
            SQL
    );
};

$updateTopologyForAuthenticationTokens = function () use ($pearDB, &$errorMessage) {
    $errorMessage = 'Unable to update new authentication tokens topology';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `topology`
                SET
                    `topology_name` = 'Authentication Tokens',
                    `topology_url` = '/administration/authentication-token'
            WHERE `topology_name` = 'API Tokens' AND `topology_url` = '/administration/api-token';
            SQL
    );
};

try {
    // DDL statements for configuration database
    $createJwtTable();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateSamlProviderConfiguration($pearDB);
    $updateTopologyForAuthenticationTokens();

    $pearDB->commit();

} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (\PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new \Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new \Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
