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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * The HTTP wiring of the service deletion: route, authentication and access gate, error bodies, and one deletion
 * end to end with its side effects. The order of the checks and the events are pinned by
 * DeleteServiceCommandHandlerTest, which service a user can reach by the repository's integration test.
 */
final class DeleteServiceProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/services';
    private const READ_WRITE = 1;
    private const READ_ONLY = 2;

    private Connection $connection;

    private Connection $realTimeConnection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->tag = 'svcdel' . bin2hex(random_bytes(4));
    }

    #[DataProvider('invalidIds')]
    public function testAnIdThatIsNotAPositiveIntegerIsNotRouted(string $id): void
    {
        // Unauthenticated on purpose: the router answers before the firewall.
        $this->request('DELETE', self::BASE_ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'zero' => ['0'];

        yield 'non-numeric' => ['abc'];

        yield 'leading zero' => ['007'];
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('DELETE', self::BASE_ENDPOINT . '/1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAReadOnlyUserIsForbidden(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost('central', $this->insertPoller()));
        $username = 'service-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);
        $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_ONLY);

        $this->login($username);
        $this->request('DELETE', self::BASE_ENDPOINT . '/' . $serviceId);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to delete services']);
        self::assertTrue($this->serviceExists($serviceId));
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFound(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost('central', $this->insertPoller()));
        $username = 'service-editor-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);
        $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_WRITE);
        // Deliberately not granted to the user's access group in the real-time ACL cache.

        $this->login($username);
        $this->request('DELETE', self::BASE_ENDPOINT . '/' . $serviceId);

        // Out of scope reads as not found (no existence leak, not a 403), and nothing is deleted.
        self::assertResponseStatusCodeSame(404);
        self::assertTrue($this->serviceExists($serviceId));
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        $this->login();

        $this->request('DELETE', self::BASE_ENDPOINT . '/999999999');

        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);
    }

    public function testItDeletesTheServiceWithItsSideEffects(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost('central', $pollerId);
        $serviceId = $this->insertService('ping', $hostId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . $this->tag, 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);

        $aclResourceId = (int) $this->connection->lastInsertId();

        $this->login();
        $response = $this->request('DELETE', self::BASE_ENDPOINT . '/' . $serviceId);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertFalse($this->serviceExists($serviceId));

        // The activity log lives in the real-time database.
        $log = $this->realTimeConnection->fetchAssociative(
            "SELECT action_log_id, action_type, object_name FROM log_action WHERE object_type = 'service' AND object_id = ?",
            [$serviceId],
        );
        Assert::isArray($log);
        self::assertSame('d', $log['action_type']);
        self::assertSame('ping', $log['object_name']);
        self::assertSame(
            ['host_id' => (string) $hostId, 'service_name' => 'ping'],
            $this->realTimeConnection->fetchAllKeyValue(
                'SELECT field_name, field_value FROM log_action_modification WHERE action_log_id = ? ORDER BY field_name',
                [$log['action_log_id']],
            ),
        );

        // An admin flags every ACL resource; the poller of the enabled host is flagged.
        /** @var int|string $changed */
        $changed = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResourceId]);
        self::assertSame(1, (int) $changed);
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    private function serviceExists(int $serviceId): bool
    {
        return $this->connection->fetchOne('SELECT service_id FROM service WHERE service_id = ?', [$serviceId]) !== false;
    }

    /**
     * "Configuration > Services > Services by host" with the given access right.
     */
    private function grantServicesByHostTopologyRole(int $contactId, int $accessRight): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'service-group-' . $this->tag,
            'acl_group_alias' => 'service-group-' . $this->tag,
            'acl_group_activate' => '1',
        ]);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => $contactId]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'service-rule-' . $this->tag,
            'acl_topo_alias' => 'service-rule-' . $this->tag,
            'acl_topo_activate' => '1',
        ]);
        $topologyRuleId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $groupId, 'acl_topology_id' => $topologyRuleId]);

        foreach ([6, 602, 60201] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$topologyPage]);
            Assert::integerish($topologyId, "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => $accessRight,
            ]);
        }
    }

    private function insertPoller(): int
    {
        $this->connection->insert('nagios_server', [
            'name' => 'poller-' . $this->tag,
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-' . $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
    }

    private function insertService(string $description, int $hostId): int
    {
        $this->connection->insert('service', ['service_description' => $description, 'service_register' => '1']);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->linkServiceToHost($serviceId, $hostId);

        return $serviceId;
    }

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }
}
