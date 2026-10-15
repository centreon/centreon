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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostCategory;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListHostCategoriesChoicesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts/host_categories';

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

    public function testItListsHostCategoriesAsAdminWithOnlyIdAndName(): void
    {
        $this->insertHostCategory("cat-A-{$this->tag}");
        $this->insertHostCategory("cat-B-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame(["cat-A-{$this->tag}", "cat-B-{$this->tag}"], array_column($member, 'name'));
        self::assertSame([], array_diff(array_keys($member[0]), ['@id', '@type', 'id', 'name']));
    }

    public function testItExcludesSeveritiesFromListing(): void
    {
        $this->insertHostCategory("cat-{$this->tag}");
        $this->insertHostCategory("sev-{$this->tag}", level: 1);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["cat-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    /**
     * Same ACL-scoping behavior as the generic /configuration/host_categories endpoint (unlike
     * medias or host_severities, host categories stay ACL-scoped for the host form too).
     */
    public function testItRestrictsListingToAccessibleCategoriesForARestrictedUser(): void
    {
        $accessibleId = $this->insertHostCategory("acl-in-{$this->tag}");
        $this->insertHostCategory("acl-out-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);
        $this->restrictContactToHostCategories($contactId, [$accessibleId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["acl-in-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItFiltersHostCategoriesByNameUsingLikeOperator(): void
    {
        $this->insertHostCategory("match-{$this->tag}");
        $this->insertHostCategory("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["match-{$this->tag}"], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItPaginatesHostCategories(): void
    {
        $this->insertHostCategory("pg-{$this->tag}-A");
        $this->insertHostCategory("pg-{$this->tag}-B");
        $this->insertHostCategory("pg-{$this->tag}-C");

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

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "cat-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        // a host category with a level is a host "severity"; a levelless one is a regular category
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => $level]);

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

    /**
     * @param list<int> $hostCategoryIds host severities are stored in the same table as categories
     */
    private function restrictContactToHostCategories(int $contactId, array $hostCategoryIds): void
    {
        $this->connection->insert('acl_resources', [
            'acl_res_name' => "hc-scope-{$this->tag}",
            'acl_res_alias' => "hc-scope-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $aclGroupId = $this->connection->fetchOne(
            'SELECT acl_group_id FROM acl_group_contacts_relations WHERE contact_contact_id = :contactId',
            ['contactId' => $contactId]
        );
        Assert::notFalse($aclGroupId);
        Assert::scalar($aclGroupId);

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => (int) $aclGroupId,
        ]);

        foreach ($hostCategoryIds as $hostCategoryId) {
            $this->connection->insert('acl_resources_hc_relations', [
                'acl_res_id' => $aclResId,
                'hc_id' => $hostCategoryId,
            ]);
        }
    }
}
