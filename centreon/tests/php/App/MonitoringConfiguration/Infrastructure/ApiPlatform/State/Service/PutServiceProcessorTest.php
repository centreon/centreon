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

    /**
     * A restricted actor sends back what they see of the service: the service groups, categories, contacts and the
     * severity outside their scope stay linked, and the response does not show them.
     */
    public function testARestrictedActorRoundTripKeepsAndHidesOutOfScopeRelations(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $visibleGroupId = $this->insertServiceGroup('visible');
        $hiddenGroupId = $this->insertServiceGroup('hidden');
        $visibleCategoryId = $this->insertServiceCategory('visible');
        $hiddenCategoryId = $this->insertServiceCategory('hidden');
        $hiddenSeverityId = $this->insertServiceCategory('severity', level: 1);
        $visibleContactId = $this->insertContact('visible');
        $hiddenContactId = $this->insertContact('hidden');
        foreach ([$visibleGroupId, $hiddenGroupId] as $groupId) {
            $this->connection->insert('servicegroup_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId, 'servicegroup_sg_id' => $groupId]);
        }
        foreach ([$visibleCategoryId, $hiddenCategoryId, $hiddenSeverityId] as $categoryId) {
            $this->connection->insert('service_categories_relation', ['service_service_id' => $serviceId, 'sc_id' => $categoryId]);
        }
        foreach ([$visibleContactId, $hiddenContactId] as $contactId) {
            $this->connection->insert('contact_service_relation', ['contact_id' => $contactId, 'service_service_id' => $serviceId]);
        }
        $aclGroupId = $this->loginRestrictedActor($pollerId, [$visibleGroupId], [$visibleCategoryId]);
        $this->grantInRealTimeAcl($aclGroupId, $hostId, $serviceId);
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $aclGroupId, 'contact_contact_id' => $visibleContactId]);

        $body = $this->request('PUT', self::ENDPOINT . $serviceId, [
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => 'web check',
                'host_id' => $hostId,
                'activated' => true,
                'check_options' => ['command_id' => $checkCommandId],
                'service_group_ids' => [$visibleGroupId],
                'category_ids' => [$visibleCategoryId],
                'notifications' => ['contacts' => [$visibleContactId]],
            ],
        ])->toArray();

        self::assertResponseStatusCodeSame(200);
        \assert(is_array($body['service_groups']) && is_array($body['categories']) && is_array($body['notifications']));
        \assert(is_array($body['notifications']['contacts']));
        self::assertSame([$visibleGroupId], array_column($body['service_groups'], 'id'));
        self::assertSame([$visibleCategoryId], array_column($body['categories'], 'id'));
        self::assertNull($body['severity'] ?? null);
        self::assertSame([$visibleContactId], array_column($body['notifications']['contacts'], 'id'));

        self::assertEqualsCanonicalizing(
            [$visibleGroupId, $hiddenGroupId],
            $this->intColumn('SELECT servicegroup_sg_id FROM servicegroup_relation WHERE service_service_id = ?', $serviceId),
        );
        self::assertEqualsCanonicalizing(
            [$visibleCategoryId, $hiddenCategoryId, $hiddenSeverityId],
            $this->intColumn('SELECT sc_id FROM service_categories_relation WHERE service_service_id = ?', $serviceId),
        );
        self::assertEqualsCanonicalizing(
            [$visibleContactId, $hiddenContactId],
            $this->intColumn('SELECT contact_id FROM contact_service_relation WHERE service_service_id = ?', $serviceId),
        );
    }

    /**
     * Resending the service as it is stored changes nothing, so no activity log line is written.
     */
    public function testAnUnchangedUpdateWritesNoActivityLog(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $this->login();
        $payload = [
            'name' => 'web check v2',
            'host_id' => $hostId,
            'activated' => true,
            'check_options' => ['command_id' => $checkCommandId, 'args' => ['443']],
            'monitoring_engine' => ['max_check_attempts' => 4],
        ];
        $this->request('PUT', self::ENDPOINT . $serviceId, ['json' => $payload]);
        self::assertResponseStatusCodeSame(200);
        $linesBefore = $this->logActionCount($serviceId);

        $this->request('PUT', self::ENDPOINT . $serviceId, ['json' => $payload]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($linesBefore, $this->logActionCount($serviceId));
    }

    /**
     * Moving the service to a host on another poller flags both pollers for export.
     */
    public function testAHostMoveFlagsBothPollers(): void
    {
        $previousPollerId = $this->insertPoller();
        $nextPollerId = $this->insertPoller('next');
        $previousHostId = $this->insertHost($previousPollerId);
        $nextHostId = $this->insertHost($nextPollerId, 'next');
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $previousHostId, $checkCommandId);
        $this->connection->executeStatement("UPDATE nagios_server SET updated = '0' WHERE id IN (?, ?)", [$previousPollerId, $nextPollerId]);
        $this->login();

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => ['name' => 'web check', 'host_id' => $nextHostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [$nextHostId],
            $this->intColumn('SELECT host_host_id FROM host_service_relation WHERE service_service_id = ?', $serviceId),
        );
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$previousPollerId]));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$nextPollerId]));
    }

    /**
     * An own macro resent by its id is updated in place, one left out is deleted, a password macro resent without a
     * value keeps the stored one, and an inherited macro resent with another value becomes an own macro.
     */
    public function testItReplacesTheOwnMacros(): void
    {
        $hostId = $this->insertHost($this->insertPoller());
        $checkCommandId = $this->insertCommand('check', type: 2);
        $templateId = $this->insertServiceTemplate('generic', $checkCommandId);
        $inheritedId = $this->insertServiceMacro($templateId, 'URL', '/');
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId, $templateId);
        $keptId = $this->insertServiceMacro($serviceId, 'PORT', '80');
        $removedId = $this->insertServiceMacro($serviceId, 'STALE', 'x');
        $passwordId = $this->insertServiceMacro($serviceId, 'TOKEN', 'secret', isPassword: true);
        $this->login();

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => [
                'name' => 'web check',
                'host_id' => $hostId,
                'activated' => true,
                'service_template_id' => $templateId,
                'check_options' => ['macros' => [
                    ['id' => $keptId, 'name' => 'port', 'value' => '8080', 'is_password' => false],
                    ['id' => $passwordId, 'name' => 'token', 'value' => null, 'is_password' => true],
                    ['id' => $inheritedId, 'parent' => 'template', 'name' => 'url', 'value' => '/health', 'is_password' => false],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        /** @var array<string, array{svc_macro_id: int|string, svc_macro_value: ?string}> $macros */
        $macros = $this->connection->fetchAllAssociativeIndexed(
            'SELECT svc_macro_name, svc_macro_id, svc_macro_value FROM on_demand_macro_service WHERE svc_svc_id = ?',
            [$serviceId],
        );
        self::assertEqualsCanonicalizing(['$_SERVICEPORT$', '$_SERVICETOKEN$', '$_SERVICEURL$'], array_keys($macros));
        self::assertSame([(string) $keptId, '8080'], [(string) $macros['$_SERVICEPORT$']['svc_macro_id'], $macros['$_SERVICEPORT$']['svc_macro_value']]);
        self::assertSame([(string) $passwordId, 'secret'], [(string) $macros['$_SERVICETOKEN$']['svc_macro_id'], $macros['$_SERVICETOKEN$']['svc_macro_value']]);
        self::assertSame('/health', $macros['$_SERVICEURL$']['svc_macro_value']);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM on_demand_macro_service WHERE svc_macro_id = ?', [$removedId]));
        self::assertSame('/', $this->connection->fetchOne('SELECT svc_macro_value FROM on_demand_macro_service WHERE svc_macro_id = ?', [$inheritedId]));
    }

    /**
     * An existing service outside a restricted actor's scope reads as not found, and is left untouched.
     */
    public function testAServiceOutsideTheRestrictedActorScopeIsNotFound(): void
    {
        $pollerId = $this->insertPoller();
        $hostId = $this->insertHost($pollerId);
        $checkCommandId = $this->insertCommand('check', type: 2);
        $serviceId = $this->insertService('web check', $hostId, $checkCommandId);
        $aclGroupId = $this->loginRestrictedActor($pollerId);
        // The host is in the actor's scope, the service is not.
        $this->grantInRealTimeAcl($aclGroupId, $hostId, null);

        $this->request('PUT', self::ENDPOINT . $serviceId, [
            'json' => ['name' => 'renamed', 'host_id' => $hostId, 'activated' => true, 'check_options' => ['command_id' => $checkCommandId]],
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('web check', $this->connection->fetchOne('SELECT service_description FROM service WHERE service_id = ?', [$serviceId]));
    }

    private function insertService(string $name, int $hostId, int $checkCommandId, ?int $templateId = null): int
    {
        $this->connection->insert('service', [
            'service_description' => $name,
            'service_register' => '1',
            'service_activate' => '1',
            'command_command_id' => $checkCommandId,
            'service_template_model_stm_id' => $templateId,
        ]);
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
        $this->connection->insert('extended_service_information', ['service_service_id' => $serviceId]);

        return $serviceId;
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

    private function insertPoller(string $name = 'poller'): int
    {
        $this->connection->insert('nagios_server', ['name' => $name . '-' . $this->tag, 'uid' => random_int(1, PHP_INT_MAX)]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(int $pollerId, string $name = 'host'): int
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

    private function insertCommand(string $name, int $type): int
    {
        $this->connection->insert('command', ['command_name' => $this->tag . '-' . $name, 'command_line' => '$USER1$/check', 'command_type' => $type]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * A non-admin actor with write access to services, scoped to the poller and to the given service groups and
     * categories, logged in.
     *
     * @param list<int> $serviceGroupIds
     * @param list<int> $serviceCategoryIds
     *
     * @return int the actor's access group, to grant services to in centreon_acl
     */
    private function loginRestrictedActor(int $pollerId, array $serviceGroupIds = [], array $serviceCategoryIds = []): int
    {
        $username = 'service-editor-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));
        $aclGroupId = $this->grantServicesByHostTopologyRole((int) $contactId, self::READ_WRITE);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'service-resource-' . $this->tag,
            'acl_res_alias' => 'service-resource-' . $this->tag,
            'acl_res_activate' => '1',
            'all_hostgroups' => '0',
            'all_servicegroups' => '0',
        ]);
        $aclResourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $aclResourceId, 'acl_group_id' => $aclGroupId]);
        $this->connection->insert('acl_resources_poller_relations', ['acl_res_id' => $aclResourceId, 'poller_id' => $pollerId]);
        foreach ($serviceGroupIds as $serviceGroupId) {
            $this->connection->insert('acl_resources_sg_relations', ['acl_res_id' => $aclResourceId, 'sg_id' => $serviceGroupId]);
        }
        foreach ($serviceCategoryIds as $serviceCategoryId) {
            $this->connection->insert('acl_resources_sc_relations', ['acl_res_id' => $aclResourceId, 'sc_id' => $serviceCategoryId]);
        }

        $this->login($username);

        return $aclGroupId;
    }

    /**
     * The host scope is read on the real-time connection, the service one cross-database through the main
     * connection: each grant goes through the connection that reads it, inside its test transaction.
     */
    private function grantInRealTimeAcl(int $aclGroupId, int $hostId, ?int $serviceId): void
    {
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $hostId]);
        if ($serviceId === null) {
            return;
        }

        $database = $this->realTimeConnection->getDatabase();
        \assert(is_string($database));
        $this->connection->executeStatement(
            sprintf('INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (?, ?, ?)', $this->connection->quoteSingleIdentifier($database)),
            [$aclGroupId, $hostId, $serviceId],
        );
    }

    private function insertServiceGroup(string $name): int
    {
        $this->connection->insert('servicegroup', ['sg_name' => $this->tag . '-' . $name, 'sg_alias' => $name, 'sg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertServiceCategory(string $name, ?int $level = null): int
    {
        $this->connection->insert('service_categories', [
            'sc_name' => $this->tag . '-' . $name,
            'sc_description' => $name,
            'level' => $level,
            'sc_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContact(string $name): int
    {
        $name = $this->tag . '-' . $name;
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@example.com',
        ]);

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

    private function insertServiceMacro(int $serviceId, string $name, string $value, bool $isPassword = false): int
    {
        $this->connection->insert('on_demand_macro_service', [
            'svc_macro_name' => '$_SERVICE' . $name . '$',
            'svc_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'svc_svc_id' => $serviceId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<int>
     */
    private function intColumn(string $sql, int $parameter): array
    {
        /** @var list<int|string> $values */
        $values = $this->connection->fetchFirstColumn($sql, [$parameter]);

        return array_map(intval(...), $values);
    }

    private function logActionCount(int $serviceId): int
    {
        $count = $this->realTimeConnection->fetchOne("SELECT COUNT(*) FROM log_action WHERE object_type = 'service' AND object_id = ?", [$serviceId]);
        \assert(is_numeric($count));

        return (int) $count;
    }
}
