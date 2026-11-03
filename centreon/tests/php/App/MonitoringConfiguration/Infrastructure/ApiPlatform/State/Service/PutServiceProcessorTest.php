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

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

/**
 * The HTTP wiring of the service update: route, access gate, payload deserialization (the required activation
 * flag), the 200 body, the status codes of the handler and the side effects end to end. Each rule of the payload is
 * pinned by the input validation tests, each rule of the update by the handler's test.
 */
final class PutServiceProcessorTest extends ApiTestCase
{
    private const ENDPOINT = '/api/configuration/services/';
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

        $this->tag = 'svcput' . bin2hex(random_bytes(4));
    }

    public function testItUpdatesAService(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $this->login();

        $body = $this->request('PUT', self::ENDPOINT . $serviceId, [
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => 'web  check  v2',
                'host_id' => $hostId,
                'activated' => false,
                'check_options' => ['command_id' => $checkCommandId, 'args' => ['443']],
                'monitoring_engine' => ['max_check_attempts' => 4, 'note' => 'a note'],
                'advanced' => ['stalking_options' => ['critical']],
            ],
        ])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame($serviceId, $body['id']);
        self::assertSame('web check v2', $body['name']);
        self::assertFalse($body['activated']);
        self::assertSame(['id' => $hostId, 'name' => $this->tag . '-host'], $body['host']);
        \assert(is_array($body['check_options']) && is_array($body['monitoring_engine']) && is_array($body['advanced']));
        self::assertSame(['443'], $body['check_options']['args']);
        self::assertSame(
            ['max_check_attempts' => 4, 'note' => 'a note'],
            array_intersect_key($body['monitoring_engine'], array_flip(['max_check_attempts', 'note'])),
        );
        self::assertSame(['critical'], $body['advanced']['stalking_options']);

        self::assertSame(
            ['service_description' => 'web check v2', 'service_activate' => '0', 'command_command_id_arg' => '!443', 'service_max_check_attempts' => '4'],
            $this->connection->fetchAssociative(
                'SELECT service_description, service_activate, command_command_id_arg, CAST(service_max_check_attempts AS CHAR) AS service_max_check_attempts
                    FROM service WHERE service_id = ?',
                [$serviceId],
            ),
        );

        // A disable line, then a change line carrying the form.
        $lines = $this->realTimeConnection->fetchFirstColumn(
            "SELECT action_type FROM log_action WHERE object_type = 'service' AND object_id = ? ORDER BY action_log_id",
            [$serviceId],
        );
        self::assertSame(['disable', 'c'], $lines);
        $details = $this->realTimeConnection->fetchAllKeyValue(
            "SELECT lam.field_name, lam.field_value
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_type = 'service' AND la.object_id = ? AND la.action_type = 'c'",
            [$serviceId],
        );
        self::assertSame(['web check v2', (string) $hostId, '!443'], [
            $details['service_description'] ?? null,
            $details['host_id'] ?? null,
            $details['command_command_id_arg'] ?? null,
        ]);

        // The JSON-LD body matches the resource schema.
        $this->request('PUT', self::ENDPOINT . $serviceId, ['json' => ['name' => 'web check v2', 'host_id' => $hostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]]]);
        self::assertResponseStatusCodeSame(200);
        self::assertMatchesResourceItemJsonSchema(ServiceResource::class);
    }

    /**
     * The activation flag is required, a full replace leaving no room for a default.
     */
    public function testItRequiresActivated(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $this->login();

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => ['name' => 'web check', 'host_id' => $hostId, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonEquals([
            'code' => 422,
            'message' => "[activated] This value should be of type bool.\n",
        ]);
        self::assertSame('1', $this->connection->fetchOne('SELECT service_activate FROM service WHERE service_id = ?', [$serviceId]));
    }

    public function testItAnswers404(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $this->login();

        $this->request('PUT', self::ENDPOINT . '999999999', [
            'json' => ['name' => 'ping', 'host_id' => $hostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);
    }

    /**
     * The body carries the exception's own code, as every non-422 error (and as host PUT).
     */
    public function testItAnswers409OnANameConflict(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $this->insertService('disk', $hostId, $checkCommandId);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $this->login();

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => ['name' => 'DISK', 'host_id' => $hostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonEquals(['code' => 0, 'message' => 'Resource already exists.']);
        self::assertSame('web check', $this->connection->fetchOne('SELECT service_description FROM service WHERE service_id = ?', [$serviceId]));
    }

    public function testItAnswers403WithoutWriteRight(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $username = 'service-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));
        $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_ONLY);
        $this->login($username);

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => ['name' => 'ping', 'host_id' => $hostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to update services']);
    }

    private function insertService(string $name, int $hostId, int $checkCommandId): int
    {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => '1',
            'service_activate' => '1',
            'command_command_id' => $checkCommandId,
        ]);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId]);

        return $serviceId;
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
            \assert(is_numeric($topologyId), "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $topologyRuleId,
                'access_right' => $accessRight,
            ]);
        }
    }

    private function insertPoller(): int
    {
        $this->connection->insert('nagios_server', ['name' => 'poller-' . $this->tag, 'uid' => random_int(1, PHP_INT_MAX)]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-host',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', ['host_host_id' => $hostId, 'nagios_server_id' => $pollerId]);

        return $hostId;
    }

    private function insertCommand(string $name, int $type): int
    {
        $this->connection->insert('command', ['command_name' => $this->tag . '-' . $name, 'command_line' => '$USER1$/check', 'command_type' => $type]);

        return (int) $this->connection->lastInsertId();
    }
}
