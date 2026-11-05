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
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsHostTemplateTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * Wiring of the host template form selectors (/configuration/host_templates/<list>): the gate, the
 * metadata, the routing and one end-to-end listing per selector. Each provider's behavior (filters,
 * sort, scoping) is pinned by its own tests under State/ and is not repeated here.
 */
final class HostTemplateFormSelectorsWiringTest extends ApiTestCase
{
    use GrantsHostTemplateTopologyRole;
    use InsertsMonitoringConnectorTemplates;

    /**
     * counterpart: the same list for another form, gated on a right the host-template-only user lacks.
     */
    private const SELECTORS = [
        'commands' => [
            'uri' => '/api/configuration/host_templates/commands',
            'counterpart' => '/api/configuration/hosts/commands',
            'message' => 'You are not allowed to access commands',
            'item_iri' => '/api/configuration/commands/',
            'keys' => ['id', 'name'],
        ],
        'timeperiods' => [
            'uri' => '/api/configuration/host_templates/timeperiods',
            'counterpart' => '/api/configuration/hosts/timeperiods',
            'message' => 'You are not allowed to access time periods',
            'item_iri' => '/api/configuration/timeperiods/',
            'keys' => ['id', 'name'],
        ],
        'contacts' => [
            'uri' => '/api/configuration/host_templates/contacts',
            'counterpart' => '/api/configuration/hosts/contacts',
            'message' => 'You are not allowed to access contacts',
            'item_iri' => '/api/configuration/contacts/',
            'keys' => ['id', 'name'],
        ],
        'contact_groups' => [
            'uri' => '/api/configuration/host_templates/contact_groups',
            'counterpart' => '/api/configuration/hosts/contact_groups',
            'message' => 'You are not allowed to access contact groups',
            'item_iri' => '/api/configuration/contact_groups/',
            'keys' => ['id', 'name'],
        ],
        'host_categories' => [
            'uri' => '/api/configuration/host_templates/host_categories',
            'counterpart' => '/api/configuration/hosts/host_categories',
            'message' => 'You are not allowed to access host categories',
            'item_iri' => '/api/configuration/host_categories/',
            'keys' => ['id', 'name'],
        ],
        'host_severities' => [
            'uri' => '/api/configuration/host_templates/host_severities',
            'counterpart' => '/api/configuration/hosts/host_severities',
            'message' => 'You are not allowed to access host severities',
            'item_iri' => '/api/configuration/host_severities/',
            'keys' => ['id', 'name'],
        ],
        'host_templates' => [
            'uri' => '/api/configuration/host_templates/host_templates',
            'counterpart' => '/api/configuration/hosts/host_templates',
            'message' => 'You are not allowed to access host templates',
            'item_iri' => '/api/configuration/host_templates/',
            'keys' => ['id', 'name'],
        ],
        'medias' => [
            'uri' => '/api/configuration/host_templates/medias',
            'counterpart' => '/api/configuration/hosts/medias',
            'message' => 'You are not allowed to access medias',
            'item_iri' => '/api/configuration/medias/',
            'keys' => ['id', 'name', 'url'],
        ],
        'service_templates' => [
            'uri' => '/api/configuration/host_templates/service_templates',
            'counterpart' => '/api/configuration/services/service_templates',
            'message' => 'You are not allowed to access service templates',
            'item_iri' => '/api/configuration/service_templates/',
            'keys' => ['id', 'name'],
        ],
    ];

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
        $this->dropMonitoringConnectorTables($this->connection);

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
     * The form must work with host template read-write alone. Row-level ACL scoping of contacts, contact
     * groups and host categories still applies, so the fixture is granted to the user.
     */
    #[DataProvider('provideSelectors')]
    public function testEachSelectorListsForAHostTemplateReadWriteUserWithoutHostAccess(string $selector): void
    {
        $name = "ht-{$selector}-{$this->tag}";
        $aclGroupId = $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);
        $fixtureId = $this->insertVisibleFixture($selector, $name, $aclGroupId);
        $definition = self::SELECTORS[$selector];

