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

use App\MonitoringConfiguration\Domain\Service\HostAddressResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostAddressResolutionResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\ResolveHostAddressProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ResolveHostAddressProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts/_resolve';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_HOSTS_HOSTS_R[W]
    // (Configuration > Hosts > Hosts), bridged to HostPermissionEnum via
    // DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const HOST_TOPOLOGY_PAGES = [6, 601, 60101];

    // CentreonACL access rights, see Centreon\Domain\Repository\TopologyRepository
    private const ACL_ACCESS_READ_WRITE = 1;
    private const ACL_ACCESS_READ_ONLY = 2;

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
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => 'localhost']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForAUserGrantedOnlyTheHostReadTopologyRole(): void
    {
        $username = 'resolve_' . Uuid::v4();
        $this->grantHostTopologyRole($this->createNonAdminContact($username), self::ACL_ACCESS_READ_ONLY);
        $this->login($username);

        // the address is filled in the host form, which needs read-write: CanRead is not enough
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => 'localhost']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('You are not allowed to resolve host addresses', $response->toArray(false)['message'] ?? null);
    }

    public function testItResolvesAHostnameForAUserGrantedTheHostReadWriteTopologyRole(): void
    {
        $username = 'resolve_' . Uuid::v4();
        $this->grantHostTopologyRole($this->createNonAdminContact($username), self::ACL_ACCESS_READ_WRITE);
        $this->login($username);

        // answered by /etc/hosts, so the test needs no network
        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => 'localhost']]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(HostAddressResolutionResource::class);

        $body = $response->toArray();
        self::assertSame('localhost', $body['hostname']);
        self::assertSame('127.0.0.1', $body['ip']);
        self::assertTrue($body['resolved']);
    }

    public function testItReturnsAnIpv4AsIs(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => '192.0.2.10']]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame('192.0.2.10', $body['ip']);
        self::assertTrue($body['resolved']);
    }

    public function testItResolvesAndReturnsTheTrimmedHostname(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => ' localhost ']]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame('localhost', $body['hostname']);
        self::assertSame('127.0.0.1', $body['ip']);
    }

    public function testItReportsAnUnresolvedHostnameWithoutEchoingItBack(): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => 'unknown.example.com']]);
        self::assertResponseIsSuccessful();

        $body = $response->toArray();
        self::assertSame('unknown.example.com', $body['hostname']);
        // ApiPlatform skips null values platform-wide: an unresolved hostname carries no "ip" key at all
        self::assertArrayNotHasKey('ip', $body);
        self::assertFalse($body['resolved']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidQueryProvider(): iterable
    {
        yield 'missing hostname' => [[], 'This value should not be blank.'];

        yield 'empty hostname' => [['hostname' => ''], 'This value should not be blank.'];

        yield 'blank hostname' => [['hostname' => '   '], 'This value should not be blank.'];

        foreach (
            [
                'malformed hostname' => 'http://10.0.0.8',
                'truncated ipv4' => '127.0.1',
                'single number' => '1234',
                'ipv6' => '2001:db8::1',
                'underscore' => 'srv_01',
                'trailing dot' => 'srv.example.com.',
            ] as $case => $hostname
        ) {
            yield $case => [['hostname' => $hostname], 'This value must be an IPv4 address or a hostname.'];
        }

        yield 'too long hostname' => [
            ['hostname' => str_repeat('a', 254)],
            'This value is too long. It should have 253 characters or less.',
        ];

        yield 'array hostname' => [['hostname' => ['localhost']], 'This value should be of type string.'];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidQueryProvider')]
    public function testItRejectsAnInvalidHostname(array $query, string $expectedViolation): void
    {
        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => $query]);
        self::assertResponseStatusCodeSame(422);

        // every violation is one "[property] message" line of a single "message" string
        $message = $response->toArray(false)['message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString("[hostname] {$expectedViolation}", $message);
    }

    public function testItIsNotAvailableOnCloudPlatforms(): void
    {
        $this->login();
        $this->forceCloudPlatform();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['hostname' => 'localhost']]);
        self::assertResponseStatusCodeSame(404);
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
        $tag = Uuid::v4()->toRfc4122();

        $this->connection->insert('acl_groups', [
            'acl_group_name' => "topology-group-{$tag}",
            'acl_group_alias' => "topology-group-{$tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => "topology-rule-{$tag}",
            'acl_topo_alias' => "topology-rule-{$tag}",
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
     * `isCloudPlatform` is a constructor argument autowired from the IS_CLOUD_PLATFORM env var, so
     * the platform is forced by replacing the Provider instance in the container.
     */
    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();

        /** @var HostAddressResolver $hostAddressResolver */
        $hostAddressResolver = $container->get(HostAddressResolver::class);

        $container->set(ResolveHostAddressProvider::class, new ResolveHostAddressProvider($hostAddressResolver, true));
    }
}
