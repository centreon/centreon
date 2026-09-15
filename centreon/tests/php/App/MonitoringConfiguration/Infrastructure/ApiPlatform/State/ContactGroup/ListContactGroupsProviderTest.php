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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListContactGroupsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/contact_groups';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_R
    // (Configuration > Users > Contact Groups), bridged to ContactGroupPermissionEnum::CanRead
    // via DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const CONTACT_GROUP_READ_TOPOLOGY_PAGES = [6, 603, 60302];

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = bin2hex(random_bytes(6));
    }

    public function testItListsContactGroupsForAnAdmin(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ContactGroupResource::class);
    }

    public function testItIsForbiddenForANonAdminWithoutPermission(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));

        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testItFiltersByNameWithEqualOperator(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var string|false $name */
        $name = $connection->fetchOne('SELECT cg_name FROM contactgroup LIMIT 1');
        if ($name === false) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['eq' => $name]]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['member' => [['name' => $name]]]);
    }

    public function testItFiltersByNameWithLikeOperatorNoMatch(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'zz_no_such_contact_group_zz']]]);
        self::assertResponseIsSuccessful();
        $this->assertCount(0, (array) $response->toArray()['member']);
    }

    public function testItFiltersByNameWithLikeOperatorMatch(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var string|false $name */
        $name = $connection->fetchOne('SELECT cg_name FROM contactgroup LIMIT 1');
        if ($name === false) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $name]]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['member' => [['name' => $name]]]);
    }

    public function testItPaginates(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $totalRaw = $connection->fetchOne('SELECT COUNT(*) FROM contactgroup');
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;
        if ($total === 0) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '1', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(ContactGroupResource::class);
        $this->assertCount(1, (array) $response->toArray()['member']);
        $this->assertSame($total, $response->toArray()['totalItems']);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => 'Supervisors']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAScalarIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => '1']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsANonNumericIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => ['eq' => 'not-a-number']]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAZeroIdFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['id' => ['eq' => '0']]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItScopesRowsForANonAdminWithPermission(): void
    {
        // The security-critical wire: a non-admin *with* the read permission must be ACL-scoped by
        // the provider (withViewerId). A regression dropping that scoping would leak every group.
        $username = "viewer_{$this->tag}";
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantContactGroupReadTopologyRole($contactId);

        $reachableId = $this->insertContactGroup("reachable_{$this->tag}");
        $this->insertContactGroup("unreachable_{$this->tag}");
        $this->restrictAclGroupToContactGroups($aclGroupId, [$reachableId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();

        $names = array_column((array) $response->toArray()['member'], 'name');
        self::assertContains("reachable_{$this->tag}", $names);
        self::assertNotContains("unreachable_{$this->tag}", $names);
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    private function insertContactGroup(string $name): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Grant the Contact Groups read topology role to a non-admin, and return the acl group id
     * (reused for the row-level scoping below).
     */
    private function grantContactGroupReadTopologyRole(int $contactId): int
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

        foreach (self::CONTACT_GROUP_READ_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::scalar($topologyId, "topology_page {$topologyPage} not found in fixtures");

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 2, // read-only
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
