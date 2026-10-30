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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListHostTemplatesProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/host_templates';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_TEMPLATES_R
    // (Configuration > Hosts > Templates), bridged to HostTemplatePermissionEnum::CanRead
    // via CredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_TEMPLATE_READ_TOPOLOGY_PAGES = [6, 601, 60103];

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

    public function testItListsHostTemplatesAsAdminWithTheListingFields(): void
    {
        $this->insertHostTemplate("tpl-A-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "tpl-A-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(HostTemplateResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => "tpl-A-{$this->tag}"],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        // the template has no alias and no icon: like any null, they are omitted from the member
        self::assertEqualsCanonicalizing(
            ['@id', '@type', 'id', 'name', 'is_locked', 'linked_service_templates_count', 'parent_templates'],
            array_keys($member[0])
        );
        self::assertSame([], $member[0]['parent_templates']);
    }

    public function testItFiltersHostTemplatesByNameUsingLikeOperator(): void
    {
        $this->insertHostTemplate("match-{$this->tag}");
        $this->insertHostTemplate("other-{$this->tag}");

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

    public function testItPaginatesHostTemplates(): void
    {
        $this->insertHostTemplate("pg-{$this->tag}-A");
        $this->insertHostTemplate("pg-{$this->tag}-B");
        $this->insertHostTemplate("pg-{$this->tag}-C");

        $this->login();

        // scope to our own rows via the name filter so pre-seeded templates cannot skew the total
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItDoesNotScopeTheListingToTheAccessibleSeveritiesOfARestrictedUser(): void
    {
        // the listing is gated by the page permission only: a user restricted to some host
        // severities still sees every host template, linked to one of them or not
        $severityId = $this->insertHostCategory("sev-{$this->tag}", level: 1);
        $regularId = $this->insertHostCategory("reg-{$this->tag}");

        $inSeverity = $this->insertHostTemplate("acl-severity-{$this->tag}");
        $this->linkTemplateToCategory($inSeverity, $severityId);
        $inRegular = $this->insertHostTemplate("acl-regular-{$this->tag}");
        $this->linkTemplateToCategory($inRegular, $regularId);
        $this->insertHostTemplate("acl-none-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostTemplateReadTopologyRole($contactId);
        $this->restrictContactToHostCategories($contactId, [$severityId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(
            ["acl-severity-{$this->tag}", "acl-regular-{$this->tag}", "acl-none-{$this->tag}"],
            array_column((array) $response->toArray()['member'], 'name')
        );
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $this->insertHostTemplate("empty-{$this->tag}");

        $this->login();

        // an empty "like" value must be ignored (not applied, not rejected, not a 500 from the VO)
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
    }

    public function testItFiltersByANameOfLiteralZero(): void
    {
        $this->insertHostTemplate('0');

        $this->login();

        // "0" is a legitimate name; the criteria's Assert::stringNotEmpty must not reject it
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '0']]]);
        self::assertResponseIsSuccessful();
        self::assertContains('0', array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "tpl-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItMatchesTheAliasAsWellWithTheNameFilter(): void
    {
        $this->insertHostTemplate("ht3-{$this->tag}", alias: "ht1-{$this->tag}-alias");
        $this->insertHostTemplate("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "ht1-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(["ht3-{$this->tag}"], $this->memberNames($response->toArray()));
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, bool>}>
     */
    public static function provideLockedFilters(): iterable
    {
        yield 'absent: unlocked only' => [[], ['unlocked' => false]];

        yield 'false: unlocked only' => [['is_locked' => 'false'], ['unlocked' => false]];

        // a NULL lock state is hidden by default like a locked one (legacy "host_locked = 0"), yet
        // reported as not locked once shown, like legacy, whose locked set is "host_locked = 1"
        // (CentreonHost::getLockedHostTemplates())
        yield 'true: locked ones as well' => [['is_locked' => 'true'], ['locked' => true, 'null' => false, 'unlocked' => false]];
    }

    /**
     * @param array<string, string> $lockedFilter
     * @param array<string, bool> $expectedLockStates the expected is_locked by name suffix, in name order
     */
    #[DataProvider('provideLockedFilters')]
    public function testItListsTheLockedTemplatesOnlyWhenAsked(array $lockedFilter, array $expectedLockStates): void
    {
        $this->insertHostTemplate("lock-{$this->tag}-unlocked");
        $this->insertHostTemplate("lock-{$this->tag}-locked", locked: true);
        $this->insertHostTemplate("lock-{$this->tag}-null", locked: null);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "lock-{$this->tag}-"]] + $lockedFilter,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{name: string, is_locked: bool}> $members */
        $members = $response->toArray()['member'];
        $expected = [];
        foreach ($expectedLockStates as $suffix => $isLocked) {
            $expected["lock-{$this->tag}-{$suffix}"] = $isLocked;
        }
        self::assertSame($expected, array_column($members, 'is_locked', 'name'));
    }

    public function testItRejectsALockedFilterThatIsNotABoolean(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['is_locked' => 'maybe']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItSortsByNameAscendingByDefault(): void
    {
        $this->insertHostTemplate("sort-{$this->tag}-b");
        $this->insertHostTemplate("sort-{$this->tag}-c");
        $this->insertHostTemplate("sort-{$this->tag}-a");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "sort-{$this->tag}-"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ["sort-{$this->tag}-a", "sort-{$this->tag}-b", "sort-{$this->tag}-c"],
            $this->memberNames($response->toArray())
        );
    }

    public function testItSortsByTheRequestedField(): void
    {
        $this->insertHostTemplate("sort-{$this->tag}-1", alias: 'a');
        $this->insertHostTemplate("sort-{$this->tag}-2", alias: 'c');
        $this->insertHostTemplate("sort-{$this->tag}-3", alias: 'b');

        $this->login();

        $byAlias = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "sort-{$this->tag}-"], 'sort' => ['alias' => 'desc']],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(["sort-{$this->tag}-2", "sort-{$this->tag}-3", "sort-{$this->tag}-1"], $this->memberNames($byAlias->toArray()));

        $byName = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "sort-{$this->tag}-"], 'sort' => ['name' => 'desc']],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(["sort-{$this->tag}-3", "sort-{$this->tag}-2", "sort-{$this->tag}-1"], $this->memberNames($byName->toArray()));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidSorts(): iterable
    {
        yield 'unknown field' => [['sort' => ['address' => 'asc']]];

        yield 'invalid direction' => [['sort' => ['name' => 'up']]];

        yield 'scalar sort' => [['sort' => 'name']];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideInvalidSorts')]
    public function testItRejectsAnInvalidSort(array $query): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => $query]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturnsThePaginationEnvelope(): void
    {
        $this->insertHostTemplate("env-{$this->tag}-a");
        $this->insertHostTemplate("env-{$this->tag}-b");
        $this->insertHostTemplate("env-{$this->tag}-c");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "env-{$this->tag}-"], 'page' => '2', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame(3, $body['totalItems']);
        self::assertSame(["env-{$this->tag}-c"], $this->memberNames($body));
        self::assertIsArray($body['view']);
        self::assertArrayHasKey('previous', $body['view']);
        self::assertArrayNotHasKey('next', $body['view']);
    }

    public function testItReturnsAnEmptyPageAfterTheLastOne(): void
    {
        $this->insertHostTemplate("past-{$this->tag}-a");
        $this->insertHostTemplate("past-{$this->tag}-b");
        $this->insertHostTemplate("past-{$this->tag}-c");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "past-{$this->tag}-"], 'page' => '5', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame([], $body['member']);
        self::assertSame(3, $body['totalItems']);
    }

    public function testItReturnsAnEmptyCollection(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "nothing-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame([], $body['member']);
        self::assertSame(0, $body['totalItems']);
    }

    public function testItListsTheCountTheParentsAndTheIconOfEachTemplate(): void
    {
        $ownIconId = $this->insertImage("own-{$this->tag}.png", 'own-dir');
        $inheritedIconId = $this->insertImage("inherited-{$this->tag}.png", 'inherited-dir');

        $firstParentId = $this->insertHostTemplate("parent-a-{$this->tag}");
        $this->setIcon($firstParentId, $inheritedIconId);
        $secondParentId = $this->insertHostTemplate("parent-b-{$this->tag}");

        $ownIconTemplateId = $this->insertHostTemplate("full-1-{$this->tag}", alias: 'Full');
        $this->setIcon($ownIconTemplateId, $ownIconId);
        // inserted out of order on purpose: the relation `order` gives the parents' order
        $this->linkTemplateToParent($ownIconTemplateId, $firstParentId, 2);
        $this->linkTemplateToParent($ownIconTemplateId, $secondParentId, 1);
        $this->linkServiceTemplate($ownIconTemplateId);
        $this->linkServiceTemplate($ownIconTemplateId);

        $inheritingTemplateId = $this->insertHostTemplate("full-2-{$this->tag}");
        $this->linkTemplateToParent($inheritingTemplateId, $secondParentId, 1);
        $this->linkTemplateToParent($inheritingTemplateId, $firstParentId, 2);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();

        $members = [];
        /** @var array{member: list<array<string, mixed>>} $body */
        $body = $response->toArray();
        foreach ($body['member'] as $member) {
            $members[$member['id']] = $member;
        }

        $parents = [
            ['id' => $secondParentId, 'name' => "parent-b-{$this->tag}"],
            ['id' => $firstParentId, 'name' => "parent-a-{$this->tag}"],
        ];
        self::assertSame('Full', $members[$ownIconTemplateId]['alias']);
        self::assertFalse($members[$ownIconTemplateId]['is_locked']);
        self::assertSame(2, $members[$ownIconTemplateId]['linked_service_templates_count']);
        self::assertSame($parents, $this->withoutJsonLdKeys($members[$ownIconTemplateId]['parent_templates']));
        self::assertSame(
            ['id' => $ownIconId, 'name' => "own-{$this->tag}.png", 'url' => "/img/media/own-dir/own-{$this->tag}.png"],
            $this->withoutJsonLdKeys($members[$ownIconTemplateId]['icon'])
        );

        // no own icon: the first one found up the parents, in relation order, depth first
        self::assertSame(0, $members[$inheritingTemplateId]['linked_service_templates_count']);
        self::assertSame($parents, $this->withoutJsonLdKeys($members[$inheritingTemplateId]['parent_templates']));
        self::assertSame(
            ['id' => $inheritedIconId, 'name' => "inherited-{$this->tag}.png", 'url' => "/img/media/inherited-dir/inherited-{$this->tag}.png"],
            $this->withoutJsonLdKeys($members[$inheritingTemplateId]['icon'])
        );
    }

    public function testItOmitsTheIconOfATemplateWithoutAnyIcon(): void
    {
        $parentId = $this->insertHostTemplate("iconless-parent-{$this->tag}");
        $templateId = $this->insertHostTemplate("iconless-{$this->tag}");
        $this->linkTemplateToParent($templateId, $parentId, 1);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "iconless-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame($templateId, $member[0]['id']);
        self::assertArrayNotHasKey('icon', $member[0]);
        self::assertArrayNotHasKey('alias', $member[0]);
    }

    /**
     * Drops the JSON-LD keys API Platform adds to every nested object (a @var and a random blank
     * node @id, /api/.well-known/genid/...): they are not part of the data contract.
     */
    private function withoutJsonLdKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $data = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || ! str_starts_with($key, '@')) {
                $data[$key] = $this->withoutJsonLdKeys($item);
            }
        }

        return $data;
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<string>
     */
    private function memberNames(array $body): array
    {
        /** @var list<array{name: string}> $member */
        $member = $body['member'];

        return array_column($member, 'name');
    }

    private function insertImage(string $name, string $folder): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imageId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir', ['dir_name' => $folder, 'dir_alias' => $folder]);
        $this->connection->insert('view_img_dir_relation', [
            'dir_dir_parent_id' => (int) $this->connection->lastInsertId(),
            'img_img_id' => $imageId,
        ]);

        return $imageId;
    }

    private function setIcon(int $hostTemplateId, int $imageId): void
    {
        $this->connection->insert('extended_host_information', ['host_host_id' => $hostTemplateId, 'ehi_icon_image' => $imageId]);
    }

    private function linkTemplateToParent(int $hostTemplateId, int $parentId, int $order): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostTemplateId,
            'host_tpl_id' => $parentId,
            '`order`' => $order,
        ]);
    }

    private function linkServiceTemplate(int $hostTemplateId): void
    {
        $this->connection->insert('service', [
            'service_description' => 'svc-' . Uuid::v4()->toBase58(),
            'service_register' => '0',
        ]);
        $this->connection->insert('host_service_relation', [
            'host_host_id' => $hostTemplateId,
            'service_service_id' => (int) $this->connection->lastInsertId(),
        ]);
    }

    /**
     * @param ?bool $locked null stores a NULL host_locked
     */
    private function insertHostTemplate(string $name, ?string $alias = null, ?bool $locked = false): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_alias' => $alias,
            'host_locked' => $locked === null ? null : (int) $locked,
            'host_register' => '0',
            'host_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostCategory(string $name, ?int $level = null): int
    {
        // a host category with a level is a host "severity"; a levelless one is a regular category
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkTemplateToCategory(int $hostTemplateId, int $hostCategoryId): void
    {
        $this->connection->insert('hostcategories_relation', [
            'hostcategories_hc_id' => $hostCategoryId,
            'host_host_id' => $hostTemplateId,
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

    private function grantHostTemplateReadTopologyRole(int $contactId): void
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

        foreach (self::HOST_TEMPLATE_READ_TOPOLOGY_PAGES as $topologyPage) {
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
     * @param list<int> $hostCategoryIds
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
