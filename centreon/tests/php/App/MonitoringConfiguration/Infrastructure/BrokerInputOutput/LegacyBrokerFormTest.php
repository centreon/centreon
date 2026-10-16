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

use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Infrastructure\Repository\DbReadBrokerInputOutputRepository;
use Core\Broker\Infrastructure\Repository\DbWriteBrokerInputOutputRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the legacy broker configuration form (www/class/centreonConfigCentreonBroker.php) and of
 * the data read by the legacy broker duplication (www/include/configuration/configCentreonBroker/DB-Func.php).
 *
 * Pinned on the read side only, independently of the storage of the broker inputs/outputs:
 * - getForms(): per flow, the form elements (name, type, value, frozen) and the hidden values, the lua parameter
 *   clone values pushed to the javascript (CentreonData) and the public arrayMultiple, never the HTML;
 * - getHelps(): help entries per flow;
 * - the restoration of masked passwords done before re-posting the form (revealPasswords / revealLuaPasswords,
 *   i.e. findOriginalValueWithGroupId / findOriginalValueWithFieldIndex);
 * - the backup/restore of the flows of a configuration used to roll back a failed form submission;
 * - getCfgBrokerInfoData(), the rows consumed by the duplication.
 * getConfigFieldName() is covered through the element names produced by getForms().
 *
 * Rows of a flow are read without a defined order (the table has no primary key), so lists built from them are
 * compared order-insensitively. The lua password masking of getForms() depends on that order (a "type" row equal
 * to "password" masks the next "value" row read), so lua values of password-bearing configurations are not pinned
 * by the snapshots; the order-independent part of the behavior is asserted explicitly instead.
 *
 * Every legacy code path runs on the test database: the form object receives the test connection, the globals
 * $pearDB / $pearDBO read by the legacy code are set to the test connections, and the legacy connection constants
 * (used by `new CentreonDB()` defaults) must be undefined or point to the test database.
 */
#[Group('broker_io_characterization')]
final class LegacyBrokerFormTest extends KernelTestCase
{
    private const POLLER_ID = 9400;
    private const OTHER_POLLER_ID = 9401;
    private const FORM_PAGE = 60909;
    private const MASK = \CentreonAuth::PWS_OCCULTATION;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    /** @var array<string, mixed> globals overridden by the test, restored on tearDown */
    private array $originalGlobals = [];

