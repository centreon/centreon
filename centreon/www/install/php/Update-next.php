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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';
$errorMessage = '';

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
$updateAgentConfiguration = function () use ($pearDB, &$errorMessage): void {
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
    $pearDB->executeStatement(
        <<<'SQL'
            ALTER TABLE `agent_configuration`
            ADD COLUMN `connection_mode` ENUM('no-tls', 'secure', 'insecure') DEFAULT 'secure' NOT NULL
        SQL
    );
};

try {
    // DDL statements for configuration database
    $addConnectionModeColumnToAgentConfiguration();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateAgentConfiguration();

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