        // guard: the user holds no right on the other form's list
        $this->request('GET', $definition['counterpart']);
        self::assertResponseStatusCodeSame(403);

        $response = $this->request('GET', $definition['uri'], ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertCount(1, $member);
        self::assertEqualsCanonicalizing(['@id', '@type', ...$definition['keys']], array_keys($member[0]));
        self::assertSame($definition['item_iri'] . $fixtureId, $member[0]['@id']);
        self::assertSame($fixtureId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
    }

    #[DataProvider('provideSelectors')]
    public function testEachSelectorIsForbiddenToAHostTemplateReadOnlyUser(string $selector): void
    {
        $username = bin2hex(random_bytes(8));
        $this->grantHostTemplateTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_ONLY, $this->tag);
        $this->login($username);

        $response = $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseStatusCodeSame(403);
        $body = $response->toArray(false);
        self::assertSame(['code', 'message'], array_keys($body));
        self::assertIsInt($body['code']);
        self::assertSame(self::SELECTORS[$selector]['message'], $body['message']);
    }

    #[DataProvider('provideSelectors')]
    public function testEachSelectorIsForbiddenToAHostReadWriteUserWithoutHostTemplateAccess(string $selector): void
    {
        $username = bin2hex(random_bytes(8));
        $this->grantHostTopologyRole($this->connection, $this->createNonAdminContact($this->connection, $username), self::ACL_ACCESS_READ_WRITE, $this->tag);
        $this->login($username);

        // guard: the user does hold Hosts read-write
        $this->request('GET', '/api/configuration/hosts/commands');
        self::assertResponseIsSuccessful();

        $response = $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(self::SELECTORS[$selector]['message'], $response->toArray(false)['message']);
    }

