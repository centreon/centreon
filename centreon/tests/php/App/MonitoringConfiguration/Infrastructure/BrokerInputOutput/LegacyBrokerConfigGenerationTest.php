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

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the broker configuration files generated from the stored broker inputs/outputs.
 *
 * Every scenario reproduces data as one writer stores it (broker API / legacy form, CLAPI, base configuration,
 * remote wizard, poller creation, modules) and pins the generated JSON. These expectations must stay
 * unchanged when the storage of broker inputs/outputs changes.
 *
 * Rows of a flow are read without a defined order (the table has no primary key), so the order of the lists
 * inside a flow (filters, lua parameters, columns) is not significant and is ignored by the comparison.
 * Scenarios whose result depends on that undefined order are deliberately avoided (repeated keys with different
 * values, two rrd outputs in the same configuration, a blockId stored as the very first row of a group whose type
 * has external values).
 *
 * Several pinned behaviors are known quirks of the current generation, kept on purpose:
 * - external values (cb_field.external) are resolved with the blockId of the LAST flow of each input/output
 *   group and only added to that flow, and a blockId stored as the very first row of a group is never found;
 * - repeated keys without fieldIndex (CLAPI lists) collapse to the last value;
 * - rows of the pre-21.04 "logger" group are emitted as a top-level "logger" list;
 * - PHP warnings raised by the generation are part of the snapshot ("php-warnings").
 */
