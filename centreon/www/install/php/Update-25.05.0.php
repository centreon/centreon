<?php

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
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
$version = '25.05.0';
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
            SET `is_react` = '1',
                `topology_url` = '/configuration/hosts/groups'
            WHERE `topology_name` = 'Host Groups'
                AND `topology_page` = 60102
        SQL
    );
};

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

$sunsetHostGroupFields = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update hostgroup table';
    $pearDB->executeQuery(
        <<<'SQL'
            ALTER TABLE `hostgroup`
                DROP COLUMN `hg_notes`,
                DROP COLUMN `hg_notes_url`,
                DROP COLUMN `hg_action_url`,
                DROP COLUMN `hg_map_icon_image`,
                DROP COLUMN `hg_rrd_retention`
            SQL
    );
};

// -------------------------------------------- Agent Configuration -------------------------------------------- //
/**
 * Add prefix "/etc/pki/" and extensions (.crt, .key) to certificate and key paths in agent_configuration table.
 *
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 *
 * @return void
 */
$updateAgentConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve data from agent_configuration table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT `id`, `configuration` FROM `agent_configuration`
        SQL
    );

    $errorMessage = 'Unable to update agent_configuration table';
    $updates = [];
    while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
        $config = json_decode($row['configuration'], true);
        if (! is_array($config)) {
            continue;
        }

        foreach ($config as $key => $value) {
            if (str_ends_with($key, '_certificate') && is_string($value)) {
                $filename = str_starts_with($value, '/etc/pki/') ? substr($value, 9) : ltrim($value, '/');
                $filename = preg_replace('/(\.crt|\.cer)$/', '', $filename);
                $config[$key] = '/etc/pki/' . ltrim($filename, '/') . '.crt';
            } elseif (str_ends_with($key, '_key') && is_string($value)) {
                $filename = str_starts_with($value, '/etc/pki/') ? substr($value, 9) : ltrim($value, '/');
                $filename = preg_replace('/\.key$/', '', $filename);
                $config[$key] = '/etc/pki/' . ltrim($filename, '/') . '.key';
            }

            if ($key === 'hosts') {
                foreach ($value as $index => $host) {
                    if (! is_array($host)) {
                        continue;
                    }

                    if (isset($host['poller_ca_certificate']) && is_string($host['poller_ca_certificate'])) {
                        $config[$key][$index]['poller_ca_certificate'] = '/etc/pki/' . ltrim($host['poller_ca_certificate'], '/') . '.crt';
                    }
                }
            }
        }

        $updatedConfig = json_encode($config);
        $updates[] = [
            'id' => $row['id'],
            'configuration' => $updatedConfig
        ];
    }

    if ($updates !== []) {
        $query = 'UPDATE `agent_configuration` SET `configuration` = CASE `id` ';
        $params = [];
        $whereParams = [];

        foreach ($updates as $index => $update) {
            $idParam = ":case_id{$index}";
            $configParam = ":case_config{$index}";
            $query .= "WHEN {$idParam} THEN {$configParam} ";
            $params[$idParam] = $update['id'];
            $params[$configParam] = $update['configuration'];

            $whereParams[] = ":where_id{$index}";
            $params[":where_id{$index}"] = $update['id'];
        }

        $query .= 'END WHERE `id` IN (' . implode(', ', $whereParams) . ')';

        $statement = $pearDB->prepareQuery($query);
        $pearDB->executePreparedQuery($statement, $params);
    }
};

/**
 * Add Column connection_mode to agent_configuration table.
 * This Column is used to define the connection mode of the agent between ("no-tls","tls","secure","insecure").
 *
 * @param CentreonDB $pearDB
 *
 * @throws CentreonDbException
 */
$addConnectionModeColumnToAgentConfiguration = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to add connection_mode column to agent_configuration table';

    if ($pearDB->isColumnExist('agent_configuration', 'connection_mode')) {
        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `agent_configuration`
            ADD COLUMN `connection_mode` ENUM('no-tls', 'secure', 'insecure') DEFAULT 'secure' NOT NULL
            SQL
    );
};

// -------------------------------------------- Token -------------------------------------------- //

$createJwtTable = function () use ($pearDB, &$errorMessage): void {
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

$updateTopologyForAuthenticationTokens = function () use ($pearDB, &$errorMessage): void {
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
  
// -------------------------------------------- Broker modules directive -------------------------------------------- //
$addColumnInEngineConf = function() use($pearDB, &$errorMessage): void {
    $errorMessage = 'Unabled to add column in cfg_nagios table';

    if ($pearDB->isColumnExist('cfg_nagios', 'broker_module_cfg_file')) {
        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `cfg_nagios`
            ADD COLUMN `broker_module_cfg_file` VARCHAR(255) DEFAULT NULL
        SQL
    );
};

$removeBrokerModuleDirectiveAndAddBrokerModuleConfigFile = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to get data from cfg_nagios_broker_module table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT `cfg_nagios_id`, `broker_module` FROM `cfg_nagios_broker_module`
            WHERE `broker_module` LIKE '%cbmod.so %.json'
        SQL
    );

    $brokerNagiosPair = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

    $errorMessage= 'Unable to update cfg_nagios table';
    $preparedStatement = $pearDB->prepareQuery(
        <<<'SQL'
            UPDATE `cfg_nagios`
            SET `broker_module_cfg_file` = :broker_module_config_file
            WHERE `nagios_id` = :nagios_id
        SQL
    );
    foreach ($brokerNagiosPair as $nagiosId => $brokerModuleDirective) {
        $brokerConfigFile = preg_match('/cbmod\.so (.+\.json)/', $brokerModuleDirective, $matches) ? $matches[1] : '';
        $pearDB->executePreparedQuery(
            $preparedStatement,
            [
                ':broker_module_config_file' => $brokerConfigFile,
                ':nagios_id' => $nagiosId,
            ]
        );
    }

    $errorMessage = 'Unable to delete rows from cfg_nagios_broker_module table';

    $pearDB->executeStatement(
        <<<'SQL'
            DELETE FROM `cfg_nagios_broker_module`
            WHERE `broker_module` LIKE '%cbmod.so %.json'
        SQL
    );
};


try {
    $createJwtTable();
    $addConnectionModeColumnToAgentConfiguration();
    $addColumnInEngineConf();
    $sunsetHostGroupFields();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateTopologyForHostGroup($pearDB);
    $updateSamlProviderConfiguration($pearDB);
    $updateAgentConfiguration($pearDB);
    $updateTopologyForAuthenticationTokens();
    $removeBrokerModuleDirectiveAndAddBrokerModuleConfigFile();

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
