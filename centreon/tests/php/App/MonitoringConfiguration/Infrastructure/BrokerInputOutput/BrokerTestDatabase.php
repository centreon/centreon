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

declare(strict_types=1);

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use Centreon\Infrastructure\DatabaseConnection;

/**
 * Autocommit connections to the integration test databases, for exercising the legacy broker code
 * (config generation, CLAPI, legacy form, Core repositories) which opens its own PDO connections
 * and therefore cannot see data held in the DAMA transaction of the Doctrine connection.
 *
 * Connection settings are always read from the test environment, never from the legacy
 * `db`/`hostCentreon` constants which point to the real platform database.
 *
 * Every poller and broker configuration created through this class is deleted by cleanUp().
 */
final class BrokerTestDatabase
{
    /** Ids far above anything created by the base configuration or by other tests. */
    public const FIRST_POLLER_ID = 9100;

    public readonly \CentreonDB $configuration;

    public readonly \CentreonDB $realtime;

    public readonly DatabaseConnection $connection;

    /** @var list<int> */
    private array $pollerIds = [];

    /** @var list<int> */
    private array $brokerConfigIds = [];

    /** @var array<string, string|null> option values to restore on cleanUp(), null when absent */
    private array $originalOptions = [];

    public function __construct()
    {
        $configuration = $this->connectionConfig($this->env('db'));
        $this->configuration = new \CentreonDB(\CentreonDB::LABEL_DB_CONFIGURATION, 1, $configuration);

        $this->realtime = new \CentreonDB(\CentreonDB::LABEL_DB_REALTIME, 1, $this->connectionConfig($this->env('dbcstg')));
        $this->connection = DatabaseConnection::createFromConfig($configuration);
    }

    public function createPoller(int $pollerId, bool $localhost = false): void
    {
        $statement = $this->configuration->prepare(
            <<<'SQL'
                INSERT INTO nagios_server (
                    id, uid, name, localhost, ns_activate, ns_ip_address,
                    centreonbroker_module_path, centreonbroker_cfg_path, centreonbroker_logs_path
                ) VALUES (
                    :id, :uid, :name, :localhost, '1', '127.0.0.1',
                    '/usr/share/centreon/lib/centreon-broker', '/etc/centreon-broker', NULL
                )
                SQL
        );
        $statement->bindValue(':id', $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':uid', 100000000000000 + $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':name', 'broker-io-poller-' . $pollerId);
        $statement->bindValue(':localhost', $localhost ? '1' : '0');
        $statement->execute();
        $this->pollerIds[] = $pollerId;
    }

    /**
     * Creates an active broker configuration with every logger set to the "error" level.
     *
     * @return int the config_id
     */
    public function createBrokerConfiguration(int $pollerId, string $name, bool $daemon = true): int
    {
        $statement = $this->configuration->prepare(
            <<<'SQL'
                INSERT INTO cfg_centreonbroker (
                    config_name, config_filename, config_write_timestamp, config_write_thread_id,
                    log_directory, log_filename, log_max_size, config_activate, ns_nagios_server,
                    event_queue_max_size, command_file, cache_directory, stats_activate, daemon, bbdo_version
                ) VALUES (
                    :name, :filename, '0', '0',
                    '/var/log/centreon-broker', :logFilename, 0, '1', :pollerId,
                    100000, '/var/lib/centreon-broker/command.sock', '/var/lib/centreon-broker', '1', :daemon, '3.1.0'
                )
                SQL
        );
        $statement->bindValue(':name', $name);
        $statement->bindValue(':filename', $name . '.json');
        $statement->bindValue(':logFilename', $name . '.log');
        $statement->bindValue(':pollerId', $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':daemon', $daemon ? 1 : 0, \PDO::PARAM_INT);
        $statement->execute();

        $configId = (int) $this->configuration->lastInsertId();
        $this->brokerConfigIds[] = $configId;

        $statement = $this->configuration->prepare(
            <<<'SQL'
                INSERT INTO cfg_centreonbroker_log (id_centreonbroker, id_log, id_level)
                SELECT :configId, log.id, level.id
                FROM cb_log log
                JOIN cb_log_level level ON level.name = 'error'
                SQL
        );
        $statement->bindValue(':configId', $configId, \PDO::PARAM_INT);
        $statement->execute();

        return $configId;
    }

    public function setOption(string $key, string $value): void
    {
        if (! array_key_exists($key, $this->originalOptions)) {
            $statement = $this->configuration->prepare('SELECT `value` FROM options WHERE `key` = :key');
            $statement->bindValue(':key', $key);
            $statement->execute();
            $original = $statement->fetchColumn();
            $this->originalOptions[$key] = $original === false ? null : (string) $original;
        }
        $this->writeOption($key, $value);
    }

    /**
     * Deletes everything created through this instance. Broker flows are removed by the
     * ON DELETE CASCADE foreign keys of cfg_centreonbroker.
     */
    public function cleanUp(): void
    {
        foreach ($this->brokerConfigIds as $configId) {
            $statement = $this->configuration->prepare('DELETE FROM cfg_centreonbroker WHERE config_id = :id');
            $statement->bindValue(':id', $configId, \PDO::PARAM_INT);
            $statement->execute();
        }
        foreach ($this->pollerIds as $pollerId) {
            $statement = $this->configuration->prepare('DELETE FROM nagios_server WHERE id = :id');
            $statement->bindValue(':id', $pollerId, \PDO::PARAM_INT);
            $statement->execute();
        }
        foreach ($this->originalOptions as $key => $value) {
            $this->writeOption($key, $value);
        }
        $this->brokerConfigIds = [];
        $this->pollerIds = [];
        $this->originalOptions = [];
    }

    private function writeOption(string $key, ?string $value): void
    {
        $statement = $this->configuration->prepare('DELETE FROM options WHERE `key` = :key');
        $statement->bindValue(':key', $key);
        $statement->execute();
        if ($value === null) {
            return;
        }
        $statement = $this->configuration->prepare('INSERT INTO options (`key`, `value`) VALUES (:key, :value)');
        $statement->bindValue(':key', $key);
        $statement->bindValue(':value', $value);
        $statement->execute();
    }

    private function connectionConfig(string $databaseName): ConnectionConfig
    {
        return new ConnectionConfig(
            host: $this->env('hostCentreon'),
            user: $this->env('user'),
            password: $this->env('password'),
            databaseNameConfiguration: $databaseName,
            databaseNameRealTime: $this->env('dbcstg'),
            port: (int) $this->env('port'),
        );
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? null;
        if (! is_string($value) || $value === '') {
            throw new \LogicException(sprintf('Environment variable "%s" is required by the integration tests', $name));
        }

        return $value;
    }
}
