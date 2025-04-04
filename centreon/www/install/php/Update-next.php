<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = 'xx.xx.x';
$errorMessage = '';

// -------------------------------------------- Host Group Configuration -------------------------------------------- //

/**
 * Update topology for host group configuration pages.
 *
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 */
$updateTopologyForHostGroup = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve data from topology table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `topology`
            WHERE `topology_name` = 'Host Groups'
                AND `topology_page` = 60105
        SQL
    );
    $topologyAlreadyExists = (bool) $statement->fetch(\PDO::FETCH_COLUMN);

    if (! $topologyAlreadyExists) {
        $errorMessage = 'Unable to insert new host group configuration topology';
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`,`topology_url`,`readonly`,`is_react`,`topology_parent`,`topology_page`,`topology_order`,`topology_group`,`topology_show`)
                VALUES ('Host Groups', '/configuration/hosts/groups', '1', '1', 601, 60105,21,1,'1')
            SQL
        );
    }

    $errorMessage = 'Unable to update old host group configuration topology';
    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_name` = 'Host Groups (deprecated)',
                `topology_show` =  '0'
            WHERE `topology_page` = 60102
        SQL
    );
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
                REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
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
    $createJwtTable($peadDB);

    $updateTopologyForAuthenticationTokens();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateTopologyForHostGroup($pearDB);

    $pearDB->commit();

} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        customContext: [
            'exception' => [
                'error_message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]
        ],
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
            customContext: [
                'error_to_rollback' => $errorMessage,
                'exception' => [
                    'error_message' => $rollbackException->getMessage(),
                    'trace' => $rollbackException->getTraceAsString()
                ]
            ],
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
