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

    if (! empty($updates)) {
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

// -------------------------------------------- Token -------------------------------------------- //

$addCMATypeToTokenTable = function () use ($pearDB, &$errorMessage) {
    $errorMessage = 'Failed to add column cma type to table security_authentication_tokens';
    $queryAddApiType = "ALTER TABLE security_authentication_tokens MODIFY COLUMN token_type enum('auto','manual','cma','api') DEFAULT 'auto'";
    $pearDB->executeQuery($queryAddApiType);
    $queryUpdateType = "UPDATE security_authentication_tokens SET token_type = 'api' WHERE token_type = 'manual'";
    $pearDB->executeQuery($queryUpdateType);
    $queryRemoveManualType = "ALTER TABLE security_authentication_tokens MODIFY COLUMN token_type enum('auto','api','cma') DEFAULT 'auto'";
    $pearDB->executeQuery($queryRemoveManualType);
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
    // TODO add your function calls to update the real time database structure here

    $addCMATypeToTokenTable();
    $updateTopologyForAuthenticationTokens();
    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateTopologyForHostGroup($pearDB);
    $updateAgentConfiguration($pearDB);

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
