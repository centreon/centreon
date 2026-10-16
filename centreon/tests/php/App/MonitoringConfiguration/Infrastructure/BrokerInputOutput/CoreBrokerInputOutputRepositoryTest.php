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
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\Type;
use Core\Broker\Infrastructure\Repository\DbReadBrokerInputOutputRepository;
use Core\Broker\Infrastructure\Repository\DbWriteBrokerInputOutputRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the Core broker input/output repositories (used by the broker API, the legacy form and the
 * vault credential migration), observed through their public methods only.
 *
 * Every scenario reproduces data as one writer stores it (repository add/update, CLAPI, base configuration,
 * App poller creation, remote wizard, modules) and pins what the read methods return. These expectations must
 * stay unchanged when the storage of broker inputs/outputs changes.
 *
 * Rows of a flow are read without a defined order (the table has no primary key), so the order of lists inside
 * a flow (filters, grouped fields) is not significant and is ignored by the comparisons.
 *
 * Pinned behaviors which look like quirks of the current implementation:
 * - every parameter value is read back as a string, null/missing scalar values are stored as their list default
 *   or as an empty string, missing multiselect/grouped values are not stored at all;
 * - findByIdAndBrokerId() re-indexes grouped fields and nulls the "value" of grouped "password" entries, while
 *   findAll() keeps the stored fieldIndex keys and the clear values; scalar password fields are never masked;
 * - every row having a parent_grp_id is gathered under "<last multiselect parent key>_<child key>", whatever its
 *   parent id (CLAPI duplicated parent rows and children linked to the field group id included);
 * - rows with a grp_level but no hierarchy columns (remote wizard) and grouped keys without fieldIndex (CLAPI)
 *   are read as plain keys;
 * - the type id is read from the blockId by stripping every "1_"/"2_" occurrence, whatever the tag;
 * - a flow without name, type or blockId row is invisible (null / skipped);
 * - findVaultPathByBrokerId() returns any value starting with "secret::" in the configuration, whatever its key.
 */
#[Group('broker_io_characterization')]
final class CoreBrokerInputOutputRepositoryTest extends KernelTestCase
{
    private const POLLER_ID = 9200;
    private const OTHER_POLLER_ID = 9201;
    private const VAULT_PATH = 'secret::hashicorp_vault::monitoring/data/configuration/broker/5a0e8f43-2b8f-4c2e-9d0a-1f2b3c4d5e6f';

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private DbReadBrokerInputOutputRepository $readRepository;

    private DbWriteBrokerInputOutputRepository $writeRepository;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->readRepository = new DbReadBrokerInputOutputRepository($this->database->connection);
        $this->writeRepository = new DbWriteBrokerInputOutputRepository($this->database->connection);
        $this->database->createPoller(self::POLLER_ID, localhost: true);
        $this->database->createPoller(self::OTHER_POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testAddAllocatesIdsPerConfigurationAndTag(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-ids');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-ids-other');

        self::assertSame(1, $this->addFileFlow($configId, 'output', 'output-a'));
        self::assertSame(2, $this->addFileFlow($configId, 'output', 'output-b'));
        self::assertSame(1, $this->addFileFlow($configId, 'input', 'input-a'), 'inputs and outputs are numbered apart');
        self::assertSame(1, $this->addFileFlow($otherConfigId, 'output', 'other-output-a'), 'configurations are numbered apart');
        self::assertSame(3, $this->addFileFlow($configId, 'output', 'output-c'));

        // Next id is MAX + 1: a deleted id in the middle is not reused, the deleted highest id is.
        $this->writeRepository->delete($configId, 'output', 2);
        self::assertSame(4, $this->addFileFlow($configId, 'output', 'output-d'));
        $this->writeRepository->delete($configId, 'output', 4);
        self::assertSame(4, $this->addFileFlow($configId, 'output', 'output-e'));

        self::assertSame(2, $this->addFileFlow($configId, 'input', 'input-b'));
    }

    public function testAddAfterFlowsStoredByLegacyWriters(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-ids-legacy');
        // Base configuration / CLAPI / poller creation number their first flow 0.
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'legacy-input',
            'port' => '5669',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 5, BrokerFlowFixture::flatRows([
            'name' => 'legacy-output',
            'path' => '/tmp/out',
            'type' => 'file',
            'blockId' => '1_11',
        ]));

