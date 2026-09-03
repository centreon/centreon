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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\State\Pagination\Pagination;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\ListPollersProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\PollerCollectionOutputTransformer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListPollersProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/pollers';

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

    public function testItListsPollersAsAdminWithOnlyIdAndName(): void
    {
        $this->insertPoller('Poller-A');
        $this->insertPoller('Poller-B');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(PollerResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Poller-A'],
                ['name' => 'Poller-B'],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
    }

    public function testItFiltersPollersByNameUsingLikeOperator(): void
    {
        $this->insertPoller('Poller-A');
        $this->insertPoller('Other-B');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => 'Poller']]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Poller-A'],
            ],
        ]);
    }

    public function testItPaginatesPollers(): void
    {
        $this->insertPoller('Poller-A');
        $this->insertPoller('Poller-B');
        $this->insertPoller('Poller-C');

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['page' => '2', 'itemsPerPage' => '1']]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRestrictsListingToAccessiblePollersForARestrictedUser(): void
    {
        $this->insertPoller('Poller-A');
        $pollerBId = $this->insertPoller('Poller-B');

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantPollerReadTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$pollerBId]);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Poller-B'],
            ],
        ]);
    }

    public function testItExcludesCentralForNonAdminOnCloudPlatform(): void
    {
        $this->insertPoller('Central', isCentral: true);
        $this->insertPoller('Poller-A');

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantPollerReadTopologyRole($contactId);
        $this->forceCloudPlatform();

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Poller-A'],
            ],
        ]);
    }

    public function testItIncludesCentralForAdminOnCloudPlatform(): void
    {
        $this->insertPoller('Central', isCentral: true);
        $this->insertPoller('Poller-A');

        $this->forceCloudPlatform();
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => 'Central'],
                ['name' => 'Poller-A'],
            ],
        ]);
    }

    private function insertPoller(string $name, bool $isCentral = false): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'localhost' => $isCentral ? '1' : '0',
            'ns_activate' => '1',
            'ns_ip_address' => '10.0.1.' . random_int(1, 254),
            'uid' => random_int(300000000000000, 399999999999999),
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
        Assert::integer($contactId);

        return $contactId;
    }

    /**
     * Grants the "Configuration > Pollers > Pollers" (read-only) topology access, the legacy menu
     * role bridged to PollerPermissionEnum::CanRead via DbalCredentialTransformer::LEGACY_PERMISSION_MAP
     * (topology_page 60901, parented by 609 "Pollers" and 6 "Configuration" — see topology fixtures
     * in www/install/insertTopology.sql).
     */
    private function grantPollerReadTopologyRole(int $contactId): void
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

        foreach ([6, 609, 60901] as $topologyPage) {
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
     * @param list<int> $pollerIds
     */
    private function restrictContactToPollers(int $contactId, array $pollerIds): void
    {
        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'poller-scope-' . $contactId,
            'acl_res_alias' => 'poller-scope-' . $contactId,
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

        foreach ($pollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }
    }

    /**
     * `isCloudPlatform` is a constructor argument autowired from the IS_CLOUD_PLATFORM env var, so the
     * platform is forced by replacing the Provider itself — same technique as
     * PollerCreatedBrokerConfigurationTest::forceCloudPlatform(). Must run before the request is made.
     */
    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();

        /** @var PollerCollectionOutputTransformer $transformer */
        $transformer = $container->get(PollerCollectionOutputTransformer::class);
        /** @var PollerRepository $repository */
        $repository = $container->get(PollerRepository::class);
        /** @var Pagination $pagination */
        $pagination = $container->get(Pagination::class);
        /** @var Security $security */
        $security = $container->get(Security::class);

        $container->set(
            ListPollersProvider::class,
            new ListPollersProvider($transformer, $repository, $pagination, $security, true),
        );
    }
}
