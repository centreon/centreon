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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * Wiring of the service form selectors (/configuration/services/*): the gate, the metadata, the routing
 * and one end-to-end listing per selector. The behavior of each reused provider (filters, sort, scoping)
 * is pinned by its own sibling test under State/ and is not repeated here.
 */
final class ServiceFormSelectorsWiringTest extends ApiTestCase
{
    private const SELECTORS = [
        'commands' => [
            'uri' => '/api/configuration/services/commands',
            'message' => 'You are not allowed to access commands',
            'item_iri' => '/api/configuration/commands/',
            'keys' => ['id', 'name'],
        ],
        'timeperiods' => [
            'uri' => '/api/configuration/services/timeperiods',
            'message' => 'You are not allowed to access time periods',
            'item_iri' => '/api/configuration/timeperiods/',
            'keys' => ['id', 'name'],
        ],
        'contacts' => [
            'uri' => '/api/configuration/services/contacts',
            'message' => 'You are not allowed to access contacts',
            'item_iri' => '/api/configuration/contacts/',
            'keys' => ['id', 'name'],
        ],
        'contact_groups' => [
            'uri' => '/api/configuration/services/contact_groups',
            'message' => 'You are not allowed to access contact groups',
            'item_iri' => '/api/configuration/contact_groups/',
            'keys' => ['id', 'name'],
        ],
        'host_groups' => [
            'uri' => '/api/configuration/services/host_groups',
            'message' => 'You are not allowed to access host groups',
            'item_iri' => '/api/configuration/host_groups/',
            'keys' => ['id', 'name'],
        ],
        'medias' => [
            'uri' => '/api/configuration/services/medias',
            'message' => 'You are not allowed to access medias',
            'item_iri' => '/api/configuration/medias/',
            'keys' => ['id', 'name', 'url'],
        ],
    ];

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_SERVICES_SERVICES_BY_HOST_R[W]
    // (Configuration > Services > Services by host), bridged to ServicePermissionEnum via
    // CredentialTransformer::LEGACY_PERMISSION_MAP.
    private const SERVICE_TOPOLOGY_PAGES = [6, 602, 60201];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;

    private Connection $connection;

    private string $tag;

    /** @var array{mixed, mixed}|null */
    private ?array $previousCloudPlatformEnv = null;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    protected function tearDown(): void
    {
        if ($this->previousCloudPlatformEnv !== null) {
            [$previousEnv, $previousServer] = $this->previousCloudPlatformEnv;
            if ($previousEnv === null) {
                unset($_ENV['IS_CLOUD_PLATFORM']);
            } else {
                $_ENV['IS_CLOUD_PLATFORM'] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER['IS_CLOUD_PLATFORM']);
            } else {
                $_SERVER['IS_CLOUD_PLATFORM'] = $previousServer;
            }
            $this->previousCloudPlatformEnv = null;
        }

        parent::tearDown();
    }

    /**
     * The service form fills a service, so every selector demands read-write on "Services by host":
     * a read-only grant reaches ServicePermissionEnum::CanRead only, which no selector accepts.
     */
    #[DataProvider('provideSelectors')]
    public function testEachSelectorIsForbiddenToAReadOnlyUser(string $selector): void
    {
        $username = bin2hex(random_bytes(8));
        $this->grantServiceTopologyRole($this->createNonAdminContact($username), self::ACL_ACCESS_READ_ONLY);
        $this->login($username);

        $response = $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseStatusCodeSame(403);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertIsInt($body['code']);
        self::assertSame(self::SELECTORS[$selector]['message'], $body['message']);
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::SELECTORS['commands']['uri']);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('provideSelectors')]
    public function testSelectorsAreAbsentFromTheOpenApiDoc(string $selector): void
    {
        $uri = self::SELECTORS[$selector]['uri'];

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $routedPaths = array_map(
            static fn (Route $route): string => $route->getPath(),
            $router->getRouteCollection()->all()
        );
        self::assertContains($uri, $routedPaths, 'the selector must be routed');

        /** @var OpenApiFactoryInterface $openApiFactory */
        $openApiFactory = self::getContainer()->get('api_platform.openapi.factory');
        $paths = $openApiFactory()->getPaths();
        // guard: the documented paths carry the /api prefix, so an absence below is meaningful
        self::assertNotNull($paths->getPath('/api/configuration/commands'));
        self::assertNull($paths->getPath($uri));
    }

    public function testSelectorsAreNotAliasedUnderApiLatest(): void
    {
        $this->login();

        $this->request('GET', self::SELECTORS['commands']['uri']);
        self::assertResponseIsSuccessful();

        $this->request('GET', str_replace('/api/', '/api/latest/', self::SELECTORS['commands']['uri']));
        self::assertResponseStatusCodeSame(404);
    }

    public function testItAnswersAPlainArrayInJson(): void
    {
        $name = "json-{$this->tag}";
        $commandId = $this->insertCommand($name);
        $this->login();

        $response = $this->request('GET', self::SELECTORS['commands']['uri'], [
            'headers' => ['Accept' => 'application/json'],
            'query' => ['name' => ['lk' => $name]],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([['id' => $commandId, 'name' => $name]], $response->toArray());
    }

    public function testAScalarNameFilterIsABadRequestWithTheErrorBody(): void
    {
        $this->login();

        $response = $this->request('GET', self::SELECTORS['commands']['uri'], ['query' => ['name' => 'foo']]);

        self::assertResponseStatusCodeSame(400);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertIsInt($body['code']);
        self::assertSame('The "name" filter must use the "name[eq|lk]=value" format.', $body['message']);
    }

    /**
     * Known defect, kept on purpose: API Platform rejects "page < 1" before the provider runs, with an
     * exception the local exception_to_status does not map, so it ends as HTTP 500. A global fix must update
     * this test on purpose.
     */
    public function testAPageBelowOneIsAnUnhandledServerError(): void
    {
        $this->login();

        $response = $this->request('GET', self::SELECTORS['commands']['uri'], ['query' => ['page' => '0']]);

        self::assertResponseStatusCodeSame(500);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertSame('Page should not be less than 1', $body['message']);
    }

    /**
     * The cloud service form has no contact nor contact group field, so these selectors are on-premise only.
     * The route condition is evaluated before the firewall, so the route answers 404 to any caller on cloud.
     */
    #[DataProvider('provideOnPremiseOnlySelectorsAndCallers')]
    public function testOnPremiseOnlySelectorsDoNotExistOnCloud(string $selector, bool $authenticated, int $onPremiseStatus): void
    {
        $uri = self::SELECTORS[$selector]['uri'];
        if ($authenticated) {
            $this->login();
        }

        $this->request('GET', $uri);
        self::assertResponseStatusCodeSame($onPremiseStatus, 'the selector must exist on-premise');

        $this->forceCloudPlatform();
        if ($authenticated) {
            $this->login();
        }

        $this->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Only contacts and contact groups are on-premise only. The cloud service form still needs the other
     * selectors (time periods for the check period), so they stay routed on cloud.
     */
    #[DataProvider('provideSelectorsRoutedOnCloud')]
    public function testOtherSelectorsStayRoutedOnCloud(string $selector): void
    {
        $this->forceCloudPlatform();
        $username = bin2hex(random_bytes(8));
        $this->grantServiceTopologyRole($this->createNonAdminContact($username), self::ACL_ACCESS_READ_WRITE);
        $this->login($username);

        $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseIsSuccessful();
    }

    /**
     * A service read-write user needs no role of the listed resource itself. Row-level ACL scoping of
     * contacts, contact groups and host groups still applies, so the fixture is granted to the user.
     */
    #[DataProvider('provideSelectors')]
    public function testEachReusedSelectorListsForAServiceReadWriteUser(string $selector): void
    {
        $name = "svc-{$selector}-{$this->tag}";
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantServiceTopologyRole($contactId, self::ACL_ACCESS_READ_WRITE);
        $fixtureId = $this->insertVisibleFixture($selector, $name, $aclGroupId);
        $this->login($username);

        $response = $this->request('GET', self::SELECTORS[$selector]['uri'], ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertCount(1, $member);
        self::assertEqualsCanonicalizing(['@id', '@type', ...self::SELECTORS[$selector]['keys']], array_keys($member[0]));
        self::assertSame(self::SELECTORS[$selector]['item_iri'] . $fixtureId, $member[0]['@id']);
        self::assertSame($fixtureId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSelectors(): iterable
    {
        foreach (array_keys(self::SELECTORS) as $selector) {
            yield $selector => [$selector];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSelectorsRoutedOnCloud(): iterable
    {
        foreach (['commands', 'timeperiods', 'host_groups', 'medias'] as $selector) {
            yield $selector => [$selector];
        }
    }

    /**
     * @return iterable<string, array{string, bool, int}>
     */
    public static function provideOnPremiseOnlySelectorsAndCallers(): iterable
    {
        foreach (['contacts', 'contact_groups'] as $selector) {
            yield "{$selector}, authenticated" => [$selector, true, 200];

            yield "{$selector}, anonymous" => [$selector, false, 401];
        }
    }

    private function insertVisibleFixture(string $selector, string $name, int $aclGroupId): int
    {
        return match ($selector) {
            'commands' => $this->insertCommand($name),
            'timeperiods' => $this->insertRow('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]),
            'contacts' => $this->insertContactInAccessGroup($name, $aclGroupId),
            'contact_groups' => $this->insertContactGroupInAccessGroup($name, $aclGroupId),
            'host_groups' => $this->insertHostGroupInAccessGroup($name, $aclGroupId),
            'medias' => $this->insertMedia($name),
            default => throw new \LogicException("No fixture for {$selector}"),
        };
    }

    private function insertCommand(string $name): int
    {
        return $this->insertRow('command', [
            'command_name' => $name,
            'command_line' => '$USER1$/check_dummy',
            'command_type' => 2,
            'enable_shell' => '0',
            'command_activate' => '1',
            'command_locked' => '0',
        ]);
    }

    private function insertContactInAccessGroup(string $name, int $aclGroupId): int
    {
        $contactId = $this->insertRow('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        return $contactId;
    }

    private function insertContactGroupInAccessGroup(string $name, int $aclGroupId): int
    {
        $contactGroupId = $this->insertRow('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);
        $this->connection->insert('acl_group_contactgroups_relations', [
            'acl_group_id' => $aclGroupId,
            'cg_cg_id' => $contactGroupId,
        ]);

        return $contactGroupId;
    }

    private function insertHostGroupInAccessGroup(string $name, int $aclGroupId): int
    {
        $hostGroupId = $this->insertRow('hostgroup', ['hg_name' => $name]);
        $aclResourceId = $this->insertRow('acl_resources', [
            'acl_res_name' => "hg-scope-{$this->tag}",
            'acl_res_alias' => "hg-scope-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResourceId,
            'acl_group_id' => $aclGroupId,
        ]);
        $this->connection->insert('acl_resources_hg_relations', [
            'acl_res_id' => $aclResourceId,
            'hg_hg_id' => $hostGroupId,
        ]);

        return $hostGroupId;
    }

    private function insertMedia(string $name): int
    {
        $directoryId = $this->insertRow('view_img_dir', ['dir_name' => "dir-{$this->tag}"]);
        $imageId = $this->insertRow('view_img', ['img_name' => $name, 'img_path' => $name]);
        $this->connection->insert('view_img_dir_relation', [
            'dir_dir_parent_id' => $directoryId,
            'img_img_id' => $imageId,
        ]);

        return $imageId;
    }

    /**
     * @param array<string, int|string> $row
     */
    private function insertRow(string $table, array $row): int
    {
        $this->connection->insert($table, $row);

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
     * Grants "Configuration > Services > Services by host" through real ACL menu rows and returns the
     * access group id (reused for row-level scoping).
     *
     * @param int $accessRight CentreonACL::ACL_ACCESS_READ_WRITE (1) or ACL_ACCESS_READ_ONLY (2)
     */
    private function grantServiceTopologyRole(int $contactId, int $accessRight): int
    {
        $aclGroupId = $this->insertRow('acl_groups', [
            'acl_group_name' => "topology-group-{$this->tag}",
            'acl_group_alias' => "topology-group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $aclTopologyId = $this->insertRow('acl_topology', [
            'acl_topo_name' => "topology-rule-{$this->tag}",
            'acl_topo_alias' => "topology-rule-{$this->tag}",
            'acl_topo_activate' => '1',
        ]);
        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopologyId,
        ]);

        foreach (self::SERVICE_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopologyId,
                'access_right' => $accessRight,
            ]);
        }

        return $aclGroupId;
    }

    /**
     * Same as ResolveHostAddressProviderTest::forceCloudPlatform(). Must run before the request is made.
     */
    private function forceCloudPlatform(): void
    {
        $this->previousCloudPlatformEnv = [
            $_ENV['IS_CLOUD_PLATFORM'] ?? null,
            $_SERVER['IS_CLOUD_PLATFORM'] ?? null,
        ];
        $_ENV['IS_CLOUD_PLATFORM'] = $_SERVER['IS_CLOUD_PLATFORM'] = '1';

        // the client shares static::$kernel: rebooting it gives both a container with no env cached
        $kernel = self::$kernel;
        Assert::notNull($kernel);
        $kernel->shutdown();
        $kernel->boot();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }
}
