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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Shared\ApiTestCase;

final class PatchHostMacrosProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    /** @var array{headers: array{Content-Type: string}} */
    private const PATCH_HEADERS = ['headers' => ['Content-Type' => 'application/merge-patch+json']];

    private Connection $connection;

    private int $hostId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->login();
        $this->connection->insert('nagios_server', [
            'name' => 'poller-' . bin2hex(random_bytes(6)),
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);
        $pollerId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host', [
            'host_name' => 'host-' . bin2hex(random_bytes(6)),
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $this->hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $this->hostId, 'nagios_server_id' => $pollerId]);
    }

    public function testTheMacrosAreReplacedWrittenAndRemoved(): void
    {
        $port = $this->insertMacro('PORT', '80');
        $dropped = $this->insertMacro('DROPPED', 'x');

        $this->patch(['check_options' => ['macros' => [
            ['id' => $port, 'name' => 'port', 'value' => '8080'],
            ['name' => 'added', 'value' => 'y'],
        ]]]);
        self::assertSame(['ADDED' => 'y', 'PORT' => '8080'], $this->macros());
        self::assertSame($port, $this->macroId('PORT'));
        self::assertFalse($this->macroExists($dropped));

        $this->patch(['check_options' => ['macros_to_upsert' => [
            ['id' => $port, 'name' => 'port', 'value' => '9090'],
            ['name' => 'other', 'value' => 'z'],
        ]]]);
        self::assertSame(['ADDED' => 'y', 'OTHER' => 'z', 'PORT' => '9090'], $this->macros());
        self::assertSame($port, $this->macroId('PORT'));

        $this->patch(['check_options' => ['macros_to_remove' => [$port, $this->macroId('ADDED')]]]);
        self::assertSame(['OTHER' => 'z'], $this->macros());
    }

    public function testThePasswordOfAMacroIsKeptWithoutAValue(): void
    {
        $token = $this->insertMacro('TOKEN', 'the-secret', isPassword: true);

        $this->patch(['check_options' => ['macros_to_upsert' => [['id' => $token, 'name' => 'token', 'is_password' => true]]]]);

        self::assertSame(['TOKEN' => 'the-secret'], $this->macros());
    }

    public function testTheMacrosAreLeftAloneByAnUpdateOfAnotherKey(): void
    {
        $this->insertMacro('PORT', '80');

        $this->patch(['check_options' => ['args' => []]]);

        self::assertSame(['PORT' => '80'], $this->macros());
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('refusedBodyProvider')]
    public function testARefusedBodyChangesNothing(array $body): void
    {
        $this->insertMacro('PORT', '80');
        $this->connection->insert('nagios_macro', ['macro_name' => '$_HOSTRESERVEDTEST$']);

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['PORT' => '80'], $this->macros());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedBodyProvider(): iterable
    {
        yield 'two forms of the macros' => [['check_options' => ['macros' => [], 'macros_to_remove' => [1]]]];

        yield 'a macro without a value' => [['check_options' => ['macros_to_upsert' => [['name' => 'new']]]]];

        yield 'a password to keep without an id' => [['check_options' => ['macros_to_upsert' => [['name' => 'new', 'is_password' => true]]]]];

        yield 'a reserved name' => [['check_options' => ['macros_to_upsert' => [['name' => 'reservedtest', 'value' => 'x']]]]];

        yield 'a vault reference as a value' => [['check_options' => ['macros_to_upsert' => [['name' => 'new', 'value' => 'secret::vault::monitoring/hosts/other::_HOSTNEW', 'is_password' => true]]]]];

        yield 'an unknown macro' => [['check_options' => ['macros' => [['id' => 999999, 'name' => 'port', 'value' => '1']]]]];

        yield 'a removal of an unknown macro' => [['check_options' => ['macros_to_remove' => [999999]]]];

        yield 'a new macro with the name of one the host has' => [['check_options' => ['macros_to_upsert' => [['name' => 'port', 'value' => '1']]]]];

        yield 'two macros of the same name in the list' => [['check_options' => ['macros_to_upsert' => [['name' => 'new', 'value' => '1'], ['name' => 'NEW', 'value' => '2']]]]];

        yield 'a removal that is not a list of ids' => [['check_options' => ['macros_to_remove' => ['port']]]];
    }

    public function testARenameOntoTheNameOfAnotherMacroIsRefusedAndNothingIsLost(): void
    {
        $plain = $this->insertMacro('PLAIN', 'visible');
        $this->insertMacro('OTHER', 'kept');

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => ['check_options' => ['macros_to_upsert' => [['id' => $plain, 'name' => 'other', 'value' => 'renamed']]]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['OTHER' => 'kept', 'PLAIN' => 'visible'], $this->macros());
    }

    #[DataProvider('requesters')]
    public function testOnlyWhoMaySeeAndChangeTheHostChangesItsMacros(string $requester, int $status, string $key): void
    {
        $portId = $this->insertMacro('PORT', '80');
        $this->actAs($requester);
        $body = match ($key) {
            'macros' => ['check_options' => ['macros' => [['id' => $portId, 'name' => 'port', 'value' => '8080']]]],
            'macros_to_upsert' => ['check_options' => ['macros_to_upsert' => [['id' => $portId, 'name' => 'port', 'value' => '8080']]]],
            default => ['check_options' => ['macros_to_remove' => [$portId]]],
        };

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame($status);
        $changed = match ($key) {
            'macros_to_remove' => [],
            default => ['PORT' => '8080'],
        };
        self::assertSame($status === 204 ? $changed : ['PORT' => '80'], $this->macros());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function requesters(): iterable
    {
        foreach (['macros', 'macros_to_upsert', 'macros_to_remove'] as $key) {
            yield 'an administrator, ' . $key => ['administrator', 204, $key];

            yield 'a user who has access to the host, ' . $key => ['user with access to the host', 204, $key];

            yield 'a user without any access right, ' . $key => ['user without any access right', 403, $key];

            yield 'a user who cannot see the host, ' . $key => ['user who cannot see the host', 404, $key];
        }
    }

    private function actAs(string $requester): void
    {
        if ($requester === 'administrator') {
            return;
        }

        $alias = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $alias, admin: false);
        if ($requester !== 'user without any access right') {
            $aclGroupId = $this->grantHostReadAndWrite($alias);
            if ($requester === 'user with access to the host') {
                /** @var Connection $realTimeConnection */
                $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
                $realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $this->hostId]);
            }
        }

        $this->login($alias);
    }

    /**
     * Gives the user the "Configuration > Hosts > Hosts" read-write topology access, through an access group
     * whose id is returned.
     */
    private function grantHostReadAndWrite(string $alias): int
    {
        /** @var int|string $contactId */
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $alias]);

        $this->connection->insert('acl_groups', ['acl_group_name' => 'macros-' . $contactId, 'acl_group_alias' => 'macros-' . $contactId, 'acl_group_activate' => '1']);
        $aclGroupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $aclGroupId, 'contact_contact_id' => $contactId]);

        $this->connection->insert('acl_topology', ['acl_topo_name' => 'macros-' . $contactId, 'acl_topo_alias' => 'macros-' . $contactId, 'acl_topo_activate' => '1']);

        $aclTopologyId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $aclGroupId, 'acl_topology_id' => $aclTopologyId]);

        foreach ([6, 601, 60101] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = :page', ['page' => $topologyPage]);
            self::assertIsScalar($topologyId, "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', ['topology_topology_id' => (int) $topologyId, 'acl_topo_id' => $aclTopologyId, 'access_right' => 1]);
        }

        return $aclGroupId;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(array $body): void
    {
        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $this->hostId, self::PATCH_HEADERS + ['json' => $body]);

        self::assertResponseStatusCodeSame(204);
    }

    private function insertMacro(string $name, string $value, bool $isPassword = false): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => '$_HOST' . $name . '$',
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'host_host_id' => $this->hostId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return array<string, string> the value of each macro of the host, by short name
     */
    private function macros(): array
    {
        /** @var array<string, string> $rows */
        $rows = $this->connection->fetchAllKeyValue('SELECT host_macro_name, host_macro_value FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY host_macro_name', [$this->hostId]);

        $macros = [];
        foreach ($rows as $name => $value) {
            $macros[mb_substr($name, 6, -1)] = $value;
        }

        return $macros;
    }

    private function macroId(string $name): int
    {
        /** @var int|string $id */
        $id = $this->connection->fetchOne('SELECT host_macro_id FROM on_demand_macro_host WHERE host_host_id = ? AND host_macro_name = ?', [$this->hostId, '$_HOST' . $name . '$']);

        return (int) $id;
    }

    private function macroExists(int $id): bool
    {
        return $this->connection->fetchOne('SELECT host_macro_id FROM on_demand_macro_host WHERE host_macro_id = ?', [$id]) !== false;
    }
}