    #[DataProvider('provideSelectors')]
    public function testEachSelectorRequiresAuthentication(string $selector): void
    {
        $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('provideSelectors')]
    public function testEachSelectorIsRoutedButAbsentFromTheOpenApiDoc(string $selector): void
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
        self::assertNotNull($paths->getPath('/api/configuration/host_templates'));
        self::assertNull($paths->getPath($uri));
    }

    /**
     * The form lists time zones from the generic list, which has no host template variant.
     */
    public function testTheTimezonesListAnswersAHostTemplateReadWriteUserWithoutHostAccess(): void
    {
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        // guard: the user holds no Hosts right
        $this->request('GET', '/api/configuration/hosts/commands');
        self::assertResponseStatusCodeSame(403);

        $response = $this->request('GET', '/api/configuration/timezones');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($response->toArray()['member']);
    }

    public function testSelectorsAreNotAliasedUnderApiLatest(): void
    {
        $this->login();

        $this->request('GET', self::SELECTORS['commands']['uri']);
        self::assertResponseIsSuccessful();

        $this->request('GET', str_replace('/api/', '/api/latest/', self::SELECTORS['commands']['uri']));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * No selector carries a platform condition: each one answers on cloud too.
     */
    #[DataProvider('provideSelectors')]
    public function testEachSelectorStaysRoutedOnCloud(string $selector): void
    {
        $this->forceCloudPlatform();
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        // guard: cloud is in force, where this on-premise-only list is not routed
        $this->request('GET', '/api/configuration/services/contacts');
        self::assertResponseStatusCodeSame(404);

        $this->request('GET', self::SELECTORS[$selector]['uri']);

        self::assertResponseIsSuccessful();
    }

    public function testTheContactGroupsSelectorListsImportedLdapGroups(): void
    {
        $localName = "local-{$this->tag}";
        $this->insertRow('contactgroup', ['cg_name' => $localName, 'cg_alias' => $localName, 'cg_type' => 'local', 'cg_activate' => '1']);
        $ldapName = "ldap-{$this->tag}";
        $this->insertRow('contactgroup', ['cg_name' => $ldapName, 'cg_alias' => $ldapName, 'cg_type' => 'ldap', 'cg_activate' => '1']);
        $this->login();

        $response = $this->request('GET', self::SELECTORS['contact_groups']['uri'], ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$localName, $ldapName], array_column((array) $response->toArray()['member'], 'name'));
    }

    /**
     * The test platform has no license manager, so the license host limit counts as reached.
     */
    public function testTheParentTemplatesSelectorExcludesTheTemplatesTheLicenseNoLongerAllows(): void
    {
        $allowedName = "allowed-{$this->tag}";
        $this->insertHostTemplate($allowedName);
        $unlicensedId = $this->insertHostTemplate("unlicensed-{$this->tag}");
        $this->insertMonitoringConnectorTemplates($this->connection, $unlicensedId);
        $this->login();

        $response = $this->request('GET', self::SELECTORS['host_templates']['uri'], ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$allowedName], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testTheServiceTemplatesSelectorExcludesTheTemplatesTheLicenseNoLongerAllows(): void
    {
        $allowedName = "allowed-{$this->tag}";
        $this->insertRow('service', ['service_description' => $allowedName, 'service_register' => '0']);
        $unlicensedId = $this->insertRow('service', ['service_description' => "unlicensed-{$this->tag}", 'service_register' => '0']);
        $this->insertRow('service', [
            'service_description' => "unlicensed-child-{$this->tag}",
            'service_register' => '0',
            'service_template_model_stm_id' => $unlicensedId,
        ]);
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $unlicensedId);
        $this->loginAsHostTemplateReadWriteUser($this->connection, $this->tag);

        $response = $this->request('GET', self::SELECTORS['service_templates']['uri'], ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$allowedName], array_column((array) $response->toArray()['member'], 'name'));
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

    private function insertVisibleFixture(string $selector, string $name, int $aclGroupId): int
    {
        return match ($selector) {
            'commands' => $this->insertRow('command', [
                'command_name' => $name,
                'command_line' => '$USER1$/check_dummy',
                'command_type' => 2,
                'enable_shell' => '0',
                'command_activate' => '1',
                'command_locked' => '0',
            ]),
            'timeperiods' => $this->insertRow('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]),
            'contacts' => $this->insertContactInAccessGroup($name, $aclGroupId),
            'contact_groups' => $this->insertContactGroupInAccessGroup($name, $aclGroupId),
            'host_categories' => $this->insertHostCategoryInAccessGroup($name, $aclGroupId),
            // a host category with a level is a host severity
            'host_severities' => $this->insertRow('hostcategories', ['hc_name' => $name, 'hc_activate' => '1', 'level' => 1]),
            'host_templates' => $this->insertHostTemplate($name),
            'medias' => $this->insertMedia($name),
            'service_templates' => $this->insertRow('service', ['service_description' => $name, 'service_register' => '0']),
            default => throw new \LogicException("No fixture for {$selector}"),
        };
    }

    private function insertHostTemplate(string $name): int
    {
        return $this->insertRow('host', ['host_name' => $name, 'host_register' => '0', 'host_activate' => '1', 'host_locked' => 0]);
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

    private function insertHostCategoryInAccessGroup(string $name, int $aclGroupId): int
    {
        $hostCategoryId = $this->insertRow('hostcategories', ['hc_name' => $name, 'hc_activate' => '1']);
        $aclResourceId = $this->insertRow('acl_resources', [
            'acl_res_name' => "hc-scope-{$this->tag}",
            'acl_res_alias' => "hc-scope-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResourceId,
            'acl_group_id' => $aclGroupId,
        ]);
        $this->connection->insert('acl_resources_hc_relations', [
            'acl_res_id' => $aclResourceId,
            'hc_id' => $hostCategoryId,
        ]);

        return $hostCategoryId;
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

    /**
     * Must run before the request is made.
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
