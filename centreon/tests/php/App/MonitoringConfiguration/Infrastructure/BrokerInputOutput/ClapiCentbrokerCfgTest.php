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

use CentreonClapi\CentreonAPI;
use CentreonClapi\CentreonCentbrokerCfg;
use CentreonClapi\CentreonExported;
use CentreonClapi\CentreonInstance;
use CentreonClapi\CentreonObject;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Pimple\Container;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the CLAPI object CENTBROKERCFG (www/class/centreon-clapi/centreonCentbrokerCfg.class.php)
 * for the broker inputs/outputs actions: ADD/SET/GET/LIST/DEL INPUT|OUTPUT and EXPORT.
 *
 * The real CLAPI class is driven with a Pimple container holding the test database connections, and every
 * printed line is captured. Expectations only rely on what CLAPI prints and on the generated broker
 * configuration, never on the storage, so they must stay unchanged when the storage changes.
 *
 * Rows of a flow are read without a defined order (the table has no primary key), so:
 * - GET lines sharing the same parameter key, and LIST lines sharing the same flow id, are compared as sets;
 * - EXPORT flows are compared per tag in their printed order, their SET lines and category values as sets.
 *
 * Pinned quirks of the current behavior:
 * - ADD stores the raw "external" definition (e.g. "T=options:C=value:...") as value of required external fields;
 * - ADD/SET split values on commas: "a,b" as flow name or as scalar value stores two rows;
 * - SET of a multiselect (category) adds a new empty "filters" row on each call;
 * - grouped fields (lua_parameter, metrics_column...) cannot be set through their stored keys, and setting their
 *   sub-field names ("name", "type", "value") overwrites the flow's own name/type rows;
 * - EXPORT skips the flows whose blockId type is unknown, and exports grouped fields without their index.
 */
#[Group('broker_io_characterization')]
final class ClapiCentbrokerCfgTest extends KernelTestCase
{
    private const POLLER_ID = 9300;
    private const GENERATION_POLLER_ID = 9301;
    private const CENTREON_DIRECTORY = __DIR__ . '/../../../../../..';

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private CentreonCentbrokerCfg $clapi;

    /** @var list<array{command: string, output: array<mixed>, error?: string}> */
    private array $transcript = [];

    /** @var list<int> */
    private array $configIds = [];

    private string $originalIncludePath = '';

    private mixed $originalApiInstance = null;

    private mixed $originalExportedInstance = null;

    private bool $hadPearDbo = false;

