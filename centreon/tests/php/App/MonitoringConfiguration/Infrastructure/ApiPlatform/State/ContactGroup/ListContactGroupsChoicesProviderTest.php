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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ContactGroup;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListContactGroupsChoicesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts/contact_groups';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_HOSTS_R[W]
    // (Configuration > Hosts > Hosts), bridged to HostPermissionEnum via
    // DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_TOPOLOGY_PAGES = [6, 601, 60101];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;

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

    public function testItListsContactGroupsForAUserGrantedTheHostReadWriteTopologyRole(): void
    {
        $name = "hostcg-{$this->tag}";
        $contactGroupId = $this->insertContactGroup($name);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);
        // ACL data-scoping still applies (see the restricted-user test below); only the
        // *permission gate* is proven here to need no contact-group role of its own.
        $this->restrictAclGroupToContactGroups($aclGroupId, [$contactGroupId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $name]]]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$name], array_column($member, 'name'));
        self::assertSame([], array_diff(array_keys($member[0]), ['@id', '@type', 'id', 'name']));
    }

    /**
     * Legacy's host-form contact-group select appends LDAP-only groups unfiltered, but a
     * LDAP-only group is refused on save (MON-208474): the host-scoped selector excludes them
     * outright instead of surfacing an option the save would reject.
     */
    public function testItExcludesLdapContactGroups(): void
    {
        $localName = "local-{$this->tag}";
        $this->insertContactGroup($localName, type: 'local');
        $ldapName = "ldap-{$this->tag}";
        $this->insertContactGroup($ldapName, type: 'ldap');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame([$localName], array_column((array) $response->toArray()['member'], 'name'));
    }

    /**
     * Same ACL-scoping behavior as the generic /configuration/contact_groups endpoint.
     */
    public function testItRestrictsListingToAccessibleContactGroupsForARestrictedUser(): void
    {
        $accessibleId = $this->insertContactGroup("acl-in-{$this->tag}");
        $this->insertContactGroup("acl-out-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);
        $this->restrictAclGroupToContactGroups($aclGroupId, [$accessibleId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["acl-in-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItFiltersContactGroupsByNameUsingLikeOperator(): void
    {
        $this->insertContactGroup("match-{$this->tag}");
        $this->insertContactGroup("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["match-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItPaginatesContactGroups(): void
    {
        $this->insertContactGroup("pg-{$this->tag}-A");
        $this->insertContactGroup("pg-{$this->tag}-B");
        $this->insertContactGroup("pg-{$this->tag}-C");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "cg-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertContactGroup(string $name, string $type = 'local'): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => $type,
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
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
     * Grants the host read/write topology role and returns the acl group id (reused for the
     * row-level contact-group scoping below).
     *
     * @param int $accessRight CentreonACL::ACL_ACCESS_READ_WRITE (1) or ACL_ACCESS_READ_ONLY (2)
     */
    private function grantHostTopologyRole(int $contactId, int $accessRight): int
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

        return $aclGroupId;
    }

    /**
     * @param list<int> $contactGroupIds
     */
    private function restrictAclGroupToContactGroups(int $aclGroupId, array $contactGroupIds): void
    {
        foreach ($contactGroupIds as $contactGroupId) {
            $this->connection->insert('acl_group_contactgroups_relations', [
                'acl_group_id' => $aclGroupId,
                'cg_cg_id' => $contactGroupId,
            ]);
        }
    }
}