    /** @var array<string, mixed>|null */
    private ?array $originalSession = null;

    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        $this->assertLegacyDefaultsCannotReachThePlatformDatabase();

        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::POLLER_ID, localhost: true);
        $this->database->createPoller(self::OTHER_POLLER_ID, localhost: false);

        foreach (['pearDB', 'pearDBO'] as $name) {
            $this->originalGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $GLOBALS['pearDB'] = $this->database->configuration;
        $GLOBALS['pearDBO'] = $this->database->realtime;
        /** @var array<string, mixed>|null $session */
        $session = $_SESSION ?? null;
        $this->originalSession = $session;
        $this->resetJavascriptData();
    }

    protected function tearDown(): void
    {
        foreach ($this->originalGlobals as $name => $value) {
            if ($value === null) {
                unset($GLOBALS[$name]);
            } else {
                $GLOBALS[$name] = $value;
            }
        }
        if ($this->originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->originalSession;
        }
        $this->resetJavascriptData();
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testFormOfFlowsAddedThroughTheApi(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-api');
        $this->addApiFlows($configId, luaPassword: null);

        $this->assertForm('form_api_flows', $configId);
    }

    public function testFormOfFlowsStoredByWritersBypassingTheApi(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-legacy');
        // www/install/var/baseconf/centreon-broker.sql
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
        $this->flows->insertLegacyRows($configId, 'output', 1, [
            ['key' => 'name', 'value' => 'central-broker-master-event-script'],
            ['key' => 'script_path', 'value' => '/usr/share/centreon/bin/console agent-configuration:host:create'],
            ['key' => 'timeout', 'value' => '15'],
            ['key' => 'managed_event_ttl', 'value' => '3600'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'event', 'value' => 'neb:UnknownHost', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'type', 'value' => 'event_script'],
            ['key' => 'blockId', 'value' => '1_37'],
        ]);
        // CLAPI (centreonCentbrokerCfg): multiselect values with the filters hierarchy, lua parameter without fieldIndex.
        $this->flows->insertLegacyRows($configId, 'output', 2, [
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'name', 'value' => 'clapi-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/clapi.lua'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => 'storage', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'lua_parameter__name', 'value' => 'clapi-param'],
            ['key' => 'blockId', 'value' => '1_33'],
        ]);
        // Module writer (centreon-anomaly-detection addOutputLua): lua parameters with fieldIndex, no password.
        $this->flows->insertLegacyRows($configId, 'output', 3, [
            ['key' => 'name', 'value' => 'anomaly-detection'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua'],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'lua_parameter__type', 'value' => 'number', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'max_queue_size', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '100', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__type', 'value' => 'string', 'fieldIndex' => 1],
            ['key' => 'lua_parameter__name', 'value' => 'saas_url', 'fieldIndex' => 1],
            ['key' => 'lua_parameter__value', 'value' => 'https://saas.example.com', 'fieldIndex' => 1],
        ]);

        $this->assertForm('form_legacy_flows', $configId);
    }

    public function testFormOfAnEmptyConfiguration(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-empty');
        $broker = $this->createBrokerForm();

        foreach ($this->tags($broker) as $tag) {
            self::assertSame([], $broker->getForms($configId, $tag, self::FORM_PAGE, new \SmartyBC()));
            self::assertSame([], $broker->getHelps($configId, $tag));
        }
        self::assertSame([], $broker->arrayMultiple);
    }

    /**
     * The value of a lua parameter of type password is masked in the form only when the "type" row of the
     * parameter is read before its "value" row, and the mask then applies to the next "value" row read, whatever
     * its parameter. Only the order-independent consequences are pinned here.
     */
    public function testLuaPasswordsInTheForm(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-lua-password');
        $this->flows->addThroughApi($configId, 'output', 33, 'lua-with-secrets', [
            'path' => '/usr/share/centreon-broker/lua/secrets.lua',
            'filters_category' => [],
            'lua_parameter' => [
                ['type' => 'password', 'name' => 'api-key', 'value' => 'lua-secret'],
            ],
        ]);

        $broker = $this->createBrokerForm();
        $this->captureWarnings(fn () => $broker->getForms($configId, 'output', self::FORM_PAGE, new \SmartyBC()));

        $values = $broker->arrayMultiple['lua_parameter___1'] ?? null;
        self::assertIsArray($values, 'lua parameters missing from the form');
        self::assertCount(1, $values);
        $parameter = $values[0] ?? null;
        self::assertIsArray($parameter);
        self::assertSame('api-key', $parameter['output[1][lua_parameter__name_#index#]'] ?? null);
        self::assertSame('password', $parameter['output[1][lua_parameter__type_#index#]'] ?? null);
        self::assertContains(
            $parameter['output[1][lua_parameter__value_#index#]'] ?? null,
            [self::MASK, 'lua-secret'],
            'the lua password is either masked or shown in clear depending on the row order',
        );
        self::assertSame([], $this->warnings);
    }

    public function testMaskedPasswordsAreRestoredFromTheStoredValues(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-reveal');
        $this->addApiFlows($configId, luaPassword: 'lua-secret');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'form-reveal-other');
        $this->addApiFlows($otherConfigId, luaPassword: 'other-lua-secret', secretPrefix: 'other-');

        // POSTed values as built by the form: output 1 = unified_sql, 2 = lua, 3 = influxdb (see addApiFlows()).
        $values = [
            'input' => [
                1 => ['name' => 'bbdo-server-input', 'authorization' => self::MASK],
            ],
            'output' => [
                1 => ['name' => 'unified-sql', 'db_password' => self::MASK],
                2 => [
                    'name' => 'stream-connector',
                    'lua_parameter__type_0' => 'string',
                    'lua_parameter__name_0' => 'lua-param-1',
                    'lua_parameter__value_0' => self::MASK,
                    'lua_parameter__type_1' => 'password',
                    'lua_parameter__name_1' => 'lua-param-2',
                    'lua_parameter__value_1' => self::MASK,
                    'lua_parameter__type_2' => 'password',
                    'lua_parameter__name_2' => 'lua-param-new',
                    'lua_parameter__value_2' => self::MASK,
                    'lua_parameter__type_5' => 'password',
                    'lua_parameter__name_5' => 'lua-param-unchanged',
                    'lua_parameter__value_5' => 'typed-by-user',
                ],
                3 => ['name' => 'influxdb', 'db_password' => 'new-influx-secret'],
                4 => ['name' => 'unknown-output', 'db_password' => self::MASK],
            ],
        ];

        $broker = $this->createBrokerForm();
        $this->captureWarnings(function () use ($broker, $configId, &$values): void {
            $this->revealPasswords($broker, 'revealLuaPasswords', $configId, $values);
            $this->revealPasswords($broker, 'revealPasswords', $configId, $values);
        });

        JsonSnapshot::assertMatches('form_revealed_passwords', ['values' => $values, 'php-warnings' => $this->warnings]);

        self::assertSame(
            'db-secret',
            $this->callPrivate($broker, 'findOriginalValueWithGroupId', $configId, 1, 'db_password'),
        );
        self::assertSame(
            'other-db-secret',
            $this->callPrivate($broker, 'findOriginalValueWithGroupId', $otherConfigId, 1, 'db_password'),
        );
        self::assertNull($this->callPrivate($broker, 'findOriginalValueWithGroupId', $configId, 99, 'db_password'));
        self::assertSame(
            'lua-secret',
            $this->callPrivate($broker, 'findOriginalValueWithFieldIndex', $configId, 'lua_parameter__value', 1, 2),
        );
        self::assertSame(
            'other-lua-secret',
            $this->callPrivate($broker, 'findOriginalValueWithFieldIndex', $otherConfigId, 'lua_parameter__value', 1, 2),
        );
        self::assertNull(
            $this->callPrivate($broker, 'findOriginalValueWithFieldIndex', $configId, 'lua_parameter__value', 9, 2),
        );
    }

    public function testMaskedLuaPasswordOfALegacyParameterWithoutFieldIndexIsNotFound(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-reveal-legacy');
        $this->flows->insertLegacyRows($configId, 'output', 0, [
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'name', 'value' => 'clapi-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/clapi.lua'],
            ['key' => 'lua_parameter__type', 'value' => 'password'],
            ['key' => 'lua_parameter__name', 'value' => 'clapi-param'],
            ['key' => 'lua_parameter__value', 'value' => 'clapi-secret'],
            ['key' => 'blockId', 'value' => '1_33'],
        ]);
        $values = [
            'output' => [
                0 => [
                    'name' => 'clapi-lua',
                    'lua_parameter__type_0' => 'password',
                    'lua_parameter__name_0' => 'clapi-param',
                    'lua_parameter__value_0' => self::MASK,
                ],
            ],
        ];

        $this->revealPasswords($this->createBrokerForm(), 'revealLuaPasswords', $configId, $values);

        /** @var array{output: array<int, array<string, string|null>>} $values */
        self::assertArrayHasKey('lua_parameter__value_0', $values['output'][0]);
        self::assertNull($values['output'][0]['lua_parameter__value_0']);
    }

    public function testBackupAndRestoreOfTheFlowsOfAConfiguration(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-backup');
        $this->addApiFlows($configId, luaPassword: 'lua-secret');
        $this->flows->insertLegacyRows($configId, 'output', 10, [
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'name', 'value' => 'clapi-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/clapi.lua'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'lua_parameter__name', 'value' => 'clapi-param'],
            ['key' => 'blockId', 'value' => '1_33'],
        ]);
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'form-backup-other');
        $this->addApiFlows($otherConfigId, luaPassword: 'other-lua-secret', secretPrefix: 'other-');

        $flowsBefore = $this->readFlows($configId);
        $otherFlowsBefore = $this->readFlows($otherConfigId);
        $formBefore = $this->extractFormsWithoutLuaParameters($configId);
        self::assertCount(6, $flowsBefore);

        $broker = $this->createBrokerForm();
        $backup = $this->callPrivate($broker, 'backupBrokerInfos', $configId);
        self::assertIsArray($backup);

        $writeRepository = new DbWriteBrokerInputOutputRepository($this->database->connection);
        foreach ($flowsBefore as $flow) {
            $writeRepository->delete($configId, $flow['tag'], $flow['id']);
        }
        self::assertSame([], $this->readFlows($configId));

        $this->callPrivate($broker, 'restoreBrokerInfos', $backup);

        self::assertSame($flowsBefore, $this->readFlows($configId));
        self::assertSame($otherFlowsBefore, $this->readFlows($otherConfigId));
        self::assertSame($formBefore, $this->extractFormsWithoutLuaParameters($configId));

        $this->callPrivate($broker, 'restoreBrokerInfos', []);
        self::assertSame($flowsBefore, $this->readFlows($configId));
    }

    public function testDuplicationReadsTheFlowsOfAConfiguration(): void
    {
        $this->loadBrokerFunctions();
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'form-duplication-api');
        $this->addApiFlows($configId, luaPassword: 'lua-secret');
        $legacyConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'form-duplication-legacy');
        $this->flows->insertLegacyRows($legacyConfigId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'legacy-input',
            'port' => '5669',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($legacyConfigId, 'output', 0, [
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'name', 'value' => 'clapi-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/clapi.lua'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => 'storage', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'lua_parameter__name', 'value' => 'clapi-param'],
            ['key' => 'blockId', 'value' => '1_33'],
        ]);
        $this->flows->insertLegacyRows($legacyConfigId, 'output', 1, [
            ['key' => 'name', 'value' => 'module-lua'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/module.lua'],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'lua_parameter__type', 'value' => 'number', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'max_queue_size', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '100', 'fieldIndex' => 0],
        ]);

        JsonSnapshot::assertMatches('form_duplication_rows', [
            'api' => $this->duplicatedRows($configId),
            'legacy' => $this->duplicatedRows($legacyConfigId),
            'unknown' => $this->cfgBrokerInfoData(999999),
        ]);
    }

    private function callLegacyFunction(string $function, mixed ...$arguments): mixed
    {
        self::assertTrue(function_exists($function), sprintf('Legacy function %s() is not loaded', $function));

        return $function(...$arguments);
    }

    /**
     * Rows returned by getCfgBrokerInfoData(), without the "filters" rows that multipleCentreonBrokerInDB() skips.
     *
     * @return list<mixed>
     */
    private function duplicatedRows(int $configId): array
    {
        return self::sortRows(array_filter(
            $this->cfgBrokerInfoData($configId),
            static fn (mixed $row): bool => ! is_array($row) || $row['config_key'] !== 'filters',
        ));
    }

    /**
     * @return array<mixed>
     */
    private function cfgBrokerInfoData(int $configId): array
    {
        // Called by name: the function is declared by the legacy file loaded at runtime.
        $rows = $this->callLegacyFunction('getCfgBrokerInfoData', $configId);
        self::assertIsArray($rows);

        return $rows;
    }

    private function assertLegacyDefaultsCannotReachThePlatformDatabase(): void
    {
        // `new CentreonDB()` without a ConnectionConfig reads these constants: when undefined it fails with an Error.
        foreach (['db' => 'db', 'dbcstg' => 'dbcstg', 'hostCentreon' => 'hostCentreon'] as $constant => $env) {
            if (defined($constant)) {
                self::assertSame(
                    $_ENV[$env] ?? null,
                    constant($constant),
                    sprintf('The legacy constant "%s" must point to the test database', $constant),
                );
            }
        }
    }

    /**
     * Inputs 1 (bbdo_server) and 2 (ipv4); outputs 1 (unified_sql), 2 (lua), 3 (influxdb).
     *
     * @param string|null $luaPassword null to add no lua parameter of type password
     */
    private function addApiFlows(int $configId, ?string $luaPassword, string $secretPrefix = ''): void
    {
        $this->flows->addThroughApi($configId, 'input', 35, 'bbdo-server-input', [
            'host' => null,
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => $secretPrefix . 'grpc-token',
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
        $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql', [
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_user' => 'centreon',
            'db_password' => $secretPrefix . 'db-secret',
            'db_name' => 'centreon_storage',
            'store_in_data_bin' => 'yes',
            'filters_category' => [],
        ]);
        $luaParameters = [
            ['type' => 'string', 'name' => 'lua-param-1', 'value' => 'value-1'],
        ];
        if ($luaPassword !== null) {
            $luaParameters[] = ['type' => 'password', 'name' => 'lua-param-2', 'value' => $luaPassword];
        }
        $luaParameters[] = ['type' => 'number', 'name' => 'lua-param-3', 'value' => '999'];
        $this->flows->addThroughApi($configId, 'output', 33, 'stream-connector', [
            'path' => '/usr/share/centreon-broker/lua/connector.lua',
            'filters_category' => ['dumper', 'storage'],
            'lua_parameter' => $luaParameters,
        ]);
        $this->flows->addThroughApi($configId, 'output', 31, 'influxdb', [
            'cache' => 'no',
            'db_host' => '127.0.0.1',
            'db_port' => 8086,
            'db_user' => 'influx-user',
            'db_password' => $secretPrefix . 'influx-secret',
            'db_name' => 'metrics',
            'metrics_timeseries' => '$METRIC$',
            'status_timeseries' => '$INSTANCE$',
            'metrics_column' => [
                ['name' => 'value', 'value' => '$VALUE$', 'is_tag' => 'false', 'type' => 'number'],
            ],
            'status_column' => [],
            'filters_category' => [],
        ]);
    }

    private function assertForm(string $snapshot, int $configId): void
    {
        JsonSnapshot::assertMatches($snapshot, $this->extractForms($configId));
    }

    /**
     * Reproduces formCentreonBroker.php: one form object, getForms() then getHelps() for each tag.
     *
     * @return array<string, mixed>
     */
    private function extractForms(int $configId): array
    {
        $this->resetJavascriptData();
        $broker = $this->createBrokerForm();
        $result = [];
        $this->warnings = [];
        foreach ($this->tags($broker) as $tag) {
            /** @var mixed $forms */
            $forms = $this->captureWarnings(
                fn () => $broker->getForms($configId, $tag, self::FORM_PAGE, new \SmartyBC())
            );
            self::assertIsArray($forms);
            /** @var mixed $helps */
            $helps = $this->captureWarnings(fn () => $broker->getHelps($configId, $tag));
            self::assertIsArray($helps);
            $result[$tag] = [
                'forms' => array_map(
                    fn (mixed $form): array => $this->extractForm(is_array($form) ? $form : []),
                    $forms,
                ),
                'helps' => $helps,
            ];
        }
        // Keyed by fieldIndex, so their order is significant and does not depend on the row order.
        $result['arrayMultiple'] = self::sortKeys($broker->arrayMultiple);
        $result['javascript'] = $this->javascriptData();
        $result['php-warnings'] = array_values(array_unique($this->warnings));
        sort($result['php-warnings']);

        return $result;
    }

    /**
     * The lua parameter values of a configuration holding lua passwords depend on the row order (see
     * testLuaPasswordsInTheForm()), so they are left out.
     *
     * @return array<string, mixed>
     */
    private function extractFormsWithoutLuaParameters(int $configId): array
    {
        $forms = $this->extractForms($configId);
        unset($forms['arrayMultiple'], $forms['javascript']);

        return $forms;
    }

    /**
     * @param array<mixed> $form a form rendered by HTML_QuickForm_Renderer_ArraySmarty
     *
     * @return array{elements: array<string, mixed>, hidden: array<string, string>}
     */
    private function extractForm(array $form): array
    {
        $elements = [];
        $this->collectElements($form, $elements);
        ksort($elements);

        $hidden = [];
        $hiddenHtml = $form['hidden'] ?? '';
        preg_match_all('/<input[^>]*>/', is_string($hiddenHtml) ? $hiddenHtml : '', $inputs);
        foreach ($inputs[0] as $input) {
            preg_match('/name="([^"]*)"/', $input, $name);
            preg_match('/value="([^"]*)"/', $input, $value);
            if (! isset($name[1])) {
                continue;
            }
            if ($name[1] === 'centreon_token') {
                continue;
            }
            $hidden[html_entity_decode($name[1])] = html_entity_decode($value[1] ?? '');
        }
        ksort($hidden);

        return ['elements' => $elements, 'hidden' => $hidden];
    }

    /**
     * @param array<mixed> $node
     * @param array<string, mixed> $elements
     */
    private function collectElements(array $node, array &$elements): void
    {
        if (isset($node['name'], $node['type']) && is_string($node['name']) && array_key_exists('frozen', $node)) {
            $elements[$node['name']] = [
                'type' => $node['type'],
                'value' => is_array($node['value']) ? self::sortLists($node['value']) : $node['value'],
                'frozen' => $node['frozen'],
            ];

            return;
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectElements($child, $elements);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function javascriptData(): array
    {
        $data = [];
        /** @var array<string, mixed> $jsData */
        $jsData = \CentreonData::getInstance()->getJsData();
        foreach ($jsData as $key => $value) {
            $decoded = is_string($value) ? json_decode(html_entity_decode($value, ENT_QUOTES), true) : null;
            $data[$key] = is_array($decoded) ? self::sortKeys($decoded) : $value;
        }
        ksort($data);

        return $data;
    }

    private function resetJavascriptData(): void
    {
        (new \ReflectionProperty(\CentreonData::class, 'instance'))->setValue(null, null);
    }

    private function createBrokerForm(): \CentreonConfigCentreonBroker
    {
        $broker = new \CentreonConfigCentreonBroker($this->database->configuration);
        self::assertSame(
            $this->database->configuration,
            (new \ReflectionProperty(\CentreonConfigCentreonBroker::class, 'db'))->getValue($broker),
        );

        return $broker;
    }

    /**
     * @return list<string>
     */
    private function tags(\CentreonConfigCentreonBroker $broker): array
    {
        $tags = array_values($broker->getTags());
        self::assertSame(['input', 'output'], $tags);

        return $tags;
    }

    private function callPrivate(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($object, $method))->invokeArgs($object, $arguments);
    }

    /**
     * Calls revealPasswords() or revealLuaPasswords(), which modify the POSTed values in place.
     *
     * @param array<string, mixed> $values
     */
    private function revealPasswords(\CentreonConfigCentreonBroker $broker, string $method, int $configId, array &$values): void
    {
        (new \ReflectionMethod($broker, $method))->invokeArgs($broker, [$configId, &$values]);
    }

    /**
     * @template T
     *
     * @param callable(): T $callable
     *
     * @return T
     */
    private function captureWarnings(callable $callable): mixed
    {
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            $this->warnings[] = sprintf('%s:%d %s', basename($file), $line, $message);

            return true;
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED);

        try {
            return $callable();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Flows of a configuration as read by the Core repository (the broker API), passwords included.
     *
     * @return list<array{tag: string, id: int, type: int, name: string, parameters: array<mixed>}>
     */
    private function readFlows(int $configId): array
    {
        $flows = [];
        $all = (new DbReadBrokerInputOutputRepository($this->database->connection))->findAll();
        foreach ($all[$configId] ?? [] as $flow) {
            /** @var BrokerInputOutput $flow */
            $flows[] = [
                'tag' => $flow->getTag(),
                'id' => $flow->getId(),
                'type' => $flow->getType()->id,
                'name' => $flow->getName(),
                'parameters' => self::sortLists($flow->getParameters()),
            ];
        }
        usort($flows, static fn (array $left, array $right): int => [$left['tag'], $left['id']] <=> [$right['tag'], $right['id']]);

        return $flows;
    }

    private function loadBrokerFunctions(): void
    {
        if (function_exists('getCfgBrokerInfoData')) {
            return;
        }
        if (function_exists('testExistence')) {
            self::fail('Another legacy DB-Func.php is already loaded, configCentreonBroker/DB-Func.php cannot be included');
        }
        require_once dirname(__DIR__, 6) . '/www/include/configuration/configCentreonBroker/DB-Func.php';
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<mixed>
     */
    private static function sortRows(array $rows): array
    {
        $rows = array_values($rows);
        usort($rows, static fn (mixed $left, mixed $right): int => strcmp(
            json_encode(self::sortKeys($left), JSON_THROW_ON_ERROR),
            json_encode(self::sortKeys($right), JSON_THROW_ON_ERROR),
        ));

        return $rows;
    }

    /**
     * Sorts lists recursively (their order comes from the undefined order of the rows) and the keys of maps.
     *
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function sortLists(array $value): array
    {
        $value = array_map(
            static fn (mixed $item): mixed => is_array($item) ? self::sortLists($item) : $item,
            $value,
        );
        if (array_is_list($value)) {
            return self::sortRows($value);
        }
        ksort($value);

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
