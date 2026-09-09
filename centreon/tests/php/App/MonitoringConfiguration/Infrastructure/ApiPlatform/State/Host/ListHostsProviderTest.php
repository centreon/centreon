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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\Security\Domain\AdminResolver;
use App\Security\Domain\Repository\AccessGroupRepository;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListHostsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
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

    public function testItListsHostsAsAdminWithPollerAndTemplates(): void
    {
        $pollerId = $this->insertPoller('Central');
        $templateId = $this->insertHostTemplate('generic-active-host');
        $hostId = $this->insertHost('server-01', $pollerId, alias: 'srv01');
        $this->linkHostToTemplate($hostId, $templateId);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(HostResource::class);
        self::assertJsonContains([
            'member' => [
                [
                    'id' => $hostId,
                    'name' => 'server-01',
                    'alias' => 'srv01',
                    'address' => '127.0.0.1',
                    'poller' => ['id' => $pollerId, 'name' => 'Central'],
                    'templates' => [
                        ['id' => $templateId, 'name' => 'generic-active-host'],
                    ],
                    'activated' => true,
                ],
            ],
        ]);
    }

    public function testItOmitsTheAliasKeyWhenTheHostHasNoAlias(): void
    {
        // ApiPlatform's skip_null_values defaults to true and drops a null field from the
        // response entirely rather than serializing it as null — the accepted platform-wide
        // convention here, confirmed for this endpoint rather than overridden per-resource.
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('server-02', $pollerId, alias: '');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertArrayNotHasKey('alias', $member[0]);
    }

    public function testItFiltersHostsByNameUsingLikeOperator(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('web-frontend', $pollerId);
        $this->insertHost('database-backend', $pollerId);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'front']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(['member' => [['name' => 'web-frontend']]]);
    }

    public function testItFiltersHostsByActivatedStatus(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('active-host', $pollerId, activated: true);
        $this->insertHost('inactive-host', $pollerId, activated: false);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['activated' => 'false']]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(['member' => [['name' => 'inactive-host']]]);
    }

    public function testItPaginatesHosts(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('host-1', $pollerId);
        $this->insertHost('host-2', $pollerId);
        $this->insertHost('host-3', $pollerId);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRestrictsListingToAccessibleHostsForARestrictedUser(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleId = $this->insertHost('accessible-host', $pollerId);
        $this->insertHost('restricted-host', $pollerId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadTopologyRole($contactId);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $accessibleId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(['member' => [['name' => 'accessible-host']]]);
    }

    public function testItIncludesAllHostsForCustomerAdminAclMemberOnCloudPlatform(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleId = $this->insertHost('accessible-host', $pollerId);
        $this->insertHost('other-host', $pollerId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadTopologyRole($contactId);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $accessibleId]);
        $this->addContactToCustomerAdminAclGroup($contactId);
        $this->forceCloudPlatform();

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
    }

    public function testItRestrictsCustomerAdminAclMemberOnPremPlatform(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleId = $this->insertHost('accessible-host', $pollerId);
        $this->insertHost('other-host', $pollerId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadTopologyRole($contactId);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $accessibleId]);
        $this->addContactToCustomerAdminAclGroup($contactId);
        $this->forceOnPremPlatform();

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains(['member' => [['name' => 'accessible-host']]]);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('any-host', $pollerId);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('any-host', $pollerId);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => 'any-host']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsANonNumericTemplateIdFilter(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('any-host', $pollerId);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['template_id' => 'not-a-number']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsANonBooleanActivatedFilter(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->insertHost('any-host', $pollerId);

        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['activated' => 'not-a-boolean']]);
        self::assertResponseStatusCodeSame(400);
    }

    private function insertPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, int $pollerId, ?string $alias = null, bool $activated = true): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_alias' => $alias,
            'host_address' => '127.0.0.1',
            'host_activate' => $activated ? '1' : '0',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function linkHostToTemplate(int $hostId, int $templateId): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
        ]);
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
     * Grants the "Configuration > Hosts > Hosts" (read-only) topology access, the legacy menu
     * role bridged to HostPermissionEnum::CanRead via
     * DbalCredentialTransformer::LEGACY_PERMISSION_MAP (topology_page 60101, parented by 601 "Hosts"
     * and 6 "Configuration" — see topology fixtures in www/install/insertTopology.sql).
     *
     * @return int the Access Group id the contact was granted, reusable to scope centreon_acl rows
     */
    private function grantHostReadTopologyRole(int $contactId): int
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

        foreach ([6, 601, 60101] as $topologyPage) {
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

        return $aclGroupId;
    }

    private function addContactToCustomerAdminAclGroup(int $contactId): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'customer_admin_acl',
            'acl_group_alias' => 'customer_admin_acl',
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);
    }

    /**
     * AdminResolver::$isCloudPlatform is bound from the IS_CLOUD_PLATFORM env var
     * (config.new/services/security.php), so the platform is forced here by replacing the
     * container's AdminResolver instance, same technique as
     * ListPollersProviderTest::forceCloudPlatform(). Must run before the request is made.
     */
    private function forceCloudPlatform(): void
    {
        $this->forcePlatform(isCloudPlatform: true);
    }

    /**
     * Pins the on-premises platform explicitly rather than relying on the ambient
     * IS_CLOUD_PLATFORM default: an external test environment could set it to true, which would
     * silently turn this into a duplicate of testItIncludesAllHostsForCustomerAdminAclMemberOnCloudPlatform.
     */
    private function forceOnPremPlatform(): void
    {
        $this->forcePlatform(isCloudPlatform: false);
    }

    private function forcePlatform(bool $isCloudPlatform): void
    {
        $container = self::getContainer();

        /** @var AccessGroupRepository $accessGroupRepository */
        $accessGroupRepository = $container->get(AccessGroupRepository::class);

        $container->set(AdminResolver::class, new AdminResolver($accessGroupRepository, $isCloudPlatform));
    }
}