#[Group('broker_io_characterization')]
final class LegacyBrokerConfigGenerationTest extends KernelTestCase
{
    private const CENTRAL_POLLER_ID = BrokerTestDatabase::FIRST_POLLER_ID;
    private const REMOTE_POLLER_ID = BrokerTestDatabase::FIRST_POLLER_ID + 1;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    /** @var list<int> */
    private array $configIds = [];

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::CENTRAL_POLLER_ID, localhost: true);
        $this->database->createPoller(self::REMOTE_POLLER_ID, localhost: false);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testFreshInstallCentralBroker(): void
    {
        $configId = $this->createBrokerConfiguration(self::CENTRAL_POLLER_ID, 'central-broker-master');
        // Rows of www/install/var/baseconf/centreon-broker.sql, placeholders replaced.
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-input',
            'port' => '5669',
            'buffering_timeout' => '0',
            'host' => '',
            'retry_interval' => '15',
            'protocol' => 'bbdo',
            'tls' => 'auto',
            'private_key' => '',
            'public_cert' => '',
            'ca_certificate' => '',
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'compression' => 'auto',
            'compression_level' => '',
            'compression_buffer' => '',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-unified-sql',
            'db_type' => 'mysql',
            'retry_interval' => '15',
            'buffering_timeout' => '0',
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_user' => 'centreon',
            'db_password' => 'db-secret',
            'db_name' => 'centreon_storage',
            'queries_per_transaction' => '',
            'read_timeout' => '',
            'interval' => '60',
            'length' => '15552000',
            'rebuild_check_interval' => '',
            'store_in_data_bin' => 'yes',
            'insert_in_index_data' => '1',
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 1, BrokerFlowFixture::flatRows([
            'name' => 'centreon-broker-master-rrd',
            'port' => '5670',
            'buffering_timeout' => '0',
            'host' => 'localhost',
            'retry_interval' => '15',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => '',
            'public_cert' => '',
            'ca_certificate' => '',
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'compression' => 'no',
            'compression_level' => '',
            'compression_buffer' => '',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 2, [
            ['key' => 'name', 'value' => 'central-broker-master-event-script'],
            ['key' => 'script_path', 'value' => '/usr/share/centreon/bin/console agent-configuration:host:create'],
            ['key' => 'timeout', 'value' => '15'],
            ['key' => 'managed_event_ttl', 'value' => '3600'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'event', 'value' => 'neb:UnknownHost', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'type', 'value' => 'event_script'],
            ['key' => 'blockId', 'value' => '1_37'],
        ]);

        $this->assertGeneration('fresh_install_central', self::CENTRAL_POLLER_ID, localhost: true);
    }

    public function testFlowsAddedThroughTheApiOnCentral(): void
    {
        $configId = $this->createBrokerConfiguration(self::CENTRAL_POLLER_ID, 'central-api');
        $this->flows->addThroughApi($configId, 'input', 35, 'bbdo-server-input', [
            'host' => null,
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => null,
            'encryption' => 'no',
            'private_key' => null,
            'certificate' => null,
            'compression' => 'no',
            'retention' => 'no',
            'filters_category' => ['dumper', 'storage'],
        ]);
        $this->flows->addThroughApi($configId, 'input', 3, 'ipv4-input', [
            'port' => 5670,
            'host' => 'localhost',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => null,
            'tls_hostname' => null,
            'public_cert' => null,
            'ca_certificate' => null,
            'negotiation' => 'no',
            'one_peer_retention_mode' => 'yes',
            'filters_category' => ['neb'],
        ]);
        $this->flows->addThroughApi($configId, 'output', 33, 'stream-connector', [
            'path' => '/usr/share/centreon-broker/lua/connector.lua',
            'filters_category' => ['dumper', 'storage'],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'lua-param-1', 'value' => 'value-1'],
                ['type' => 'number', 'name' => 'lua-param-2', 'value' => '999'],
                ['type' => 'password', 'name' => 'lua-param-3', 'value' => 'lua-secret'],
            ],
        ]);
        $this->flows->addThroughApi($configId, 'output', 31, 'influxdb', [
            'cache' => 'no',
            'db_host' => '127.0.0.1',
            'db_port' => 8086,
            'db_user' => 'influx-user',
            'db_password' => 'influx-secret',
            'db_name' => 'metrics',
            'metrics_timeseries' => '$METRIC$',
            'status_timeseries' => '$INSTANCE$',
            'metrics_column' => [
                ['is_tag' => 'true', 'type' => 'string', 'value' => '$HOST$', 'name' => 'host'],
                ['is_tag' => 'false', 'type' => 'number', 'value' => '$VALUE$', 'name' => 'value'],
            ],
            'status_column' => [['is_tag' => 'true', 'type' => 'string', 'value' => '$STATE$', 'name' => 'state']],
        ]);
        $this->flows->addThroughApi($configId, 'output', 36, 'bbdo-client-output', [
            'host' => '127.0.0.1',
            'port' => 5671,
            'retry_interval' => null,
            'transport_protocol' => 'gRPC',
            'authorization' => null,
            'encryption' => 'no',
            'ca_certificate' => null,
            'ca_name' => null,
            'compression' => 'no',
            'filters_category' => ['dumper', 'neb'],
        ]);
        $this->flows->addThroughApi($configId, 'output', 11, 'file-output', [
            'path' => '/var/lib/centreon-broker/file-output',
            'protocol' => 'bbdo',
            'max_size' => 512,
            'filters_category' => ['correlation'],
        ]);
        // Last output: its blockId drives the external values (interval, length, db_type, insert_in_index_data).
        $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql', [
            'interval' => 75,
            'length' => 15553000,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_user' => 'centreon',
            'db_password' => '',
            'queries_per_transaction' => 10000,
            'connections_count' => 3,
            'db_name' => 'centreon_storage',
            'read_timeout' => 4500,
            'rebuild_check_interval' => 12,
            'store_in_data_bin' => 'no',
            'insert_in_index_data' => '1',
            'cleanup_check_interval' => 7500,
            'instance_timeout' => 5000,
            'db_type' => 'mysql',
            'filters_category' => ['dumper', 'storage'],
        ]);

        $this->assertGeneration('api_flows_central', self::CENTRAL_POLLER_ID, localhost: true);
    }

    public function testRrdAndStorageOutputsOnRemotePoller(): void
    {
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'poller-rrd');
        $this->flows->addThroughApi($configId, 'input', 3, 'rrd-input', [
            'port' => 5670,
            'host' => '',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => null,
            'tls_hostname' => null,
            'public_cert' => null,
            'ca_certificate' => null,
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'filters_category' => [],
        ]);
        $this->flows->addThroughApi($configId, 'output', 14, 'perfdata', [
            'interval' => 120,
            'length' => 155520000,
            'db_type' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_user' => 'centreon',
            'connections_count' => 2,
            'db_password' => 'storage-secret',
            'db_name' => 'centreon_storage',
            'queries_per_transaction' => 500,
            'read_timeout' => 3600,
            'rebuild_check_interval' => 60,
            'store_in_data_bin' => 'no',
            'insert_in_index_data' => '2',
            'filters_category' => ['storage', 'neb'],
        ]);
        // Last output: rrdcached over TCP, its blockId (1_13) drives the external metrics/status paths.
        $this->flows->addThroughApi($configId, 'output', 13, 'rrd-tcp', [
            'metrics_path' => '/var/lib/centreon/metrics/',
            'status_path' => '/var/lib/centreon/status/',
            'rrd_cached_option' => 'tcp',
            'rrd_cached' => '42217',
            'store_in_data_bin' => 'no',
            'write_metrics' => 'yes',
            'insert_in_index_data' => '2',
            'write_status' => 'yes',
            'filters_category' => ['storage'],
        ]);

        $this->assertGeneration('api_flows_remote_poller', self::REMOTE_POLLER_ID, localhost: false);
    }

    /**
     * Kept apart from the TCP case: rrd_cached_option and rrd_cached are tracked per input/output group, not per
     * flow, so two rrd outputs in the same configuration leak into each other depending on the row order.
     */
    public function testRrdOutputThroughUnixSocket(): void
    {
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'poller-rrd-unix');
        $this->flows->addThroughApi($configId, 'output', 13, 'rrd-unix', [
            'metrics_path' => '/var/lib/centreon/metrics/',
            'status_path' => '/var/lib/centreon/status/',
            'rrd_cached_option' => 'unix',
            'rrd_cached' => '/var/run/rrdcached.sock',
            'store_in_data_bin' => 'no',
            'write_metrics' => 'yes',
            'insert_in_index_data' => '2',
            'write_status' => 'yes',
            'filters_category' => ['storage'],
        ]);

        $this->assertGeneration('api_rrd_unix_socket', self::REMOTE_POLLER_ID, localhost: false);
    }

    public function testFlowsStoredByCliRemoteWizardAndPollerCreation(): void
    {
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'poller-module', daemon: false);
        // CLAPI ADDINPUT: blockId inserted first, group id 0, required fields only, no grp columns.
        // Being the first row of the only input, its blockId is never found by the generation.
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'blockId' => '2_3',
            'type' => 'ipv4',
            'name' => 'clapi-input',
            'port' => '5669',
            'protocol' => 'bbdo',
        ]));
        // App poller creation (DbalBrokerConfigurationRepository): group id 0, type and blockId stored last.
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'poller-module-output',
            'port' => '5669',
            'host' => '10.0.0.1',
            'failover' => '',
            'retry_interval' => '15',
            'buffering_timeout' => '0',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'compression' => 'no',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));
        // CLAPI SETOUTPUT on lists: parent row duplicated on each call, children linked to the field group id,
        // grouped fields stored without fieldIndex (kept as plain keys).
        $this->flows->insertLegacyRows($configId, 'output', 1, [
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'name', 'value' => 'clapi-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/clapi.lua'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 7],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 7],
            ['key' => 'category', 'value' => 'storage', 'grp_level' => 1, 'parent_grp_id' => 7],
            ['key' => 'lua_parameter__name', 'value' => 'clapi-param'],
        ]);
        // Remote wizard (LinkedPollerConfigurationService): forward-to-master template inserted
        // with grp_level only, so the filters hierarchy columns are lost.
        $this->flows->insertLegacyRows($configId, 'output', 2, [
            ['key' => 'name', 'value' => 'forward-to-master'],
            ['key' => 'port', 'value' => '5669'],
            ['key' => 'host', 'value' => '192.168.0.10'],
            ['key' => 'retry_interval', 'value' => '15'],
            ['key' => 'buffering_timeout', 'value' => '0'],
            ['key' => 'protocol', 'value' => 'bbdo'],
            ['key' => 'tls', 'value' => 'no'],
            ['key' => 'negotiation', 'value' => 'yes'],
            ['key' => 'one_peer_retention_mode', 'value' => 'no'],
            ['key' => 'compression', 'value' => 'no'],
            ['key' => 'filters', 'value' => ''],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'blockId', 'value' => '1_3'],
        ]);

        $this->assertGeneration('legacy_writers_poller', self::REMOTE_POLLER_ID, localhost: false);
    }

    public function testFlowsStoredByModules(): void
    {
        $this->database->setOption('saas_token', 'saas-token');
        $this->database->setOption('saas_url', 'https://saas.example.com');
        $this->database->setOption('saas_use_proxy', '0');

        $configId = $this->createBrokerConfiguration(self::CENTRAL_POLLER_ID, 'central-modules');
        // centreon-map configure.sh: ipv4 output with hardcoded blockId and neb/bam categories.
        $this->flows->insertLegacyRows($configId, 'output', 1, [
            ['key' => 'name', 'value' => 'centreon-map'],
            ['key' => 'port', 'value' => '5758'],
            ['key' => 'host', 'value' => ''],
            ['key' => 'failover', 'value' => ''],
            ['key' => 'retry_interval', 'value' => '60'],
            ['key' => 'buffering_timeout', 'value' => '0'],
            ['key' => 'protocol', 'value' => 'bbdo'],
            ['key' => 'tls', 'value' => 'auto'],
            ['key' => 'private_key', 'value' => ''],
            ['key' => 'public_cert', 'value' => ''],
            ['key' => 'ca_certificate', 'value' => ''],
            ['key' => 'negociation', 'value' => 'yes'],
            ['key' => 'one_peer_retention_mode', 'value' => 'no'],
            ['key' => 'compression', 'value' => 'auto'],
            ['key' => 'compression_level', 'value' => ''],
            ['key' => 'compression_buffer', 'value' => ''],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'blockId', 'value' => '1_3'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'bam', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        // centreon-bam installBrokerConf.php: monitoring and reporting outputs, flat rows.
        $this->flows->insertLegacyRows($configId, 'output', 2, BrokerFlowFixture::flatRows([
            'blockId' => '1_38',
            'name' => 'centreon-bam-monitoring',
            'type' => 'bam',
            'cache' => 'yes',
            'db_name' => 'centreon',
            'db_type' => 'mysql',
            'db_host' => 'localhost',
            'db_user' => 'centreon',
            'db_password' => 'bam-secret',
            'db_port' => '3306',
            'storage_db_name' => 'centreon_storage',
            'queries_per_transaction' => '0',
            'command_file' => '/var/lib/centreon-engine/rw/centengine.cmd',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 3, [
            ['key' => 'blockId', 'value' => '1_39'],
            ['key' => 'name', 'value' => 'centreon-bam-reporting'],
            ['key' => 'type', 'value' => 'bam_bi'],
            ['key' => 'db_name', 'value' => 'centreon_storage'],
            ['key' => 'db_type', 'value' => 'mysql'],
            ['key' => 'db_host', 'value' => 'localhost'],
            ['key' => 'db_user', 'value' => 'centreon'],
            ['key' => 'db_password', 'value' => 'bam-secret'],
            ['key' => 'db_port', 'value' => '3306'],
            ['key' => 'queries_per_transaction', 'value' => '0'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'bam', 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        // centreon-anomaly-detection DB-Func.php addOutputLua: lua parameters merged with the SaaS options.
        $this->flows->insertLegacyRows($configId, 'output', 4, [
            ['key' => 'name', 'value' => 'forward-to-anomaly-detection'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua'],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'lua_parameter__type', 'value' => 'number', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'max_queue_size', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '100', 'fieldIndex' => 0],
        ]);

        $this->assertGeneration('module_flows_central', self::CENTRAL_POLLER_ID, localhost: true);
    }

    public function testLegacyLoggerRowsAreGenerated(): void
    {
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'pre-21-04-loggers');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'input',
            'port' => '5669',
            'protocol' => 'bbdo',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'output',
            'host' => 'central.example.com',
            'port' => '5669',
            'protocol' => 'bbdo',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'logger', 0, BrokerFlowFixture::flatRows([
            'name' => '/var/log/centreon-broker/pre-21-04.log',
            'config' => 'yes',
            'debug' => 'no',
            'error' => 'yes',
            'info' => 'no',
            'level' => 'low',
            'type' => 'file',
            'blockId' => '3_17',
        ]));

        $this->assertGeneration('legacy_logger_rows', self::REMOTE_POLLER_ID, localhost: false);
    }

    public function testPasswordsAreEncryptedForEncryptionReadyPollers(): void
    {
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'encrypted');
        $this->addSecretBearingOutputs($configId, 'db-secret', 'lua-secret');

        $monitoringServers = $this->createStub(ReadMonitoringServerRepositoryInterface::class);
        $monitoringServers->method('isEncryptionReady')->willReturn(true);

        $this->assertGeneration(
            'encryption_ready_poller',
            self::REMOTE_POLLER_ID,
            localhost: false,
            generator: new LegacyBrokerConfigGenerator($this->database, $monitoringServers),
        );
    }

    public function testVaultPathsAreResolved(): void
    {
        $vaultPath = 'secret::hashicorp_vault::monitoring/data/configuration/broker/0f5e4a2c-uuid';
        $configId = $this->createBrokerConfiguration(self::REMOTE_POLLER_ID, 'vault');
        $this->addSecretBearingOutputs($configId, $vaultPath, $vaultPath);

        $vault = $this->createStub(ReadVaultRepositoryInterface::class);
        $vault->method('findFromPath')->willReturn([
            'unified-sql-with-secrets_db_password' => 'db-secret-from-vault',
            'lua-with-secrets_lua_parameter_api-key' => 'lua-secret-from-vault',
        ]);

        $this->assertGeneration(
            'vault_paths',
            self::REMOTE_POLLER_ID,
            localhost: false,
            generator: new LegacyBrokerConfigGenerator($this->database, $this->notEncryptionReady(), $vault),
        );
    }

    private function addSecretBearingOutputs(int $configId, string $dbPassword, string $luaPassword): void
    {
        $this->flows->addThroughApi($configId, 'output', 33, 'lua-with-secrets', [
            'path' => '/usr/share/centreon-broker/lua/secrets.lua',
            'filters_category' => [],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'endpoint', 'value' => 'https://example.com'],
                ['type' => 'password', 'name' => 'api-key', 'value' => $luaPassword],
            ],
        ]);
        $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql-with-secrets', [
            'interval' => 60,
            'length' => 15552000,
            'db_host' => 'db.example.com',
            'db_port' => 3306,
            'db_user' => 'centreon',
            'db_password' => $dbPassword,
            'queries_per_transaction' => 1000,
            'connections_count' => 3,
            'db_name' => 'centreon_storage',
            'read_timeout' => 3600,
            'rebuild_check_interval' => 60,
            'store_in_data_bin' => 'yes',
            'insert_in_index_data' => '1',
            'cleanup_check_interval' => 0,
            'instance_timeout' => 300,
            'db_type' => 'mysql',
            'filters_category' => [],
        ]);
    }

    private function createBrokerConfiguration(int $pollerId, string $name, bool $daemon = true): int
    {
        $configId = $this->database->createBrokerConfiguration($pollerId, $name, $daemon);
        $this->configIds[] = $configId;

        return $configId;
    }

    private function notEncryptionReady(): ReadMonitoringServerRepositoryInterface
    {
        $monitoringServers = $this->createStub(ReadMonitoringServerRepositoryInterface::class);
        $monitoringServers->method('isEncryptionReady')->willReturn(false);

        return $monitoringServers;
    }

    private function assertGeneration(
        string $snapshot,
        int $pollerId,
        bool $localhost,
        ?LegacyBrokerConfigGenerator $generator = null,
    ): void {
        $generator ??= new LegacyBrokerConfigGenerator($this->database, $this->notEncryptionReady());

        $files = $this->normalize($generator->generate($pollerId, $localhost));
        $files['php-warnings'] = $generator->warnings();

        JsonSnapshot::assertMatches($snapshot, $files);
    }

    /**
     * Replaces the values depending on auto-increment ids or on the test platform.
     *
     * @param array<string, array<mixed>> $files
     *
     * @return array<string, array<mixed>>
     */
    private function normalize(array $files): array
    {
        $placeholders = array_flip($this->configIds);
        foreach ($files as $name => $file) {
            $broker = $file['centreonBroker'] ?? null;
            if (! is_array($broker)) {
                continue;
            }
            if (! isset($broker['broker_id'])) {
                continue;
            }
            foreach (['input', 'output', 'logger'] as $group) {
                $flows = $broker[$group] ?? null;
                if (! is_array($flows)) {
                    continue;
                }
                foreach ($flows as $flowIndex => $flow) {
                    $flows[$flowIndex] = is_array($flow) ? self::sortListsRecursively($this->hidePlatformUuid($flow)) : $flow;
                }
                $broker[$group] = $flows;
            }
            $index = $placeholders[$broker['broker_id']] ?? 'unknown';
            $broker['broker_id'] = '<broker-' . $index . '>';
            $broker['grpc'] = ['port' => '<51000 + broker-' . $index . '>'];
            $files[$name]['centreonBroker'] = $broker;
        }

        return $files;
    }

    /**
     * @param array<mixed> $flow
     *
     * @return array<mixed>
     */
    private function hidePlatformUuid(array $flow): array
    {
        if (! is_array($flow['lua_parameter'] ?? null)) {
            return $flow;
        }
        foreach ($flow['lua_parameter'] as $index => $parameter) {
            if (is_array($parameter) && ($parameter['name'] ?? null) === 'centreon_platform_uuid') {
                $parameter['value'] = '<platform-uuid>';
                $flow['lua_parameter'][$index] = $parameter;
            }
        }

        return $flow;
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function sortListsRecursively(array $value): array
    {
        $value = array_map(
            static fn (mixed $item): mixed => is_array($item) ? self::sortListsRecursively($item) : $item,
            $value,
        );
        if (array_is_list($value)) {
            usort(
                $value,
                static fn (mixed $left, mixed $right): int => strcmp(
                    json_encode(self::sortKeys($left), JSON_THROW_ON_ERROR),
                    json_encode(self::sortKeys($right), JSON_THROW_ON_ERROR),
                ),
            );
        }

        return $value;
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(self::sortKeys(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
