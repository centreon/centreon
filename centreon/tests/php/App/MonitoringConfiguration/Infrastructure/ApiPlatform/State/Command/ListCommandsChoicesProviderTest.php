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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListCommandsChoicesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts/commands';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_HOSTS_R[W]
    // (Configuration > Hosts > Hosts), bridged to HostPermissionEnum via
    // DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_TOPOLOGY_PAGES = [6, 601, 60101];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;
    private const TYPE_NOTIFICATION = 1;
    private const TYPE_CHECK = 2;
    private const TYPE_MISCELLANEOUS = 3;

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItIsForbiddenForAUserGrantedOnlyTheHostReadTopologyRole(): void
    {
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_ONLY);

        $this->login($username);

        // the host form fills a host, so the selector demands read-write: a read-only grant
        // reaches HostPermissionEnum::CanRead only, which the operation does not accept
        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItListsCommandsForAUserGrantedTheHostReadWriteTopologyRole(): void
    {
        $name = "hostcmd-{$this->tag}";
        $this->insertCommand($name, self::TYPE_CHECK);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);

        $this->login($username);

        // proves the point of the endpoint: filling a host's check and event handler commands
        // needs no command role of its own
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $name]]]);
        self::assertResponseIsSuccessful();
        self::assertSame([$name], array_column((array) $response->toArray()['member'], 'name'));

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        // the selector exposes the id and the name only, never the command line
        self::assertSame([], array_diff(array_keys($member[0]), ['@id', '@type', 'id', 'name']));
    }

    public function testItReturnsAnEmptyCollectionWhenNoCommandMatches(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "none-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame([], (array) $response->toArray()['member']);
        self::assertEquals(0, $response->toArray()['totalItems']);
    }

    public function testItFiltersCommandsByType(): void
    {
        $this->insertCommand("check-{$this->tag}", self::TYPE_CHECK);
        $this->insertCommand("notif-{$this->tag}", self::TYPE_NOTIFICATION);
        $this->insertCommand("misc-{$this->tag}", self::TYPE_MISCELLANEOUS);

        $this->login();

        // the host form asks for "type[]=Check"
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['type' => ['Check'], 'name' => ['lk' => $this->tag]],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(["check-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItFiltersCommandsBySeveralTypes(): void
    {
        $this->insertCommand("check-{$this->tag}", self::TYPE_CHECK);
        $this->insertCommand("notif-{$this->tag}", self::TYPE_NOTIFICATION);
        $this->insertCommand("misc-{$this->tag}", self::TYPE_MISCELLANEOUS);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['type' => ['Check', 'Miscellaneous'], 'name' => ['lk' => $this->tag]],
        ]);
        self::assertResponseIsSuccessful();
        $names = array_column((array) $response->toArray()['member'], 'name');
        sort($names);
        self::assertSame(["check-{$this->tag}", "misc-{$this->tag}"], $names);
    }

    public function testItFiltersCommandsByActivation(): void
    {
        $this->insertCommand("on-{$this->tag}", self::TYPE_CHECK);
        $this->insertCommand("off-{$this->tag}", self::TYPE_CHECK, isActivated: false);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['is_activated' => 'true', 'name' => ['lk' => $this->tag]],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(["on-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItFiltersCommandsByNameUsingLikeOperator(): void
    {
        $this->insertCommand("match-{$this->tag}", self::TYPE_CHECK);
        $this->insertCommand("other-{$this->tag}", self::TYPE_CHECK);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["match-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItPaginatesCommands(): void
    {
        $this->insertCommand("pg-{$this->tag}-A", self::TYPE_CHECK);
        $this->insertCommand("pg-{$this->tag}-B", self::TYPE_CHECK);
        $this->insertCommand("pg-{$this->tag}-C", self::TYPE_CHECK);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRejectsAnUnknownType(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['type' => ['Unknown']]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "hostcmd-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertCommand(string $name, int $type, bool $isActivated = true): void
    {
        $this->connection->insert('command', [
            'command_name' => $name,
            'command_line' => '$USER1$/check_dummy',
            'command_type' => $type,
            'enable_shell' => '0',
            'command_activate' => $isActivated ? '1' : '0',
            'command_locked' => '0',
        ]);
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::notFalse($contactId);
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    /**
     * @param int $accessRight CentreonACL::ACL_ACCESS_READ_WRITE (1) or ACL_ACCESS_READ_ONLY (2)
     */
    private function grantHostTopologyRole(int $contactId, int $accessRight): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => "topology-group-{$this->tag}",
            'acl_group_alias' => "topology-group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => "topology-rule-{$this->tag}",
            'acl_topo_alias' => "topology-rule-{$this->tag}",
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach (self::HOST_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => $accessRight,
            ]);
        }
    }
}
