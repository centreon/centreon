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
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

/**
 * The HTTP wiring of the service PATCH: route, authentication and access gate, input validation before the service
 * is looked up, error bodies, and one toggle end to end with its side effects. The no-op and event rules are pinned
 * by PatchServiceCommandHandlerTest, which service a user can reach by the repository's integration test.
 */
final class PatchServiceProcessorTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/services';
    private const CONTENT_TYPE = 'application/merge-patch+json';
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

        $this->tag = 'svcpatch' . bin2hex(random_bytes(4));
    }

    #[DataProvider('invalidIds')]
    public function testAnIdThatIsNotAPositiveIntegerIsNotRouted(string $id): void
    {
        // Unauthenticated on purpose: the router answers before the firewall.
        $this->patch($id, ['activated' => false]);

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
        $this->patch('1', ['activated' => false]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAReadOnlyUserIsForbidden(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        $this->loginAsNonAdmin(self::READ_ONLY);

        $this->patch((string) $serviceId, ['activated' => false]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to update services']);
        self::assertSame('1', $this->serviceActivation($serviceId));
    }

    #[DataProvider('invalidBodies')]
    public function testAnActivationSentAsNullOrNotABooleanIsInvalidBeforeTheServiceIsLookedUp(string $body, string $message): void
    {
        $this->login();

        $this->request('PATCH', self::BASE_ENDPOINT . '/999999999', ['headers' => ['Content-Type' => self::CONTENT_TYPE], 'body' => $body]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonEquals(['code' => 422, 'message' => $message]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'null' => ['{"activated": null}', "[activated] This value cannot be null.\n"];

        yield 'not a boolean' => ['{"activated": "x"}', "[activated] This value should be of type bool|null.\n"];
    }

    public function testAnActivationLeftOutIsAccepted(): void
    {
        // The key is optional: the input validation lets an empty body through, and nothing is written.
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        $this->login();

        $this->request('PATCH', self::BASE_ENDPOINT . '/' . $serviceId, ['headers' => ['Content-Type' => self::CONTENT_TYPE], 'body' => '{}']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('1', $this->serviceActivation($serviceId));
        self::assertNull($this->activityLog($serviceId));
    }

    public function testAnotherContentTypeIsUnsupportedBeforeTheAccessGate(): void
    {
        // A read-only user: the content type is negotiated before the write right is checked.
        $this->loginAsNonAdmin(self::READ_ONLY);

        $this->request('PATCH', self::BASE_ENDPOINT . '/1', ['headers' => ['Content-Type' => 'application/json'], 'body' => '{"activated": false}']);

        self::assertResponseStatusCodeSame(415);
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        $this->login();

        $this->patch('999999999', ['activated' => false]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFound(): void
    {
        $serviceId = $this->insertService('ping', $this->insertHost($this->insertPoller()));
        // Deliberately not granted to the user's access group in the real-time ACL cache.
        $this->loginAsNonAdmin(self::READ_WRITE);

        $this->patch((string) $serviceId, ['activated' => false]);

        // Out of scope reads as not found (no existence leak, not a 403), and nothing is changed.
        self::assertResponseStatusCodeSame(404);
        self::assertSame('1', $this->serviceActivation($serviceId));
    }

    public function testItDisablesTheServiceWithItsSideEffects(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $serviceId = $this->insertService('ping', $hostId);
        $aclResourceId = $this->insertUnchangedAclResource();
        $this->login();

        $response = $this->patch((string) $serviceId, ['activated' => false]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertSame('0', $this->serviceActivation($serviceId));
        self::assertSame(['action_type' => 'disable', 'details' => ['host_id' => (string) $hostId, 'service_name' => 'ping']], $this->activityLog($serviceId));
        // An admin flags every ACL resource; the poller of the enabled host is flagged.
        self::assertSame(1, $this->aclResourceChanged($aclResourceId));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(string $id, array $body): ResponseInterface
    {
        return $this->request('PATCH', self::BASE_ENDPOINT . '/' . $id, ['headers' => ['Content-Type' => self::CONTENT_TYPE], 'json' => $body]);
    }

    private function serviceActivation(int $serviceId): mixed
    {
        return $this->connection->fetchOne('SELECT service_activate FROM service WHERE service_id = ?', [$serviceId]);
    }

    /**
     * The activity-log line of the service, which lives in the real-time database; null when there is none.
     *
     * @return array{action_type: mixed, details: array<mixed>}|null
     */
    private function activityLog(int $serviceId): ?array
    {
        $logs = $this->realTimeConnection->fetchAllAssociative(
            "SELECT action_log_id, action_type FROM log_action WHERE object_type = 'service' AND object_id = ?",
            [$serviceId],
        );
        if ($logs === []) {
            return null;
        }

        Assert::count($logs, 1);

        return [
            'action_type' => $logs[0]['action_type'],
            'details' => $this->realTimeConnection->fetchAllKeyValue(
                'SELECT field_name, field_value FROM log_action_modification WHERE action_log_id = ? ORDER BY field_name',
                [$logs[0]['action_log_id']],
            ),
        ];
    }

    private function insertUnchangedAclResource(): int
    {
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . $this->tag, 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function aclResourceChanged(int $aclResourceId): int
    {
        $changed = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResourceId]);
        Assert::integerish($changed);

        return (int) $changed;
    }

    private function loginAsNonAdmin(int $accessRight): void
    {
        $username = 'service-user-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        Assert::integerish($contactId);
        $this->grantServicesByHostTopologyRole((int) $contactId, $accessRight);
        $this->login($username);
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
            'updated' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-central',
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
        $this->connection->insert('service', ['service_description' => $description, 'service_register' => '1', 'service_activate' => '1']);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);

        return $serviceId;
    }
}
