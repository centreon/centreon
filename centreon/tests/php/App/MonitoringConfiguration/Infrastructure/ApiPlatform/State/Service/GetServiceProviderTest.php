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
 * The HTTP wiring of the service form read: route, access gate, not-found answer and the wire format. What each
 * field holds is pinned by {@see ServiceResourceBuilderTest}, the rows by the repository's integration tests.
 */
final class GetServiceProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/services';
    private const READ_ONLY = 2;

    private Connection $connection;

    private string $realTimeDatabaseName;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $realTimeDatabaseName = $realTimeConnection->getDatabase();
        \assert(is_string($realTimeDatabaseName));
        $this->realTimeDatabaseName = $realTimeDatabaseName;

        $this->tag = 'svcget' . bin2hex(random_bytes(4));
    }

    public function testItRequiresAuthentication(): void
    {
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('ping');
        $this->linkServiceToHost($serviceId, $hostId);

        $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturnsTheServiceForm(): void
    {
        $hostId = $this->insertHost('central');
        $templateId = $this->insertService('generic', register: '0');
        $this->connection->insert('on_demand_macro_service', ['svc_macro_name' => '$_SERVICEINHERITED$', 'svc_macro_value' => 'tpl', 'svc_svc_id' => $templateId]);
        $checkCommandId = $this->insertCommand('check');
        $eventHandlerId = $this->insertCommand('handler');
        $timePeriodId = $this->insertTimePeriod('24x7');
        $folderId = $this->insertImageFolder();
        $iconId = $this->insertImage('ping.png', $folderId);
        $this->connection->insert('giv_graphs_template', ['name' => $this->tag . '-graph']);
        $graphTemplateId = (int) $this->connection->lastInsertId();
        $contactId = $this->insertContact();
        $this->connection->insert('contactgroup', ['cg_name' => $this->tag . '-cg', 'cg_alias' => $this->tag . '-cg', 'cg_activate' => '1']);
        $contactGroupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('servicegroup', ['sg_name' => $this->tag . '-sg', 'sg_activate' => '1']);
        $serviceGroupId = (int) $this->connection->lastInsertId();
        $categoryId = $this->insertCategory(level: null);
        $severityId = $this->insertCategory(level: 1);
        $this->connection->insert('traps_vendor', ['name' => $this->tag . '-vendor', 'alias' => $this->tag]);
        $vendorId = (int) $this->connection->lastInsertId();
        $this->connection->insert('traps', ['traps_name' => $this->tag . '-trap', 'traps_oid' => '.1.3.6.1.4.1.' . random_int(1, PHP_INT_MAX), 'manufacturer_id' => $vendorId]);
        $trapId = (int) $this->connection->lastInsertId();

        $serviceId = $this->insertService('ping', templateId: $templateId);
        $this->linkServiceToHost($serviceId, $hostId);
        $this->connection->update('service', [
            'command_command_id' => $checkCommandId,
            'command_command_id_arg' => '!80',
            'timeperiod_tp_id' => $timePeriodId,
            'command_command_id2' => $eventHandlerId,
            'command_command_id_arg2' => '!arg',
            'timeperiod_tp_id2' => $timePeriodId,
            'service_max_check_attempts' => 3,
            'service_normal_check_interval' => 5,
            'service_retry_check_interval' => 1,
            'service_is_volatile' => '1',
            'service_notifications_enabled' => '1',
            'service_notification_options' => 'w,c',
            'service_notification_interval' => 30,
            'service_stalking_options' => 'o,c',
            'service_acknowledgement_timeout' => 15,
            'service_comment' => 'a comment',
            'geo_coords' => '48.85,2.35',
        ], ['service_id' => $serviceId]);
        $this->connection->insert('extended_service_information', [
            'service_service_id' => $serviceId,
            'esi_notes' => 'a note',
            'esi_notes_url' => 'https://example.com/notes',
            'esi_action_url' => 'https://example.com/action',
            'esi_icon_image' => $iconId,
            'esi_icon_image_alt' => 'alt',
            'graph_id' => $graphTemplateId,
        ]);
        $this->connection->insert('contact_service_relation', ['service_service_id' => $serviceId, 'contact_id' => $contactId]);
        $this->connection->insert('contactgroup_service_relation', ['service_service_id' => $serviceId, 'contactgroup_cg_id' => $contactGroupId]);
        $this->connection->insert('servicegroup_relation', ['service_service_id' => $serviceId, 'host_host_id' => $hostId, 'servicegroup_sg_id' => $serviceGroupId]);
        $this->connection->insert('service_categories_relation', ['service_service_id' => $serviceId, 'sc_id' => $categoryId]);
        $this->connection->insert('service_categories_relation', ['service_service_id' => $serviceId, 'sc_id' => $severityId]);
        $this->connection->insert('traps_service_relation', ['service_id' => $serviceId, 'traps_id' => $trapId]);
        $this->connection->insert('on_demand_macro_service', ['svc_macro_name' => '$_SERVICEPLAIN$', 'svc_macro_value' => 'value', 'is_password' => 0, 'svc_svc_id' => $serviceId]);

        $plainMacroId = (int) $this->connection->lastInsertId();
        $this->connection->insert('on_demand_macro_service', ['svc_macro_name' => '$_SERVICESECRET$', 'svc_macro_value' => 's3cr3t', 'is_password' => 1, 'svc_svc_id' => $serviceId]);
        $passwordMacroId = (int) $this->connection->lastInsertId();
        $inheritedMacroId = $this->connection->fetchOne('SELECT svc_macro_id FROM on_demand_macro_service WHERE svc_svc_id = ?', [$templateId]);
        \assert(is_numeric($inheritedMacroId));
        $inheritedMacroId = (int) $inheritedMacroId;

        $this->login();
        $body = $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId, ['headers' => ['accept' => 'application/json']])->toArray();

        self::assertResponseStatusCodeSame(200);
        $named = static fn (int $id, string $name): array => ['id' => $id, 'name' => $name];
        self::assertSame(
            $this->sortedKeys([
                'id' => $serviceId,
                'name' => 'ping',
                'activated' => true,
                'host' => $named($hostId, $this->tag . '-central'),
                'template' => $named($templateId, 'generic'),
                'check_options' => [
                    'command' => $named($checkCommandId, $this->tag . '-check'),
                    'args' => ['80'],
                    'macros' => [
                        ['id' => $plainMacroId, 'name' => 'PLAIN', 'value' => 'value', 'is_password' => false],
                        // A password macro's value is never returned.
                        ['id' => $passwordMacroId, 'name' => 'SECRET', 'is_password' => true],
                        ['id' => $inheritedMacroId, 'name' => 'INHERITED', 'value' => 'tpl', 'is_password' => false, 'parent' => 'template'],
                    ],
                ],
                'monitoring_engine' => [
                    'check_period' => $named($timePeriodId, $this->tag . '-24x7'),
                    'max_check_attempts' => 3,
                    'normal_check_interval' => 5,
                    'retry_check_interval' => 1,
                    'active_check_enabled' => 'use_default',
                    'passive_check_enabled' => 'use_default',
                    'is_volatile' => 'true',
                    'note_url' => 'https://example.com/notes',
                    'note' => 'a note',
                    'action_url' => 'https://example.com/action',
                    'icon' => ['id' => $iconId, 'name' => 'ping.png', 'url' => '/img/media/' . $this->tag . '/ping.png'],
                    'alt_icon' => 'alt',
                    'geo_coordinates' => '48.85,2.35',
                ],
                'notifications' => [
                    'enabled' => 'true',
                    'use_only_contacts_from_host' => false,
                    'contact_additive_inheritance' => false,
                    'contact_group_additive_inheritance' => false,
                    'contacts' => [$named($contactId, $this->tag . '-contact')],
                    'contact_groups' => [$named($contactGroupId, $this->tag . '-cg')],
                    'interval' => 30,
                    'timeperiod' => $named($timePeriodId, $this->tag . '-24x7'),
                    'options' => ['warning', 'critical'],
                ],
                'advanced' => [
                    'obsess_over_service' => 'use_default',
                    'acknowledgment_timeout' => 15,
                    'check_freshness' => 'use_default',
                    'flap_detection_enabled' => 'use_default',
                    'retain_status_information' => 'use_default',
                    'retain_non_status_information' => 'use_default',
                    'stalking_options' => ['ok', 'critical'],
                    'event_handler_enabled' => 'use_default',
                    'event_handler' => $named($eventHandlerId, $this->tag . '-handler'),
                    'event_handler_args' => ['arg'],
                    'comment' => 'a comment',
                ],
                'service_groups' => [$named($serviceGroupId, $this->tag . '-sg')],
                'categories' => [$named($categoryId, $this->tag . '-cat-' . $categoryId)],
                'severity' => $named($severityId, $this->tag . '-cat-' . $severityId),
                'graph_template' => $named($graphTemplateId, $this->tag . '-graph'),
                'traps' => [['id' => $trapId, 'name' => $this->tag . '-trap', 'vendor' => $named($vendorId, $this->tag . '-vendor')]],
            ]),
            $this->sortedKeys($body),
            'Exactly these keys, nested objects included; unset values (first and recovery delays, freshness and '
                . 'flap thresholds) are left out.',
        );

        // The default JSON-LD representation: the item IRI, and a body matching the resource schema.
        $jsonLd = $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId)->toArray();
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::BASE_ENDPOINT . '/' . $serviceId, $jsonLd['@id']);
        self::assertSame('Service', $jsonLd['@type']);
        self::assertMatchesResourceItemJsonSchema(ServiceResource::class);
    }

    public function testAServiceWithoutOwnValuesLeavesThemOut(): void
    {
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('bare');
        $this->linkServiceToHost($serviceId, $hostId);

        $this->login();
        $body = $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId, ['headers' => ['accept' => 'application/json']])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['activated', 'advanced', 'categories', 'check_options', 'host', 'id', 'monitoring_engine', 'name', 'notifications', 'service_groups', 'traps'],
            array_keys($this->sortedKeys($body)),
            'No template, severity nor graph template key.',
        );
        self::assertSame(['args' => [], 'macros' => []], $body['check_options']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'zero' => ['0'];

        yield 'leading zero' => ['007'];

        yield 'not a number' => ['abc'];
    }

    /**
     * The route only takes positive integers: anything else is a 404 before the provider, never a 500.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIdProvider')]
    public function testItAnswers404ForAnInvalidId(string $id): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItAnswers404ForAServiceNotVisible(): void
    {
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('not-granted');
        $this->linkServiceToHost($serviceId, $hostId);
        $grantedId = $this->insertService('granted');
        $this->linkServiceToHost($grantedId, $hostId);

        $username = 'service-reader-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = :alias', ['alias' => $username]);
        \assert(is_numeric($contactId));
        $groupId = $this->grantServicesByHostTopologyRole((int) $contactId);
        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %s.centreon_acl (group_id, host_id, service_id) VALUES (?, ?, ?)',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
            ),
            [$groupId, $hostId, $grantedId],
        );
        $this->login($username);

        // The not-found body of the other service operations (Delete, Patch).
        $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId);
        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);

        // Same answer as for an id that does not exist at all.
        $this->request('GET', self::BASE_ENDPOINT . '/' . ($grantedId + 1000000));
        self::assertResponseStatusCodeSame(404);
        self::assertJsonEquals(['code' => 0, 'message' => 'One or more services do not exist.']);

        // The granted one is read by the same user.
        $this->request('GET', self::BASE_ENDPOINT . '/' . $grantedId);
        self::assertResponseStatusCodeSame(200);
    }

    public function testItAnswers403WithoutServiceRight(): void
    {
        $hostId = $this->insertHost('central');
        $serviceId = $this->insertService('ping');
        $this->linkServiceToHost($serviceId, $hostId);

        $username = 'no-service-rights-' . $this->tag;
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT . '/' . $serviceId);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(['message' => 'You are not allowed to view services']);

        // The access gate runs before the read: an unknown id is a 403 too, not a 404.
        $this->request('GET', self::BASE_ENDPOINT . '/' . ($serviceId + 1000000));
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private function sortedKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->sortedKeys($item) : $item, $value);
    }

    /**
     * "Configuration > Services > Services by host" read-only, which gives the service read permission.
     *
     * @return int the Access Group granted, to scope real-time ACL rows with
     */
    private function grantServicesByHostTopologyRole(int $contactId): int
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'service-reader-group-' . $this->tag,
            'acl_group_alias' => 'service-reader-group-' . $this->tag,
            'acl_group_activate' => '1',
        ]);
        $groupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $groupId, 'contact_contact_id' => $contactId]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'service-reader-rule-' . $this->tag,
            'acl_topo_alias' => 'service-reader-rule-' . $this->tag,
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
                'access_right' => self::READ_ONLY,
            ]);
        }

        return $groupId;
    }

    private function insertHost(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $this->tag . '-' . $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertService(string $description, string $register = '1', ?int $templateId = null): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => $register,
            'service_activate' => '1',
            'service_template_model_stm_id' => $templateId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }

    private function insertCommand(string $name): int
    {
        $this->connection->insert('command', ['command_name' => $this->tag . '-' . $name, 'command_line' => '$USER1$/check', 'command_type' => 2]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $this->tag . '-' . $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertContact(): int
    {
        $name = $this->tag . '-contact';
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCategory(?int $level): int
    {
        $this->connection->insert('service_categories', ['sc_name' => 'tmp', 'sc_description' => 'tmp', 'level' => $level, 'sc_activate' => '1']);
        $categoryId = (int) $this->connection->lastInsertId();
        $this->connection->update('service_categories', ['sc_name' => $this->tag . '-cat-' . $categoryId], ['sc_id' => $categoryId]);

        return $categoryId;
    }

    private function insertImageFolder(): int
    {
        $this->connection->insert('view_img_dir', ['dir_name' => $this->tag, 'dir_alias' => $this->tag]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(string $name, int $folderId): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imageId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir_relation', ['dir_dir_parent_id' => $folderId, 'img_img_id' => $imageId]);

        return $imageId;
    }
}
