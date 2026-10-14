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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostGroup;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostGroup\HostGroupResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListHostGroupsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/host_groups';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
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

    public function testItListsHostGroupsAsAdminWithOnlyIdAndName(): void
    {
        $this->insertHostGroup('HostGroup-A');
        $this->insertHostGroup('HostGroup-B');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(HostGroupResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => 'HostGroup-A'],
                ['name' => 'HostGroup-B'],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
    }

    public function testItFiltersHostGroupsByNameUsingLikeOperator(): void
    {
        $this->insertHostGroup('Linux-Servers');
        $this->insertHostGroup('Other-B');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'Linux']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Linux-Servers'],
            ],
        ]);
    }

    public function testItPaginatesHostGroups(): void
    {
        $this->insertHostGroup('HostGroup-A');
        $this->insertHostGroup('HostGroup-B');
        $this->insertHostGroup('HostGroup-C');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRestrictsListingToAccessibleHostGroupsForARestrictedUser(): void
    {
        $this->insertHostGroup('HostGroup-A');
        $hostGroupBId = $this->insertHostGroup('HostGroup-B');

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostGroupReadTopologyRole($contactId);
        $this->restrictContactToHostGroups($contactId, [$hostGroupBId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'HostGroup-B'],
            ],
        ]);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->insertHostGroup('HostGroup-A');

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->insertHostGroup('HostGroup-A');

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => 'HostGroup-A']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $this->insertHostGroup('HostGroup-A');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
    }

    public function testItFiltersByANameOfLiteralZero(): void
    {
        $this->insertHostGroup('0');
        $this->insertHostGroup('HostGroup-A');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '0']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => '0'],
            ],
        ]);
    }

    private function insertHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::integer($contactId);

        return $contactId;
    }

    /**
     * Grants the "Configuration > Hosts > Host Groups" (read-only) topology access, the legacy menu
     * role bridged to HostGroupPermissionEnum::CanRead via
     * DbalCredentialTransformer::LEGACY_PERMISSION_MAP (topology_page 60102, parented by 601 "Hosts"
     * and 6 "Configuration" — see topology fixtures in www/install/insertTopology.sql).
     */
    private function grantHostGroupReadTopologyRole(int $contactId): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'topology-group-' . $contactId,
            'acl_group_alias' => 'topology-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'topology-rule-' . $contactId,
            'acl_topo_alias' => 'topology-rule-' . $contactId,
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach ([6, 601, 60102] as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            self::assertIsScalar($topologyId, "topology_page {$topologyPage} not found in fixtures");

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 2, // read-only
            ]);
        }
    }

    /**
     * @param list<int> $hostGroupIds
     */
    private function restrictContactToHostGroups(int $contactId, array $hostGroupIds): void
    {
        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'host-group-scope-' . $contactId,
            'acl_res_alias' => 'host-group-scope-' . $contactId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        /** @var int $aclGroupId */
        $aclGroupId = $this->connection->fetchOne(
            'SELECT acl_group_id FROM acl_group_contacts_relations WHERE contact_contact_id = :contactId',
            ['contactId' => $contactId]
        );

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($hostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', [
                'acl_res_id' => $aclResId,
                'hg_hg_id' => $hostGroupId,
            ]);
        }
    }
}
