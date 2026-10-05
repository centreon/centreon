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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostSeverity;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostSeverity\HostSeverityResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListHostSeveritiesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/host_severities';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_CATEGORIES_R
    // (Configuration > Hosts > Categories). Host severities share the host-categories legacy role,
    // so it is bridged to HostSeverityPermissionEnum::CanRead too via
    // DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_CATEGORY_READ_TOPOLOGY_PAGES = [6, 601, 60104];

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

    public function testItListsHostSeveritiesAsAdminWithOnlyIdAndName(): void
    {
        $this->insertHostSeverity("sev-A-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "sev-A-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(HostSeverityResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => "sev-A-{$this->tag}"],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
    }

    public function testItExcludesCategoriesFromListing(): void
    {
        $this->insertHostSeverity("sev-{$this->tag}");
        $this->insertHostSeverity("cat-{$this->tag}", level: null);

        $this->login();

        // both share the hostcategories table; only the level-bearing row is a host severity
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ["sev-{$this->tag}"],
            array_column((array) $response->toArray()['member'], 'name')
        );
    }

    public function testItFiltersHostSeveritiesByNameUsingLikeOperator(): void
    {
        $this->insertHostSeverity("match-{$this->tag}");
        $this->insertHostSeverity("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => "match-{$this->tag}"],
            ],
        ]);
    }

    public function testItPaginatesHostSeverities(): void
    {
        $this->insertHostSeverity("pg-{$this->tag}-A");
        $this->insertHostSeverity("pg-{$this->tag}-B");
        $this->insertHostSeverity("pg-{$this->tag}-C");

        $this->login();

        // scope to our own rows via the name filter so pre-seeded severities cannot skew the total
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRestrictsListingToAccessibleSeveritiesForARestrictedUser(): void
    {
        $accessibleId = $this->insertHostSeverity("acl-in-{$this->tag}");
        $this->insertHostSeverity("acl-out-{$this->tag}");
        // a category granted through the same resource must stay excluded (it is not a severity)
        $categoryId = $this->insertHostSeverity("acl-cat-{$this->tag}", level: null);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostCategoryReadTopologyRole($contactId);
        $this->restrictContactToHostCategories($contactId, [$accessibleId, $categoryId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        // only the accessible level-bearing severity is returned; the inaccessible severity, the
        // granted category, and any pre-seeded severities are excluded.
        self::assertSame(
            ["acl-in-{$this->tag}"],
            array_column((array) $response->toArray()['member'], 'name')
        );
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $this->insertHostSeverity("empty-{$this->tag}");

        $this->login();

        // an empty "like" value must be ignored (not applied, not rejected, not a 500 from the VO)
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
    }

    public function testItFiltersByANameOfLiteralZero(): void
    {
        $this->insertHostSeverity('0');

        $this->login();

        // "0" is a legitimate name; the criteria's Assert::stringNotEmpty must not reject it
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '0']]]);
        self::assertResponseIsSuccessful();
        self::assertContains('0', array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "sev-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertHostSeverity(string $name, ?int $level = 1): int
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

    private function grantHostCategoryReadTopologyRole(int $contactId): void
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

        foreach (self::HOST_CATEGORY_READ_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 2, // read-only
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
