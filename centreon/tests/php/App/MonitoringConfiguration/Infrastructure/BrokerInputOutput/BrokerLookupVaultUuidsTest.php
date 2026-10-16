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

use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

require_once __DIR__ . '/../../../../../../www/include/common/vault-functions.php';

/**
 * Characterization of the broker readers of www/include/common/vault-functions.php:
 * retrieveMultipleBrokerConfigUuidsFromDatabase() and deleteBrokerConfigsFromVault().
 *
 * Both read the global $pearDB, which is replaced by the autocommit test connection for the duration of each test
 * (and checked to be it), so the real platform database is never used.
 *
 * Pinned current behavior:
 * - every parameter value (any key, any input/output, lua parameters included) starting with "secret::" of the
 *   given configurations is considered, DISTINCT on the whole value;
 * - the uuid is the segment between the last "/" and the last "::"; values without "/" are dropped;
 * - two different vault paths sharing the same uuid return the uuid twice (and deleteBrokerConfigsFromVault()
 *   deletes it twice);
 * - an empty list of configuration ids produces an invalid SQL query (exception).
 * The order of the uuids (and their keys, array_filter() preserving them) follows the undefined row order, so
 * results are compared sorted.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupVaultUuidsTest extends KernelTestCase
{
    private const POLLER_ID = 9530;
    private const UUID_A = '0a1b2c3d-0000-4000-8000-00000000000a';
    private const UUID_B = '0a1b2c3d-0000-4000-8000-00000000000b';
    private const UUID_C = '0a1b2c3d-0000-4000-8000-00000000000c';

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private mixed $previousPearDB = null;

    private bool $hadPearDB = false;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::POLLER_ID);

        $this->hadPearDB = array_key_exists('pearDB', $GLOBALS);
        $this->previousPearDB = $GLOBALS['pearDB'] ?? null;
        $GLOBALS['pearDB'] = $this->database->configuration;
    }

    protected function tearDown(): void
    {
        if ($this->hadPearDB) {
            $GLOBALS['pearDB'] = $this->previousPearDB;
        } else {
            unset($GLOBALS['pearDB']);
        }
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testUuidsOfEveryVaultPathOfTheGivenConfigurations(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-a');
        $otherConfigId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-b');
        $ignoredConfigId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-ignored');

        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'unified-sql',
            'db_user' => 'centreon',
            'db_password' => $this->vaultPath(self::UUID_A, 'unified-sql_db_password'),
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 1, [
            ['key' => 'name', 'value' => 'stream-connector'],
            ['key' => 'path', 'value' => '/usr/share/centreon-broker/lua/connector.lua'],
            ['key' => 'lua_parameter__value', 'value' => $this->vaultPath(self::UUID_A, 'stream-connector_lua-password'), 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'lua-password', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__type', 'value' => 'password', 'fieldIndex' => 0],
            ['key' => 'type', 'value' => 'lua'],
            ['key' => 'blockId', 'value' => '1_33'],
        ]);
        $this->flows->insertLegacyRows($otherConfigId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'bbdo-server',
            'authorization' => $this->vaultPath(self::UUID_B, 'bbdo-server_authorization'),
            'type' => 'bbdo_server',
            'blockId' => '2_35',
        ]));
        $this->flows->insertLegacyRows($ignoredConfigId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'ignored',
            'db_password' => $this->vaultPath(self::UUID_C, 'ignored_db_password'),
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));

        self::assertSame(
            [self::UUID_A, self::UUID_A, self::UUID_B],
            $this->retrieve([$configId, $otherConfigId]),
        );
        self::assertSame([self::UUID_C], $this->retrieve([$ignoredConfigId]));
    }

    public function testIdenticalVaultPathsAreReturnedOnce(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-same-path');
        $path = $this->vaultPath(self::UUID_A, 'shared');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'first', 'db_password' => $path, 'type' => 'unified_sql', 'blockId' => '1_34',
        ]));
        $this->flows->insertLegacyRows($configId, 'output', 1, BrokerFlowFixture::flatRows([
            'name' => 'second', 'db_password' => $path, 'type' => 'unified_sql', 'blockId' => '1_34',
        ]));

        self::assertSame([self::UUID_A], $this->retrieve([$configId]));
    }

    public function testValuesNotLookingLikeAVaultPathAreIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-not-paths');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'not-paths',
            'db_password' => 'plain-password',
            'db_user' => 'secret::without-any-slash::key',
            'db_host' => 'prefix secret::hashicorp_vault::centreon/data/broker/' . self::UUID_B . '::key',
            'db_name' => 'SECRET::hashicorp_vault::centreon/data/broker/' . self::UUID_C . '::key',
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));

        // LIKE is case-insensitive with the column collation: the upper case prefix matches.
        self::assertSame([self::UUID_C], $this->retrieve([$configId]));
    }

    public function testConfigurationWithoutVaultPathReturnsNothing(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-none');

        self::assertSame([], $this->retrieve([$configId]));
    }

    public function testEmptyListOfConfigurationsIsAnInvalidQuery(): void
    {
        $this->expectException(\PDOException::class);

        $this->legacyFunction('retrieveMultipleBrokerConfigUuidsFromDatabase')->invoke([]);
    }

    public function testDeleteBrokerConfigsFromVaultDeletesEveryRetrievedUuid(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-vault-delete');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'unified-sql',
            'db_password' => $this->vaultPath(self::UUID_A, 'unified-sql_db_password'),
            'db_user' => $this->vaultPath(self::UUID_A, 'unified-sql_db_user'),
            'type' => 'unified_sql',
            'blockId' => '1_34',
        ]));
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'bbdo-server',
            'authorization' => $this->vaultPath(self::UUID_B, 'bbdo-server_authorization'),
            'type' => 'bbdo_server',
            'blockId' => '2_35',
        ]));

        $deleted = [];
        $vault = $this->createMock(WriteVaultRepositoryInterface::class);
        $vault->method('delete')->willReturnCallback(static function (string $uuid) use (&$deleted): void {
            $deleted[] = $uuid;
        });

        $this->legacyFunction('deleteBrokerConfigsFromVault')->invoke($vault, [$configId]);

        sort($deleted);
        self::assertSame([self::UUID_A, self::UUID_A, self::UUID_B], $deleted);
    }

    private function vaultPath(string $uuid, string $key): string
    {
        return 'secret::hashicorp_vault::centreon/data/broker/' . $uuid . '::' . $key;
    }

    /**
     * @param int[] $configIds
     *
     * @return list<string>
     */
    private function retrieve(array $configIds): array
    {
        self::assertSame($this->database->configuration, $GLOBALS['pearDB'], 'The legacy code must use the test connection');
        $uuids = $this->legacyFunction('retrieveMultipleBrokerConfigUuidsFromDatabase')->invoke($configIds);
        self::assertIsArray($uuids);
        self::assertContainsOnlyString($uuids);
        $uuids = array_values($uuids);
        sort($uuids);

        return $uuids;
    }

    /**
     * The legacy functions are declared by a file required at runtime, unknown to static analysis.
     */
    private function legacyFunction(string $name): \ReflectionFunction
    {
        return new \ReflectionFunction($name);
    }
}