    private mixed $originalPearDbo = null;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::POLLER_ID, localhost: false);
        $this->database->createPoller(self::GENERATION_POLLER_ID, localhost: true);

        $this->clapi = $this->createClapi();
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        (new \ReflectionProperty(CentreonAPI::class, 'instance'))->setValue(null, $this->originalApiInstance);
        (new \ReflectionProperty(CentreonExported::class, 'instance'))->setValue(null, $this->originalExportedInstance);
        if ($this->hadPearDbo) {
            $GLOBALS['pearDBO'] = $this->originalPearDbo;
        } else {
            unset($GLOBALS['pearDBO']);
        }
        set_include_path($this->originalIncludePath);
        parent::tearDown();
    }

    public function testAddFlowsStoreRequiredFieldsWithDefaults(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9300-defaults');
        $inputTypes = ['ipv4', 'ipv6', 'file', 'bbdo_server', 'bbdo_client'];
        $outputTypes = [
            'ipv4', 'ipv6', 'file', 'rrd', 'storage', 'sql', 'unified_sql', 'lua', 'influxdb', 'graphite',
            'bbdo_server', 'bbdo_client', 'event_script', 'db_cfg_reader', 'db_cfg_writer',
        ];
        foreach ($inputTypes as $type) {
            $this->clapiRun('ADDINPUT', "{$name};input-{$type};{$type}");
        }
        foreach ($outputTypes as $type) {
            $this->clapiRun('ADDOUTPUT', "{$name};output-{$type};{$type}");
        }
        $this->clapiRun('LISTINPUT', $name);
        $this->clapiRun('LISTOUTPUT', $name);
        foreach (array_keys($inputTypes) as $id) {
            $this->clapiRun('GETINPUT', "{$name};{$id}");
        }
        foreach (array_keys($outputTypes) as $id) {
            $this->clapiRun('GETOUTPUT', "{$name};{$id}");
        }

        JsonSnapshot::assertMatches('clapi_add_defaults', $this->transcript);
    }

    public function testFlowIdsAreAllocatedPerTagFromTheHighestId(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9300-ids');
        $this->clapiRun('ADDOUTPUT', "{$name};output-a;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};output-b;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};output-c;ipv4");
        $this->clapiRun('ADDINPUT', "{$name};input-a;ipv4");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('LISTINPUT', $name);
        $this->clapiRun('DELOUTPUT', "{$name};1");
        $this->clapiRun('ADDOUTPUT', "{$name};output-d;ipv4");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('DELOUTPUT', "{$name};3");
        $this->clapiRun('ADDOUTPUT', "{$name};output-e;ipv4");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('DELOUTPUT', "{$name};42");
        $this->clapiRun('DELOUTPUT', "{$name};0");
        $this->clapiRun('DELOUTPUT', "{$name};2");
        $this->clapiRun('DELOUTPUT', "{$name};3");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('GETOUTPUT', "{$name};0");
        $this->clapiRun('ADDOUTPUT', "{$name};output-a;ipv4");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('DELINPUT', "{$name};0");
        $this->clapiRun('LISTINPUT', $name);
        $this->clapiRun('ADDINPUT', "{$name};input-b;ipv4");
        $this->clapiRun('LISTINPUT', $name);
        // Flows added through the API share the same id sequence.
        $this->flows->addThroughApi($this->configIds[0], 'output', 3, 'api-output', ['port' => 5670, 'host' => 'localhost']);
        $this->clapiRun('ADDOUTPUT', "{$name};output-f;ipv4");
        $this->clapiRun('LISTOUTPUT', $name);

        JsonSnapshot::assertMatches('clapi_id_allocation', $this->transcript);
    }

    public function testInvalidCommands(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9300-errors');
        $this->clapiRun('ADDOUTPUT', "{$name};output-a;ipv4");
        $this->clapiRun('ADDINPUT', "{$name};input-a;ipv4");

        $this->clapiRun('ADDOUTPUT', 'clapi-9300-unknown;output-x;ipv4');
        $this->clapiRun('LISTOUTPUT', 'clapi-9300-unknown');
        $this->clapiRun('LISTOUTPUT', '');
        $this->clapiRun('ADDOUTPUT', $name);
        $this->clapiRun('ADDOUTPUT', "{$name};output-x");
        $this->clapiRun('ADDOUTPUT', "{$name};output-x;unknown_type");
        $this->clapiRun('ADDINPUT', "{$name};input-x;rrd");
        $this->clapiRun('ADDOUTPUT', "{$name};output-a;ipv6");
        $this->clapiRun('ADDOUTPUT', "{$name};input-a;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};output-b,output-c;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};output-c;ipv4");
        $this->clapiRun('SETOUTPUT', "{$name};0;port");
        $this->clapiRun('SETOUTPUT', "{$name};42;port;5669");
        $this->clapiRun('SETOUTPUT', "{$name};0;unknown_field;value");
        $this->clapiRun('SETOUTPUT', "{$name};0;path;/var/lib/file");
        $this->clapiRun('SETOUTPUT', "{$name};0;tls;maybe");
        $this->clapiRun('SETOUTPUT', "{$name};0;category;neb,bam");
        $this->clapiRun('SETINPUT', "{$name};0;category;");
        $this->clapiRun('GETOUTPUT', $name);
        $this->clapiRun('GETOUTPUT', "{$name};42");
        $this->clapiRun('DELOUTPUT', "{$name};");
        $this->clapiRun('UNKNOWNOUTPUT', $name);
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('LISTINPUT', $name);
        $this->clapiRun('GETOUTPUT', "{$name};0");
        $this->clapiRun('GETOUTPUT', "{$name};1");

        JsonSnapshot::assertMatches('clapi_errors', $this->transcript);
    }

    public function testSetFlowParameters(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9300-set');
        $this->clapiRun('ADDINPUT', "{$name};input-ipv4;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};output-lua;lua");
        $this->clapiRun('ADDOUTPUT', "{$name};output-influxdb;influxdb");
        $this->clapiRun('ADDOUTPUT', "{$name};output-sql;unified_sql");

        // Scalar, select, empty value and multiselect fields.
        $this->clapiRun('SETINPUT', "{$name};0;port;5669");
        $this->clapiRun('SETINPUT', "{$name};0;host;");
        $this->clapiRun('SETINPUT', "{$name};0;tls;yes");
        $this->clapiRun('SETINPUT', "{$name};0;category;neb,storage");
        $this->clapiRun('GETINPUT', "{$name};0");
        $this->clapiRun('SETINPUT', "{$name};0;category;dumper");
        $this->clapiRun('SETINPUT', "{$name};0;port;5670");
        $this->clapiRun('GETINPUT', "{$name};0");

        // Comma lists on scalar fields.
        $this->clapiRun('SETOUTPUT', "{$name};0;path;/usr/share/centreon-broker/lua/a.lua,/usr/share/centreon-broker/lua/b.lua");
        $this->clapiRun('GETOUTPUT', "{$name};0");
        $this->clapiRun('SETOUTPUT', "{$name};0;path;/usr/share/centreon-broker/lua/connector.lua");

        // Grouped fields: stored keys are rejected, sub-field names collide with the flow name/type.
        $this->clapiRun('SETOUTPUT', "{$name};0;lua_parameter__name_0;param");
        $this->clapiRun('SETOUTPUT', "{$name};0;lua_parameter;param");
        $this->clapiRun('SETOUTPUT', "{$name};0;value;value-1");
        $this->clapiRun('SETOUTPUT', "{$name};0;type;password");
        $this->clapiRun('SETOUTPUT', "{$name};0;name;renamed-lua");
        $this->clapiRun('GETOUTPUT', "{$name};0");
        $this->clapiRun('SETOUTPUT', "{$name};1;metrics_column__name_0;host");
        $this->clapiRun('SETOUTPUT', "{$name};1;is_tag;true");
        $this->clapiRun('SETOUTPUT', "{$name};1;db_port;8086");
        $this->clapiRun('GETOUTPUT', "{$name};1");

        // Comma list on the flow name.
        $this->clapiRun('SETOUTPUT', "{$name};2;name;sql-a,sql-b");
        $this->clapiRun('SETOUTPUT', "{$name};2;db_host;127.0.0.1");
        $this->clapiRun('SETOUTPUT', "{$name};2;category;storage");
        $this->clapiRun('SETOUTPUT', "{$name};2;category;storage");
        $this->clapiRun('GETOUTPUT', "{$name};2");
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('LISTINPUT', $name);

        JsonSnapshot::assertMatches('clapi_set_parameters', $this->transcript);
    }

    public function testExportFlowsFromApiClapiAndLegacyWriters(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9300-export');
        $configId = $this->configIds[0];

        $this->flows->addThroughApi($configId, 'input', 35, 'api-bbdo-server', [
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
        $this->flows->addThroughApi($configId, 'output', 33, 'api-lua', [
            'path' => '/usr/share/centreon-broker/lua/connector.lua',
            'filters_category' => ['neb'],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'lua-param-1', 'value' => 'value-1'],
                ['type' => 'number', 'name' => 'lua-param-2', 'value' => '999'],
            ],
        ]);
        $this->flows->addThroughApi($configId, 'output', 31, 'api-influxdb', [
            'cache' => 'no',
            'db_host' => '127.0.0.1',
            'db_port' => 8086,
            'db_user' => 'influx-user',
            'db_password' => 'influx-secret',
            'db_name' => 'metrics',
            'metrics_timeseries' => '$METRIC$',
            'status_timeseries' => '$INSTANCE$',
            'metrics_column' => [['is_tag' => 'true', 'type' => 'string', 'value' => '$HOST$', 'name' => 'host']],
            'status_column' => [],
        ]);

        $this->clapiRun('ADDOUTPUT', "{$name};clapi-ipv4;ipv4");
        // The API numbers flows from 1 on an empty configuration, CLAPI continues after the highest id.
        $this->clapiRun('SETOUTPUT', "{$name};3;host;central.example.com");
        $this->clapiRun('SETOUTPUT', "{$name};3;port;5669");
        $this->clapiRun('SETOUTPUT', "{$name};3;category;neb,storage");
        $this->clapiRun('ADDINPUT', "{$name};clapi-input;ipv4");
        $this->clapiRun('SETINPUT', "{$name};2;port;5670");

        // centreon-map configure.sh: ipv4 output with categories.
        $this->flows->insertLegacyRows($configId, 'output', 10, [
            ['key' => 'name', 'value' => 'centreon-map'],
            ['key' => 'port', 'value' => '5758'],
            ['key' => 'host', 'value' => ''],
            ['key' => 'protocol', 'value' => 'bbdo'],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'blockId', 'value' => '1_3'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'bam', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        // centreon-bam: type 38 is unknown without the module.
        $this->flows->insertLegacyRows($configId, 'output', 11, BrokerFlowFixture::flatRows([
            'blockId' => '1_38',
            'name' => 'centreon-bam-monitoring',
            'type' => 'bam',
            'db_name' => 'centreon',
        ]));
        // Anomaly detection lua output with indexed lua parameters and a multi-line value.
        $this->flows->insertLegacyRows($configId, 'output', 12, [
            ['key' => 'name', 'value' => 'forward-to-anomaly-detection'],
            ['key' => 'path', 'value' => "/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua\nsecond-line"],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'lua_parameter__type', 'value' => 'number', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'max_queue_size', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '100', 'fieldIndex' => 0],
        ]);
        // Output without blockId: never exported.
        $this->flows->insertLegacyRows($configId, 'output', 13, BrokerFlowFixture::flatRows([
            'name' => 'no-block-id',
            'type' => 'ipv4',
            'port' => '5000',
        ]));
        // Pre-21.04 logger group.
        $this->flows->insertLegacyRows($configId, 'logger', 0, BrokerFlowFixture::flatRows([
            'name' => '/var/log/centreon-broker/pre-21-04.log',
            'config' => 'yes',
            'level' => 'low',
            'type' => 'file',
            'blockId' => '3_17',
        ]));

        $this->clapiRun('LISTINPUT', $name);
        $this->clapiRun('LISTOUTPUT', $name);
        $this->clapiRun('GETINPUT', "{$name};1");
        $this->clapiRun('GETINPUT', "{$name};2");
        $this->clapiRun('GETOUTPUT', "{$name};1");
        $this->clapiRun('GETOUTPUT', "{$name};3");
        $this->clapiRun('GETOUTPUT', "{$name};12");
        $this->clapiRun('EXPORT', $name);

        JsonSnapshot::assertMatches('clapi_export', $this->transcript);
    }

    public function testGeneratedConfigurationAfterClapiSequence(): void
    {
        $name = $this->createBrokerConfiguration('clapi-9301-central', self::GENERATION_POLLER_ID);

        $this->clapiRun('ADDINPUT', "{$name};central-input;ipv4");
        $this->clapiRun('SETINPUT', "{$name};0;port;5669");
        $this->clapiRun('SETINPUT', "{$name};0;category;neb");
        $this->clapiRun('ADDOUTPUT', "{$name};to-delete;ipv4");
        $this->clapiRun('ADDOUTPUT', "{$name};rrd-output;rrd");
        $this->clapiRun('SETOUTPUT', "{$name};1;port;5670");
        $this->clapiRun('ADDOUTPUT', "{$name};lua-output;lua");
        $this->clapiRun('SETOUTPUT', "{$name};2;path;/usr/share/centreon-broker/lua/connector.lua");
        $this->clapiRun('SETOUTPUT', "{$name};2;category;neb,storage");
        $this->clapiRun('SETOUTPUT', "{$name};2;category;neb,storage");
        $this->clapiRun('ADDOUTPUT', "{$name};sql-output;unified_sql");
        $this->clapiRun('SETOUTPUT', "{$name};3;db_host;127.0.0.1");
        $this->clapiRun('SETOUTPUT', "{$name};3;db_port;3306");
        $this->clapiRun('SETOUTPUT', "{$name};3;db_user;centreon");
        $this->clapiRun('SETOUTPUT', "{$name};3;db_password;db-secret");
        $this->clapiRun('SETOUTPUT', "{$name};3;db_name;centreon_storage");
        $this->clapiRun('SETOUTPUT', "{$name};3;category;storage");
        $this->clapiRun('DELOUTPUT', "{$name};0");

        $monitoringServers = $this->createStub(ReadMonitoringServerRepositoryInterface::class);
        $monitoringServers->method('isEncryptionReady')->willReturn(false);
        $generator = new LegacyBrokerConfigGenerator($this->database, $monitoringServers);
        $files = $this->normalizeGeneratedFiles($generator->generate(self::GENERATION_POLLER_ID, localhost: true));
        $files['php-warnings'] = $generator->warnings();

        JsonSnapshot::assertMatches('clapi_generated_broker', ['clapi' => $this->transcript, 'files' => $files]);
    }

    private function createBrokerConfiguration(string $name, int $pollerId = self::POLLER_ID): string
    {
        $this->configIds[] = $this->database->createBrokerConfiguration($pollerId, $name);

        return $name;
    }

    /**
     * Builds the CLAPI object as bin/centreon does, with every connection pointing to the test databases.
     */
    private function createClapi(): CentreonCentbrokerCfg
    {
        $this->originalIncludePath = (string) get_include_path();
        set_include_path(implode(PATH_SEPARATOR, [
            self::CENTREON_DIRECTORY . '/lib',
            self::CENTREON_DIRECTORY . '/www/class/centreon-clapi',
            $this->originalIncludePath,
        ]));
        require_once self::CENTREON_DIRECTORY . '/www/class/centreon-clapi/centreonAPI.class.php';
        require_once self::CENTREON_DIRECTORY . '/www/class/centreon-clapi/centreonCentbrokerCfg.class.php';

        // Legacy fallbacks: CentreonConfigCentreonBroker::getInfoDb() opens new CentreonDB('centstorage')
        // (the platform database) unless $pearDBO is set.
        $this->hadPearDbo = array_key_exists('pearDBO', $GLOBALS);
        $this->originalPearDbo = $GLOBALS['pearDBO'] ?? null;
        $GLOBALS['pearDBO'] = $this->database->realtime;

        $container = new Container();
        $container['configuration_db'] = $this->database->configuration;
        $container['realtime_db'] = $this->database->realtime;

        // CentreonObject uses the CentreonAPI singleton, which loads the platform dependency injector when absent.
        $apiInstance = new \ReflectionProperty(CentreonAPI::class, 'instance');
        $this->originalApiInstance = $apiInstance->getValue();
        $apiInstance->setValue(null, new CentreonAPI('', '', '', _CENTREON_PATH_, [], $container));
        $this->originalExportedInstance = (new \ReflectionProperty(CentreonExported::class, 'instance'))->getValue();

        $clapi = new CentreonCentbrokerCfg($container);
        $this->assertUsesTestDatabase($clapi);

        return $clapi;
    }

    /**
     * Fails if any connection reachable from the CLAPI object is not one of the test database connections.
     */
    private function assertUsesTestDatabase(CentreonCentbrokerCfg $clapi): void
    {
        $configuration = $this->database->configuration;
        $instance = $this->property($clapi, CentreonCentbrokerCfg::class, 'instanceObj');
        self::assertInstanceOf(CentreonInstance::class, $instance);
        $poller = $instance->centreonConfigPoller;
        $api = (new \ReflectionProperty(CentreonAPI::class, 'instance'))->getValue();
        self::assertInstanceOf(CentreonAPI::class, $api);

        $connections = [
            'CentreonCentbrokerCfg::$db' => $this->property($clapi, CentreonObject::class, 'db'),
            'CentreonCentbrokerCfg::$object' => $this->property(
                $this->property($clapi, CentreonObject::class, 'object'),
                \Centreon_Object::class,
                'db'
            ),
            'CentreonCentbrokerCfg::$brokerObj' => $this->property(
                $this->property($clapi, CentreonCentbrokerCfg::class, 'brokerObj'),
                \CentreonConfigCentreonBroker::class,
                'db'
            ),
            'CentreonInstance::$db' => $this->property($instance, CentreonObject::class, 'db'),
            'CentreonInstance::$object' => $this->property(
                $this->property($instance, CentreonObject::class, 'object'),
                \Centreon_Object::class,
                'db'
            ),
            'CentreonConfigPoller::$DB' => $this->property($poller, $poller::class, 'DB'),
            'CentreonAPI::$DB' => $this->property($api, CentreonAPI::class, 'DB'),
            'pearDBO' => $GLOBALS['pearDBO'],
        ];
        foreach ($connections as $label => $connection) {
            $expected = $label === 'pearDBO' ? $this->database->realtime : $configuration;
            self::assertSame($expected, $connection, $label . ' does not use the test database connection');
        }
        self::assertSame($this->database->realtime, $this->property($poller, $poller::class, 'DBC'));
        self::assertSame($this->database->realtime, $this->property($api, CentreonAPI::class, 'DBC'));
    }

    private function property(mixed $object, string $class, string $name): mixed
    {
        self::assertIsObject($object);

        return (new \ReflectionProperty($class, $name))->getValue($object);
    }

    /**
     * Runs one CLAPI action as "centreon -o CENTBROKERCFG -a <action> -v <arguments>" and records its output.
     */
    private function clapiRun(string $action, string $arguments): void
    {
        $entry = ['command' => 'CENTBROKERCFG;' . $action . ';' . $arguments, 'output' => []];
        ob_start();
        try {
            if ($action === 'EXPORT') {
                // As "centreon -e --select=CENTBROKERCFG;<name>".
                $exported = new \ReflectionProperty(CentreonExported::class, 'instance');
                $exported->setValue(null, null);
                CentreonExported::getInstance()->setFilter(1);
            }
            // Same dispatch as CentreonAPI: the lower-cased action is called with the raw arguments.
            $this->clapi->{mb_strtolower($action)}($arguments);
        } catch (\Throwable $exception) {
            $entry['error'] = $exception::class . ': ' . $exception->getMessage();
        } finally {
            $output = (string) ob_get_clean();
        }
        $lines = $output === '' ? [] : explode("\n", rtrim($output, "\n"));
        $entry['output'] = match (true) {
            str_starts_with($action, 'LIST'), str_starts_with($action, 'GET') => $this->sortRunsSharingFirstField($lines),
            $action === 'EXPORT' => $this->normalizeExport($lines),
            default => $lines,
        };
        $this->transcript[] = $entry;
    }

    /**
     * LIST is ordered by flow id and GET by parameter key only: lines sharing that first field come in undefined order.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function sortRunsSharingFirstField(array $lines): array
    {
        $runs = [];
        foreach ($lines as $line) {
            $first = explode(';', $line, 2)[0];
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['key'] === $first) {
                $runs[$last]['lines'][] = $line;
            } else {
                $runs[] = ['key' => $first, 'lines' => [$line]];
            }
        }
        $sorted = [];
        foreach ($runs as $run) {
            sort($run['lines']);
            array_push($sorted, ...$run['lines']);
        }

        return $sorted;
    }

    /**
     * Keeps the broker lines in order, then the flows grouped by tag in their printed order (flows of different
     * tags sharing an id come in undefined order), each flow's SET lines and category values being sorted.
     *
     * @param list<string> $lines
     *
     * @return array{broker: list<string>, flows: array<string, array<int, list<string>>>}
     */
    private function normalizeExport(array $lines): array
    {
        $broker = [];
        $flows = [];
        $current = null;
        foreach ($lines as $line) {
            $fields = explode(';', $line);
            $action = $fields[1] ?? '';
            if (preg_match('/^ADD(INPUT|OUTPUT|LOGGER)$/', $action, $matches) === 1) {
                $flows[$matches[1]][] = [$line];
                $current = $matches[1];
            } elseif (preg_match('/^SET(INPUT|OUTPUT|LOGGER)$/', $action, $matches) === 1) {
                if (($fields[4] ?? '') === 'category') {
                    $values = explode(',', $fields[5] ?? '');
                    sort($values);
                    $fields[5] = implode(',', $values);
                    $line = implode(';', $fields);
                }
                if ($current !== $matches[1]) {
                    $flows[$matches[1]][] = [];
                    $current = $matches[1];
                }
                $last = array_key_last($flows[$matches[1]]);
                $flows[$matches[1]][$last][] = $line;
            } else {
                $broker[] = $line;
                $current = null;
            }
        }
        foreach ($flows as $tag => $tagFlows) {
            foreach ($tagFlows as $index => $flowLines) {
                $add = str_contains($flowLines[0] ?? '', ';ADD') ? array_shift($flowLines) : null;
                sort($flowLines);
                $flows[$tag][$index] = $add === null ? $flowLines : [$add, ...$flowLines];
            }
        }
        ksort($flows);

        return ['broker' => $broker, 'flows' => $flows];
    }

    /**
     * Replaces the values depending on auto-increment ids and ignores the order of the lists inside flows.
     *
     * @param array<string, array<mixed>> $files
     *
     * @return array<string, array<mixed>>
     */
    private function normalizeGeneratedFiles(array $files): array
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
                if (! is_array($broker[$group] ?? null)) {
                    continue;
                }
                foreach ($broker[$group] as $flowIndex => $flow) {
                    $broker[$group][$flowIndex] = is_array($flow) ? self::sortListsRecursively($flow) : $flow;
                }
            }
            $index = $placeholders[$broker['broker_id']] ?? 'unknown';
            $broker['broker_id'] = '<broker-' . $index . '>';
            $broker['grpc'] = ['port' => '<51000 + broker-' . $index . '>'];
            $files[$name]['centreonBroker'] = $broker;
        }

        return $files;
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