        self::assertSame(1, $this->addFileFlow($configId, 'input', 'api-input'));
        self::assertSame(6, $this->addFileFlow($configId, 'output', 'api-output'));
        self::assertSame('legacy-input', $this->readRepository->findByIdAndBrokerId('input', 0, $configId)?->getName());
    }

    public function testFlowsAddedThroughTheApiAreReadBack(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-api');
        $ids = ['input' => [], 'output' => []];
        $ids['input'][] = $this->flows->addThroughApi($configId, 'input', 35, '  bbdo-server-input  ', [
            'host' => null,
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => 'grpc-secret',
            'encryption' => 'no',
            'private_key' => null,
            'certificate' => '',
            'compression' => 'no',
            'retention' => 'no',
            'filters_category' => ['storage', 'neb'],
        ]);
        $ids['input'][] = $this->flows->addThroughApi($configId, 'input', 3, 'ipv4-input', [
            'port' => 5670,
            'host' => 'localhost',
            'protocol' => 'bbdo',
            'filters_category' => [],
        ]);
        $ids['output'][] = $this->flows->addThroughApi($configId, 'output', 33, 'lua-output', [
            'path' => '/usr/share/centreon-broker/lua/connector.lua',
            'filters_category' => ['dumper', '', 'storage'],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'lua-param-1', 'value' => 'value-1'],
                ['type' => 'number', 'name' => 'lua-param-2', 'value' => '999'],
                ['type' => 'password', 'name' => 'lua-param-3', 'value' => 'lua-secret'],
                ['type' => 'string', 'name' => '', 'value' => ''],
                ['name' => 'name-only'],
                ['type' => 'password', 'name' => 'empty-password', 'value' => ''],
            ],
        ]);
        $ids['output'][] = $this->flows->addThroughApi($configId, 'output', 31, 'influxdb-output', [
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
                ['value' => '$VALUE$', 'name' => 'value'],
            ],
            'status_column' => [],
        ]);
        $ids['output'][] = $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql-output', [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_user' => 'centreon',
            'db_password' => '',
            'db_name' => 'centreon_storage',
            'store_in_data_bin' => null,
            'filters_category' => ['dumper', 'storage'],
        ]);

        self::assertSame(['input' => [1, 2], 'output' => [1, 2, 3]], $ids);
        JsonSnapshot::assertMatches('core_repository_api_flows', $this->readFlows($configId, $ids));

        self::assertNull($this->readRepository->findByIdAndBrokerId('output', 4, $configId), 'unknown id');
        self::assertNull($this->readRepository->findByIdAndBrokerId('input', 3, $configId), 'id of another tag');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-api-other');
        self::assertNull($this->readRepository->findByIdAndBrokerId('output', 1, $otherConfigId), 'other configuration');
    }

    public function testFlowsStoredByLegacyWritersAreReadBack(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-legacy');
        $this->insertLegacyFlows($configId);

        JsonSnapshot::assertMatches(
            'core_repository_legacy_flows',
            $this->readFlows($configId, ['input' => [0, 1], 'output' => [0, 1, 2, 3, 4, 5, 6, 7, 8]])
        );
    }

    public function testUpdateReplacesTheStoredFlow(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-update');
        $luaId = $this->flows->addThroughApi($configId, 'output', 33, 'lua-before', [
            'path' => '/before.lua',
            'filters_category' => ['neb', 'storage'],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'kept', 'value' => 'kept-value'],
                ['type' => 'password', 'name' => 'secret', 'value' => 'lua-secret'],
                ['type' => 'string', 'name' => 'removed', 'value' => 'removed-value'],
            ],
        ]);
        $fileId = $this->addFileFlow($configId, 'output', 'untouched');

        // Updating with the object returned by the reader writes back the masked (null) grouped password.
        $read = $this->readRepository->findByIdAndBrokerId('output', $luaId, $configId);
        self::assertNotNull($read);
        $this->writeRepository->update($read, $configId, $this->readRepository->findParametersByType(33));
        self::assertSame(
            [
                ['name' => 'kept', 'type' => 'string', 'value' => 'kept-value'],
                ['name' => 'removed', 'type' => 'string', 'value' => 'removed-value'],
                ['name' => 'secret', 'type' => 'password', 'value' => ''],
            ],
            self::normalize($this->findAllGroupedField($configId, $luaId, 'lua_parameter'), true),
            'the clear password is lost (stored as an empty string)'
        );

        $updated = new BrokerInputOutput($luaId, 'output', new Type(33, 'lua'), 'lua-after', [
            'path' => '/after.lua',
            'filters_category' => ['bam'],
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'kept', 'value' => 'new-value'],
                ['type' => 'password', 'name' => 'secret', 'value' => 'new-secret'],
            ],
        ]);
        $this->writeRepository->update($updated, $configId, $this->readRepository->findParametersByType(33));

        self::assertSame(
            [
                'id' => $luaId,
                'tag' => 'output',
                'type' => ['id' => 33, 'name' => 'lua'],
                'name' => 'lua-after',
                'parameters' => [
                    'filters_category' => ['bam'],
                    'lua_parameter' => [
                        ['name' => 'kept', 'type' => 'string', 'value' => 'new-value'],
                        ['name' => 'secret', 'type' => 'password', 'value' => null],
                    ],
                    'path' => '/after.lua',
                ],
            ],
            $this->describe($this->readRepository->findByIdAndBrokerId('output', $luaId, $configId))
        );
        self::assertSame('untouched', $this->readRepository->findByIdAndBrokerId('output', $fileId, $configId)?->getName());

        // Updating a flow stored by a legacy writer (group id 0, CLAPI rows) replaces all its rows.
        $this->flows->insertLegacyRows($configId, 'input', 0, [
            ['key' => 'blockId', 'value' => '2_3'],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'name', 'value' => 'clapi-input'],
            ['key' => 'port', 'value' => '5669'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 7],
            ['key' => 'legacy_only', 'value' => 'dropped'],
        ]);
        $this->writeRepository->update(
            new BrokerInputOutput(0, 'input', new Type(3, 'ipv4'), 'clapi-input-updated', ['port' => 5700, 'host' => 'example.com']),
            $configId,
            $this->readRepository->findParametersByType(3)
        );
        JsonSnapshot::assertMatches(
            'core_repository_updated_legacy_flow',
            $this->describe($this->readRepository->findByIdAndBrokerId('input', 0, $configId))
        );
    }

    public function testDeleteRemovesOnlyTheTargetedFlow(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-delete');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-delete-other');
        $this->addFileFlow($configId, 'output', 'output-1');
        $this->addFileFlow($configId, 'output', 'output-2');
        $this->addFileFlow($configId, 'input', 'input-1');
        $this->addFileFlow($otherConfigId, 'output', 'other-output-1');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'legacy-output-0',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));

        $this->writeRepository->delete($configId, 'output', 1);
        $this->writeRepository->delete($configId, 'output', 0);
        $this->writeRepository->delete($configId, 'output', 42);

        self::assertNull($this->readRepository->findByIdAndBrokerId('output', 1, $configId));
        self::assertNull($this->readRepository->findByIdAndBrokerId('output', 0, $configId));
        self::assertSame('output-2', $this->readRepository->findByIdAndBrokerId('output', 2, $configId)?->getName());
        self::assertSame('input-1', $this->readRepository->findByIdAndBrokerId('input', 1, $configId)?->getName());
        self::assertSame('other-output-1', $this->readRepository->findByIdAndBrokerId('output', 1, $otherConfigId)?->getName());
        self::assertSame(['output' => ['output-2'], 'input' => ['input-1']], $this->namesByTag($configId));
    }

    public function testFindAllGroupsFlowsByConfigurationWithClearPasswords(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-find-all');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-find-all-other');
        $this->flows->addThroughApi($configId, 'input', 35, 'bbdo-server-input', [
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => 'grpc-secret',
            'filters_category' => ['neb'],
        ]);
        // The group at index 1 is skipped (no name nor value), so stored fieldIndex values are 0 and 2.
        $this->flows->addThroughApi($configId, 'output', 33, 'lua-output', [
            'path' => '/lua.lua',
            'lua_parameter' => [
                ['type' => 'string', 'name' => 'endpoint', 'value' => 'https://example.com'],
                ['type' => 'string', 'name' => '', 'value' => ''],
                ['type' => 'password', 'name' => 'api-key', 'value' => 'lua-secret'],
            ],
        ]);
        $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql', [
            'db_host' => 'db.example.com',
            'db_password' => 'db-secret',
        ]);
        $this->insertLegacyFlows($otherConfigId);

        $all = $this->readRepository->findAll();
        self::assertArrayHasKey($configId, $all);
        self::assertArrayHasKey($otherConfigId, $all);
        self::assertContainsOnlyInstancesOf(BrokerInputOutput::class, $all[$configId]);

        $lua = $this->findAllGroupedField($configId, 1, 'lua_parameter');
        ksort($lua);
        self::assertSame([0, 2], array_keys($lua), 'findAll() keeps the stored fieldIndex keys');
        self::assertSame(
            ['name' => 'api-key', 'type' => 'password', 'value' => 'lua-secret'],
            self::normalize($lua[2], true),
            'findAll() keeps grouped passwords in clear'
        );
        $readLua = $this->readRepository->findByIdAndBrokerId('output', 1, $configId)?->getParameters()['lua_parameter'] ?? null;
        self::assertIsArray($readLua);
        self::assertTrue(array_is_list($readLua), 'findByIdAndBrokerId() re-indexes them');
        self::assertCount(2, $readLua);

        JsonSnapshot::assertMatches('core_repository_find_all', [
            'api' => $this->findAllFlows($configId),
            'legacy' => $this->findAllFlows($otherConfigId),
        ]);
    }

    public function testFindVaultPathByBrokerId(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'core-repo-vault');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-vault-other');
        self::assertNull($this->readRepository->findVaultPathByBrokerId($configId), 'no flow');

        $this->flows->addThroughApi($configId, 'output', 34, 'unified-sql', [
            'db_host' => 'db.example.com',
            'db_user' => 'my-secret::user',
            'db_password' => 'clear-secret',
        ]);
        self::assertNull($this->readRepository->findVaultPathByBrokerId($configId), 'no value starting with secret::');

        // Grouped password values are searched as well.
        $this->flows->addThroughApi($configId, 'output', 33, 'lua-vault', [
            'path' => '/lua.lua',
            'lua_parameter' => [['type' => 'password', 'name' => 'api-key', 'value' => self::VAULT_PATH]],
        ]);
        self::assertSame(self::VAULT_PATH, $this->readRepository->findVaultPathByBrokerId($configId));
        self::assertNull($this->readRepository->findVaultPathByBrokerId($otherConfigId), 'scoped to the configuration');

        // Any key matches, even a flow name, and the value is returned as stored.
        $this->flows->addThroughApi($otherConfigId, 'input', 11, 'secret::not-a-vault-path', ['path' => '/tmp/in']);
        self::assertSame('secret::not-a-vault-path', $this->readRepository->findVaultPathByBrokerId($otherConfigId));

        // Values written by legacy writers are found too.
        $legacyConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'core-repo-vault-legacy');
        $this->flows->insertLegacyRows($legacyConfigId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'legacy-sql',
            'db_password' => self::VAULT_PATH,
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));
        self::assertSame(self::VAULT_PATH, $this->readRepository->findVaultPathByBrokerId($legacyConfigId));
    }

    public function testFindTypeAndParametersByTypeReadTheBrokerMetadata(): void
    {
        self::assertEquals(new Type(33, 'lua'), $this->readRepository->findType('output', 33));
        self::assertEquals(new Type(3, 'ipv4'), $this->readRepository->findType('input', 3));
        self::assertNull($this->readRepository->findType('input', 33), 'lua is an output only type');
        self::assertNull($this->readRepository->findType('output', 999999));

        $fields = $this->readRepository->findParametersByType(33);
        self::assertSame(['path', 'filters', 'lua_parameter'], array_keys($fields));
        self::assertInstanceOf(BrokerInputOutputField::class, $fields['path']);
        self::assertIsArray($fields['filters']);
        self::assertSame(['category'], array_keys($fields['filters']));
        self::assertSame('multiselect', $fields['filters']['category']->getType());
        self::assertIsArray($fields['lua_parameter']);
        $luaFields = $fields['lua_parameter'];
        ksort($luaFields);
        self::assertSame(['name', 'type', 'value'], array_keys($luaFields));
        self::assertTrue($luaFields['name']->isMultiple());
        self::assertSame([], $this->readRepository->findParametersByType(999999));
    }

    /**
     * Stores one flow per legacy writer, ids: input 0-1, output 0-8.
     */
    private function insertLegacyFlows(int $configId): void
    {
        // Base configuration (www/install/var/baseconf/centreon-broker.sql): group id 0, flat rows.
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-input',
            'port' => '5669',
            'host' => '',
            'retry_interval' => '15',
            'protocol' => 'bbdo',
            'tls' => 'auto',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 0, [
            ['key' => 'name', 'value' => 'central-broker-master-event-script'],
            ['key' => 'script_path', 'value' => '/usr/share/centreon/bin/console agent-configuration:host:create'],
            ['key' => 'timeout', 'value' => '15'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'event', 'value' => 'neb:UnknownHost', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'type', 'value' => 'event_script'],
            ['key' => 'blockId', 'value' => '1_37'],
        ]);
        // CLAPI ADDINPUT: blockId first, required fields only.
        $this->flows->insertLegacyRows($configId, 'input', 1, BrokerFlowFixture::flatRows([
            'blockId' => '2_3',
            'type' => 'ipv4',
            'name' => 'clapi-input',
            'port' => '5669',
            'protocol' => 'bbdo',
        ]));
        // App poller creation (DbalBrokerConfigurationRepository): type and blockId last.
        $this->flows->insertLegacyRows($configId, 'output', 1, BrokerFlowFixture::flatRows([
            'name' => 'poller-module-output',
            'port' => '5669',
            'host' => '10.0.0.1',
            'failover' => '',
            'retry_interval' => '15',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));
        // CLAPI SETOUTPUT on lists: duplicated parent rows, children linked to the field group id (7),
        // repeated children, grouped field without fieldIndex.
        $this->flows->insertLegacyRows($configId, 'output', 2, [
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
        // Remote wizard (LinkedPollerConfigurationService): grp_level only, hierarchy columns lost.
        $this->flows->insertLegacyRows($configId, 'output', 3, [
            ['key' => 'name', 'value' => 'forward-to-master'],
            ['key' => 'port', 'value' => '5669'],
            ['key' => 'host', 'value' => '192.168.0.10'],
            ['key' => 'filters', 'value' => ''],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'blockId', 'value' => '1_3'],
        ]);
        // centreon-map configure.sh: hardcoded blockId, misspelled key, bam and neb categories.
        $this->flows->insertLegacyRows($configId, 'output', 4, [
            ['key' => 'name', 'value' => 'centreon-map'],
            ['key' => 'port', 'value' => '5758'],
            ['key' => 'negociation', 'value' => 'yes'],
            ['key' => 'type', 'value' => 'ipv4'],
            ['key' => 'blockId', 'value' => '1_3'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'bam', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        // centreon-bam installBrokerConf.php: types unknown to cb_type when the module is not installed.
        $this->flows->insertLegacyRows($configId, 'output', 5, BrokerFlowFixture::flatRows([
            'blockId' => '1_38',
            'name' => 'centreon-bam-monitoring',
            'type' => 'bam',
            'cache' => 'yes',
            'db_name' => 'centreon',
            'db_password' => 'bam-secret',
            'command_file' => '/var/lib/centreon-engine/rw/centengine.cmd',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 6, [
            ['key' => 'blockId', 'value' => '1_39'],
            ['key' => 'name', 'value' => 'centreon-bam-reporting'],
            ['key' => 'type', 'value' => 'bam_bi'],
            ['key' => 'db_name', 'value' => 'centreon_storage'],
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'bam', 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        // centreon-anomaly-detection addOutputLua: grouped lua parameters with fieldIndex, one password.
        $this->flows->insertLegacyRows($configId, 'output', 7, [
            ['key' => 'name', 'value' => 'forward-to-anomaly-detection'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/centreon-anomaly-detection.lua'],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
            ['key' => 'lua_parameter__type', 'value' => 'number', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'max_queue_size', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '100', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__type', 'value' => 'password', 'fieldIndex' => 1],
            ['key' => 'lua_parameter__name', 'value' => 'saas_token', 'fieldIndex' => 1],
            ['key' => 'lua_parameter__value', 'value' => 'saas-token', 'fieldIndex' => 1],
        ]);
        // A flow without blockId is invisible to the readers.
        $this->flows->insertLegacyRows($configId, 'output', 8, BrokerFlowFixture::flatRows([
            'name' => 'no-block-id',
            'type' => 'ipv4',
            'port' => '5669',
        ]));
    }

    private function addFileFlow(int $configId, string $tag, string $name): int
    {
        /** @var 'input'|'output' $tag */
        return $this->flows->addThroughApi($configId, $tag, 11, $name, ['path' => '/var/lib/centreon-broker/' . $name]);
    }

    /**
     * @param array<string, list<int>> $ids
     *
     * @return array<string, array<int|string, mixed>>
     */
    private function readFlows(int $configId, array $ids): array
    {
        $flows = [];
        foreach ($ids as $tag => $tagIds) {
            foreach ($tagIds as $id) {
                $flows[$tag][(string) $id] = $this->describe($this->readRepository->findByIdAndBrokerId($tag, $id, $configId));
            }
        }

        return $flows;
    }

    /**
     * findAll() result of one configuration, indexed by tag and id.
     *
     * @return array<string, array<int, array<string, mixed>|null>>
     */
    private function findAllFlows(int $configId): array
    {
        $flows = [];
        foreach ($this->readRepository->findAll()[$configId] ?? [] as $inputOutput) {
            $flows[$inputOutput->getTag()][$inputOutput->getId()] = $this->describe($inputOutput, sortGroupedFields: false);
        }
        foreach ($flows as $tag => $tagFlows) {
            ksort($tagFlows);
            $flows[$tag] = $tagFlows;
        }

        return $flows;
    }

    /**
     * Raw grouped field of an output as returned by findAll().
     *
     * @return array<mixed>
     */
    private function findAllGroupedField(int $configId, int $outputId, string $name): array
    {
        foreach ($this->readRepository->findAll()[$configId] ?? [] as $inputOutput) {
            if ($inputOutput->getTag() === 'output' && $inputOutput->getId() === $outputId) {
                $value = $inputOutput->getParameters()[$name] ?? null;
                self::assertIsArray($value);

                return $value;
            }
        }
        self::fail(sprintf('Output %d not returned by findAll()', $outputId));
    }

    /**
     * @return array<string, list<string>>
     */
    private function namesByTag(int $configId): array
    {
        $names = [];
        foreach ($this->readRepository->findAll()[$configId] ?? [] as $inputOutput) {
            $names[$inputOutput->getTag()][] = $inputOutput->getName();
        }

        return $names;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function describe(?BrokerInputOutput $inputOutput, bool $sortGroupedFields = true): ?array
    {
        if (! $inputOutput instanceof BrokerInputOutput) {
            return null;
        }

        return [
            'id' => $inputOutput->getId(),
            'tag' => $inputOutput->getTag(),
            'type' => ['id' => $inputOutput->getType()->id, 'name' => $inputOutput->getType()->name],
            'name' => $inputOutput->getName(),
            'parameters' => self::normalize($inputOutput->getParameters(), $sortGroupedFields),
        ];
    }

    /**
     * Sorts object keys, and lists whose order comes from the undefined row order: multiselect values always,
     * grouped entries when $sortGroupedFields (findAll() keys them by their stored fieldIndex instead).
     */
    private static function normalize(mixed $value, bool $sortGroupedFields): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map(static fn (mixed $item): mixed => self::normalize($item, $sortGroupedFields), $value);
        $containsArrays = array_filter($value, is_array(...)) !== [];
        if (! array_is_list($value) || ($containsArrays && ! $sortGroupedFields)) {
            ksort($value);

            return $value;
        }
        usort(
            $value,
            static fn (mixed $left, mixed $right): int => strcmp(
                json_encode($left, JSON_THROW_ON_ERROR),
                json_encode($right, JSON_THROW_ON_ERROR),
            ),
        );

        return $value;
    }
}
