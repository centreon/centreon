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
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;

/**
 * The HTTP wiring of the service creation: route, access gate, payload deserialization, the validation pipeline
 * and its error body, the 201 body and the side effects end to end. Each rule of the payload is pinned by the
 * input validation test, each rule of the creation by the handler's test.
 */
final class CreateServiceProcessorTest extends ApiTestCase
{
    use InsertsMonitoringConnectorTemplates;
    private const ENDPOINT = '/api/configuration/services';
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

        $this->tag = 'svccreate' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);
        parent::tearDown();
    }

    public function testItCreatesAService(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $checkCommandId = $this->insertCommand('check', type: 2);
        $templateId = $this->insertServiceTemplate('generic', $checkCommandId);
        $this->connection->insert('timeperiod', ['tp_name' => $this->tag . '-24x7', 'tp_alias' => '24x7']);
        $timePeriodId = (int) $this->connection->lastInsertId();
        $this->login();

        $body = $this->request('POST', self::ENDPOINT, [
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => 'web check',
                'host_id' => $hostId,
                'service_template_id' => $templateId,
                'check_options' => ['args' => ['80']],
                'monitoring_engine' => ['check_timeperiod_id' => $timePeriodId, 'max_check_attempts' => 3, 'is_volatile' => 'true', 'note' => 'a note'],
                'notifications' => ['enabled' => 'false', 'options' => ['warning', 'critical']],
                'advanced' => ['stalking_options' => ['ok'], 'acknowledgment_timeout' => 15],
            ],
        ])->toArray();

        self::assertResponseStatusCodeSame(201);
        $serviceId = $body['id'];
        \assert(is_int($serviceId));
        self::assertSame('web check', $body['name']);
        self::assertTrue($body['activated']);
        self::assertSame(['id' => $hostId, 'name' => $this->tag . '-host'], $body['host']);
        self::assertSame(['id' => $templateId, 'name' => $this->tag . '-generic'], $body['template']);
        self::assertSame(['args' => ['80'], 'macros' => []], $body['check_options']);
        \assert(is_array($body['monitoring_engine']) && is_array($body['notifications']) && is_array($body['advanced']));
        self::assertSame(
            ['check_period' => ['id' => $timePeriodId, 'name' => $this->tag . '-24x7'], 'max_check_attempts' => 3, 'is_volatile' => 'true', 'note' => 'a note'],
            array_intersect_key($body['monitoring_engine'], array_flip(['check_period', 'max_check_attempts', 'is_volatile', 'note'])),
        );
        self::assertSame(['enabled' => 'false', 'options' => ['warning', 'critical']], array_intersect_key($body['notifications'], array_flip(['enabled', 'options'])));
        self::assertSame(['acknowledgment_timeout' => 15, 'stalking_options' => ['ok']], array_intersect_key($body['advanced'], array_flip(['stalking_options', 'acknowledgment_timeout'])));

        // Written as one service of the host, and logged with its form.
        self::assertSame(
            [(string) $hostId],
            array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $this->connection->fetchFirstColumn('SELECT host_host_id FROM host_service_relation WHERE service_service_id = ?', [$serviceId])),
        );
        $details = $this->realTimeConnection->fetchAllKeyValue(
            "SELECT lam.field_name, lam.field_value
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_type = 'service' AND la.object_id = ? AND la.action_type = 'a'",
            [$serviceId],
        );
        self::assertSame(['web check', (string) $hostId, 'w,c', '1'], [
            $details['service_description'] ?? null,
            $details['host_id'] ?? null,
            $details['service_notification_options'] ?? null,
            $details['service_is_volatile'] ?? null,
        ]);

        // The JSON-LD body matches the resource schema.
        $this->request('POST', self::ENDPOINT, ['json' => ['name' => 'other', 'host_id' => $hostId, 'service_template_id' => $templateId]]);
        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(ServiceResource::class);
    }

    /**
     * The macros of `check_options` are deserialized and stored as the service's own; they are answered with the
     * ids of their rows, a password macro without its value.
     */
    public function testItCreatesAServiceWithMacros(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $this->login();

        $body = $this->request('POST', self::ENDPOINT, [
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => 'web check',
                'host_id' => $hostId,
                'check_options' => [
                    'command_id' => $checkCommandId,
                    'macros' => [
                        ['name' => 'port', 'value' => '8080', 'is_password' => false],
                        ['name' => 'token', 'value' => 's3cr3t', 'is_password' => true],
                    ],
                ],
            ],
        ])->toArray();

        self::assertResponseStatusCodeSame(201);
        $serviceId = $body['id'];
        \assert(is_int($serviceId));
        \assert(is_array($body['check_options']) && is_array($body['check_options']['macros']));
        $macros = array_values($body['check_options']['macros']);
        self::assertCount(2, $macros);
        \assert(is_array($macros[0]) && is_array($macros[1]));
        self::assertSame(['name' => 'PORT', 'value' => '8080', 'is_password' => false], array_intersect_key($macros[0], array_flip(['name', 'value', 'is_password'])));
        self::assertSame(['name' => 'TOKEN', 'is_password' => true], array_intersect_key($macros[1], array_flip(['name', 'value', 'is_password'])));
        self::assertArrayNotHasKey('value', $macros[1]);
        self::assertArrayNotHasKey('parent', $macros[0]);
        self::assertArrayNotHasKey('parent', $macros[1]);
        /** @var list<int|string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT svc_macro_id FROM on_demand_macro_service WHERE svc_svc_id = ? ORDER BY svc_macro_id', [$serviceId]);
        self::assertSame(array_map('intval', $ids), [$macros[0]['id'] ?? null, $macros[1]['id'] ?? null]);

        // A password value depends on the vault configuration: only the plain one is compared.
        self::assertSame(
            [
                ['svc_macro_name' => '$_SERVICEPORT$', 'plain_value' => '8080', 'is_password' => null],
                ['svc_macro_name' => '$_SERVICETOKEN$', 'plain_value' => null, 'is_password' => '1'],
            ],
            $this->connection->fetchAllAssociative(
                'SELECT svc_macro_name, CASE WHEN is_password IS NULL THEN svc_macro_value END AS plain_value,
                        CAST(is_password AS CHAR) AS is_password
                    FROM on_demand_macro_service WHERE svc_svc_id = ? ORDER BY svc_macro_id',
                [$serviceId],
            ),
        );
    }

    /**
     * One line per violation, each prefixed with its snake_case path, a nested field included.
     */
    public function testItAnswers422WithTheFieldPath(): void
    {
        $this->login();

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'ping',
                'host_id' => 999999999,
                'check_options' => ['command_id' => 999999999],
                'monitoring_engine' => ['max_check_attempts' => 0],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonEquals([
            'code' => 422,
            'message' => "[host_id] This host does not exist or is not accessible.\n"
                . "[check_options.command_id] This command does not exist or is not a check command.\n"
                . "[monitoring_engine.max_check_attempts] This value should be greater than or equal to 1.\n",
        ]);
    }

    public function testItAnswers403WithoutWriteRight(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $username = 'service-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));
        $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_ONLY);
        $this->login($username);

        $this->request('POST', self::ENDPOINT, ['json' => ['name' => 'ping', 'host_id' => $hostId]]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to create services']);
    }

    /**
     * The creator's group is granted the new service in the real-time ACL cache within the request, so they see it at
     * once. A GET is not replayed here: the test transactions keep the grant, written on the real-time connection,
     * out of sight of the configuration connection that scopes the read.
     */
    public function testANonAdminCreatorSeesTheServiceAtOnce(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $templateId = $this->insertServiceTemplate('generic', $this->insertCommand('check', type: 2));
        $username = 'service-writer-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));
        $aclGroupId = $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_WRITE);
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $hostId, 'service_id' => null]);
        $this->connection->update('acl_groups', ['acl_group_changed' => 0], ['acl_group_id' => $aclGroupId]);
        $this->login($username);

        $body = $this->request('POST', self::ENDPOINT, [
            'headers' => ['accept' => 'application/json'],
            'json' => ['name' => 'ping', 'host_id' => $hostId, 'service_template_id' => $templateId],
        ])->toArray();

        self::assertResponseStatusCodeSame(201);
        $serviceId = $body['id'];
        \assert(is_int($serviceId));
        self::assertSame(['ping', $hostId], [$body['name'], is_array($body['host']) ? $body['host']['id'] : null]);
        self::assertSame(
            [(string) $aclGroupId],
            array_map(
                static fn (mixed $id): string => is_scalar($id) ? (string) $id : '',
                $this->realTimeConnection->fetchFirstColumn('SELECT group_id FROM centreon_acl WHERE host_id = ? AND service_id = ?', [$hostId, $serviceId]),
            ),
        );
        $changedFlag = $this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$aclGroupId]);
        self::assertTrue(is_numeric($changedFlag) && (int) $changedFlag === 1, "The creator's group is flagged for the ACL recomputation.");
    }

    /**
     * The license check is the handler's: its refusal reaches the client as a 422 on the template.
     */
    public function testItRejectsAnUnlicensedConnectorTemplate(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $templateId = $this->insertServiceTemplate('connector', $this->insertCommand('check', type: 2));
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $templateId);
        $this->login();

        $this->request('POST', self::ENDPOINT, ['json' => ['name' => 'ping', 'host_id' => $hostId, 'service_template_id' => $templateId]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonEquals([
            'code' => 422,
            'message' => "[service_template_id] The license host limit is reached: service templates coming from Monitoring Connectors cannot be used.\n",
        ]);
        self::assertFalse($this->connection->fetchOne("SELECT 1 FROM service WHERE service_template_model_stm_id = ? AND service_register = '1'", [$templateId]));
    }

    /**
     * "Configuration > Services > Services by host" with the given access right.
     */
    private function grantServicesByHostTopologyRole(int $contactId, int $accessRight): int
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

        return $groupId;
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

    private function insertServiceTemplate(string $name, int $checkCommandId): int
    {
        $this->connection->insert('service', [
            'service_description' => $this->tag . '-' . $name,
            'service_register' => '0',
            'command_command_id' => $checkCommandId,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
