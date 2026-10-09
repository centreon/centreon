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

use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\GetHostProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostNotificationsTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostResourceTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Domain\Repository\ResourceAccessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class GetHostProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/hosts';

    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT . '/1');
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('host'), $pollerId);

        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItReturns404ForAnUnknownId(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/999999');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The route pins `{id}` to `\d+`. A non-numeric id must not match the route (404), never reach
     * the provider — where `Assert::integer($uriVariables['id'])` would throw and surface as a 500.
     * This locks that constraint: dropping it would turn this case into a documented 500.
     */
    public function testItReturns404ForANonNumericId(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT . '/not-a-number');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The single strongest guarantee of the acceptance criteria "response body identical in shape
     * to the CreateHost response": a host is created through the POST endpoint, then fetched through
     * the new GET endpoint, and the two *response* bodies must be equivalent (modulo key ordering).
     * (The POST *request* takes bare ids; it is the two responses — both carrying the richer
     * `{id, name}` objects — that must match.) This exercises the whole findById hydration and the
     * whole GetHostProvider assembly end to end.
     */
    public function testItReturnsTheFullHostDetailIdenticalToTheCreateHostResponse(): void
    {
        $this->login();

        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup($this->uniqueName('group'));
        $categoryId = $this->insertHostCategory($this->uniqueName('Production'));
        $severityId = $this->insertHostSeverity($this->uniqueName('Critical'));
        $timezoneName = $this->uniqueName('Europe/Zone');
        $timezoneId = $this->insertTimezone($timezoneName);
        $timePeriodId = $this->insertTimePeriod($this->uniqueName('24x7'));
        $iconId = $this->createImage('server.png');
        $checkCommandId = $this->insertCommand($this->uniqueName('check'), 2);
        $eventHandlerCommandId = $this->insertCommand($this->uniqueName('eh'), 2);
        $templateId = $this->insertHostTemplate($this->uniqueName('tpl'));
        $parentId = $this->insertHost($this->uniqueName('router'), $pollerId);
        $childId = $this->insertHost($this->uniqueName('vm'), $pollerId);
        $contactId = $this->insertNotificationContact('notified');
        $contactGroupId = $this->insertContactGroup('supervisors');
        $notificationPeriodId = $this->insertTimePeriod($this->uniqueName('notif'));
        $name = $this->uniqueName('server');

        $created = $this->request('POST', self::BASE_ENDPOINT, [
            // Plain JSON on both sides: JSON-LD embeds a random blank-node @id per nested object
            // (/api/.well-known/genid/...) that differs between two responses and is not part of the
            // data contract, so it would defeat a body-to-body comparison.
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => $name,
                'address' => '10.0.0.60',
                'poller_id' => $pollerId,
                'alias' => 'front server',
                'snmp_version' => '2c',
                // Write-only: it must NOT reappear in either response.
                'snmp_community' => 'public',
                'host_group_ids' => [$groupId],
                'category_ids' => [$categoryId],
                'severity_id' => $severityId,
                'timezone_id' => $timezoneId,
                'template_ids' => [$templateId],
                // Leaving it on would boot the legacy deployer; nothing here asserts deployment.
                'create_services_linked_to_templates' => false,
                'parent_host_ids' => [$parentId],
                'child_host_ids' => [$childId],
                'data_processing' => [
                    'check_freshness' => 'true',
                    'freshness_threshold' => 120,
                    'flap_detection_enabled' => 'false',
                    'low_flap_threshold' => 10,
                    'high_flap_threshold' => 60,
                    'event_handler_enabled' => 'true',
                    'event_handler_command_id' => $eventHandlerCommandId,
                    'event_handler_args' => ['-w', '80'],
                    'acknowledgment_timeout' => 15,
                ],
                'scheduling_options' => [
                    'check_timeperiod_id' => $timePeriodId,
                    'max_check_attempts' => 3,
                    'normal_check_interval' => 5,
                    'retry_check_interval' => 1,
                    'active_check_enabled' => 'true',
                    'passive_check_enabled' => 'false',
                ],
                'extended_informations' => [
                    'note_url' => 'https://example.com/notes',
                    'note' => 'a free-text note',
                    'action_url' => 'https://example.com/actions',
                    'icon_id' => $iconId,
                    'alt_icon' => 'server icon',
                    'comment' => 'internal comment',
                    'geo_coordinates' => '48.8566,2.3522',
                ],
                'check_options' => [
                    'command_id' => $checkCommandId,
                    'args' => ['-w', '5'],
                    'macros' => [
                        ['name' => 'community', 'value' => 'public', 'is_password' => false, 'description' => 'SNMP'],
                        ['name' => 'token', 'value' => 's3cr3t', 'is_password' => true],
                    ],
                ],
                // A fully-populated notifications block: contacts, groups, options, period and delays
                // all have to survive the round-trip through the relation tables and the engine's
                // single-letter option format, otherwise the two bodies diverge.
                'notifications' => [
                    'enabled' => 'true',
                    'contacts' => [$contactId],
                    'contact_groups' => [$contactGroupId],
                    'options' => ['down', 'recovery'],
                    'interval' => 30,
                    'timeperiod_id' => $notificationPeriodId,
                    'first_delay' => 10,
                    'recovery_delay' => 20,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $createBody = $created->toArray();

        /** @var int $hostId */
        $hostId = $createBody['id'];

        $fetched = $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId, [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(200);

        $getBody = $fetched->toArray();

        // Secrets are never part of the resource, on either endpoint.
        self::assertArrayNotHasKey('snmp_community', $getBody);

        // A password macro's value is redacted (null), asserted directly so the guarantee does not
        // rely solely on the body-to-body comparison below (which would pass even if both leaked).
        $checkOptions = $getBody['check_options'];
        Assert::isArray($checkOptions);
        /** @var list<array{name: string, value: string|null, is_password: bool}> $macros */
        $macros = $checkOptions['macros'];
        $passwordMacros = array_filter(
            $macros,
            static fn (array $macro): bool => $macro['is_password'],
        );
        self::assertNotEmpty($passwordMacros, 'the fixture must contain a password macro');
        foreach ($passwordMacros as $macro) {
            // A redacted value is null, which the serializer omits, so the key is absent entirely.
            self::assertNull($macro['value'] ?? null, "password macro '{$macro['name']}' value must not be exposed");
        }

        // The whole contract, field for field, matches what CreateHost returned.
        self::assertEqualsCanonicalizing($createBody, $getBody);

        // The default (JSON-LD) representation of this fully-populated host validates against the
        // resource schema.
        $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId);
        self::assertResponseStatusCodeSame(200);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
    }

    /**
     * A host carrying no notification field reads back the neutral default block, never a dropped
     * key or "notifications off", exactly as CreateHost reports it
     * (CreateHostProcessorTest::testItReportsTheDefaultNotificationsWhenTheBlockIsAbsent). The
     * populated case is covered by the round-trip above; this pins the empty case end to end.
     */
    public function testItReturnsTheDefaultNotificationsBlockForAHostWithoutOne(): void
    {
        $this->login();

        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('bare'), $pollerId);

        $response = $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId);
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();

        // With every extended field empty, the whole sub-object is nulled (and so omitted), never
        // emitted as an all-null object the serializer would collapse to an invalid `[]`.
        self::assertArrayNotHasKey('extended_informations', $body);

        /** @var array<string, mixed> $notifications */
        $notifications = $body['notifications'];
        // Drop the hydra/JSON-LD metadata ApiPlatform adds to every nested object.
        $notifications = array_filter($notifications, static fn (string $key): bool => ! str_starts_with($key, '@'), ARRAY_FILTER_USE_KEY);

        self::assertSame(
            [
                'enabled' => 'use_default',
                'contacts' => [],
                'contact_groups' => [],
                'options' => [],
                'contact_additive_inheritance' => false,
                'contact_group_additive_inheritance' => false,
            ],
            $notifications,
        );
    }

    public function testItReturnsTheHostToANonAdminWithinScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('accessible'), $pollerId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadTopologyRole($contactId);
        $this->linkHostToAcl($hostId, $aclGroupId);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($hostId, $response->toArray()['id']);
    }

    public function testItReturns404ForANonAdminWhenTheHostIsOutOfScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('restricted'), $pollerId);

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        // Granted the read permission (so the 403 gate passes) but no centreon_acl row for this host:
        // the host is out of scope and must read as "not found", never leaking its existence.
        $this->grantHostReadTopologyRole($contactId);

        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The GET body reflects the CreateHost response, Cloud included: on a Cloud platform the three
     * on-premise-only pans of the contract are dropped exactly as CreateHostProcessor drops them
     * (CreateHostProcessorTest::testItOmitsTheOnPremiseOnlyDataProcessingFieldsOnCloud,
     * ::testItOmitsTheTriStateFieldsOnACloudPlatform, ::testItDropsTheNotificationsOnACloudPlatform).
     * The host is stored with every on-premise field populated, then read through a provider forced
     * to Cloud: the shaping must happen on read, not leak the stored on-premise values. It is seeded
     * in SQL rather than through POST because the forced provider only lives until the kernel
     * reboots, which the client does between two requests.
     */
    public function testItOmitsTheCloudSensitiveFieldsOnACloudPlatform(): void
    {
        $this->login();

        $pollerId = $this->insertPoller('Central');
        $eventHandlerCommandId = $this->insertCommand($this->uniqueName('eh'), 2);
        $timePeriodId = $this->insertTimePeriod($this->uniqueName('24x7'));
        $contactId = $this->insertNotificationContact('notified');

        // acknowledgment_timeout, the flap settings, event_handler_args, the tri-state checks and
        // the notifications block are all persisted, as an on-premise creation leaves them.
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $this->connection->update('host', [
            'host_check_freshness' => '1',
            'host_freshness_threshold' => 120,
            'host_flap_detection_enabled' => '0',
            'host_low_flap_threshold' => 10,
            'host_high_flap_threshold' => 60,
            'host_event_handler_enabled' => '1',
            'command_command_id2' => $eventHandlerCommandId,
            'command_command_id_arg2' => '!-w!80',
            'host_acknowledgement_timeout' => 15,
            'timeperiod_tp_id' => $timePeriodId,
            'host_max_check_attempts' => 3,
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_notifications_enabled' => '1',
            'host_notification_options' => 'd,r',
            'host_notification_interval' => 30,
        ], ['host_id' => $hostId]);
        $this->connection->insert('contact_host_relation', ['contact_id' => $contactId, 'host_host_id' => $hostId]);

        $this->forceCloudPlatform();

        $fetched = $this->request('GET', self::BASE_ENDPOINT . '/' . $hostId, [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(200);
        $getBody = $fetched->toArray();

        // 1. Notifications: the whole block is dropped on Cloud.
        self::assertArrayNotHasKey('notifications', $getBody);

        // 2. scheduling_options: the tri-state checks are dropped, the platform-agnostic fields stay.
        $schedulingOptions = $getBody['scheduling_options'];
        self::assertIsArray($schedulingOptions);
        self::assertSame(3, $schedulingOptions['max_check_attempts']);
        self::assertArrayNotHasKey('active_check_enabled', $schedulingOptions);
        self::assertArrayNotHasKey('passive_check_enabled', $schedulingOptions);

        // 3. data_processing: the on-premise-only members are nulled (and so omitted); the array
        // member event_handler_args collapses to an empty list; the platform-agnostic fields stay.
        $dataProcessing = $getBody['data_processing'];
        self::assertIsArray($dataProcessing);
        self::assertSame('true', $dataProcessing['check_freshness']);
        self::assertSame(120, $dataProcessing['freshness_threshold']);
        self::assertArrayNotHasKey('acknowledgment_timeout', $dataProcessing);
        self::assertArrayNotHasKey('flap_detection_enabled', $dataProcessing);
        self::assertArrayNotHasKey('low_flap_threshold', $dataProcessing);
        self::assertArrayNotHasKey('high_flap_threshold', $dataProcessing);
        self::assertSame([], $dataProcessing['event_handler_args']);
    }

    private function forceCloudPlatform(): void
    {
        $this->forcePlatform(isCloudPlatform: true);
    }

    /**
     * GetHostProvider shapes its Cloud-sensitive output from its own $isCloudPlatform (bound from
     * IS_CLOUD_PLATFORM), which cannot be flipped per test through the env. Force it by replacing
     * the container's provider with one built with the desired value, reusing its real
     * dependencies. Must run before the request is made (same technique as the sibling
     * CreateHostProcessorTest::forcePlatform()).
     */
    private function forcePlatform(bool $isCloudPlatform): void
    {
        $container = self::getContainer();

        /** @var HostResourceTransformer $transformer */
        $transformer = $container->get(HostResourceTransformer::class);
        /** @var HostNotificationsTransformer $notificationsTransformer */
        $notificationsTransformer = $container->get(HostNotificationsTransformer::class);
        /** @var Security $security */
        $security = $container->get(Security::class);
        /** @var HostRepository $hostRepository */
        $hostRepository = $container->get(HostRepository::class);
        /** @var PollerRepository $pollerRepository */
        $pollerRepository = $container->get(PollerRepository::class);
        /** @var HostGroupRepository $hostGroupRepository */
        $hostGroupRepository = $container->get(HostGroupRepository::class);
        /** @var CommandRepository $commandRepository */
        $commandRepository = $container->get(CommandRepository::class);
        /** @var HostTemplateRepository $hostTemplateRepository */
        $hostTemplateRepository = $container->get(HostTemplateRepository::class);
        /** @var HostCategoryRepository $hostCategoryRepository */
        $hostCategoryRepository = $container->get(HostCategoryRepository::class);
        /** @var HostSeverityRepository $hostSeverityRepository */
        $hostSeverityRepository = $container->get(HostSeverityRepository::class);
        /** @var TimezoneRepository $timezoneRepository */
        $timezoneRepository = $container->get(TimezoneRepository::class);
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = $container->get(MediaRepository::class);
        /** @var MediaUrlGenerator $mediaUrlGenerator */
        $mediaUrlGenerator = $container->get(MediaUrlGenerator::class);
        /** @var TimePeriodRepository $timePeriodRepository */
        $timePeriodRepository = $container->get(TimePeriodRepository::class);
        /** @var ResourceAccessRepository $resourceAccessRepository */
        $resourceAccessRepository = $container->get(ResourceAccessRepository::class);
        /** @var HostMacroTransformer $macroTransformer */
        $macroTransformer = $container->get(HostMacroTransformer::class);
        /** @var InheritedHostMacrosResolver $inheritedHostMacrosResolver */
        $inheritedHostMacrosResolver = $container->get(InheritedHostMacrosResolver::class);

        $container->set(
            GetHostProvider::class,
            new GetHostProvider(
                $transformer,
                $notificationsTransformer,
                $security,
                $hostRepository,
                $pollerRepository,
                $hostGroupRepository,
                $commandRepository,
                $hostTemplateRepository,
                $hostCategoryRepository,
                $hostSeverityRepository,
                $timezoneRepository,
                $mediaRepository,
                $mediaUrlGenerator,
                $timePeriodRepository,
                $resourceAccessRepository,
                $macroTransformer,
                $inheritedHostMacrosResolver,
                $isCloudPlatform,
            ),
        );
    }

    private function insertPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'ns_ip_address' => '127.0.0.1',
            'uid' => random_int(1, \PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function insertHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostCategory(string $name): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostSeverity(string $name): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name, 'level' => 1]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimezone(string $name): int
    {
        $this->connection->insert('timezone', [
            'timezone_name' => $name,
            'timezone_offset' => '+00:00',
            'timezone_dst_offset' => '+00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertNotificationContact(string $prefix): int
    {
        $name = $this->uniqueName($prefix);
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

    private function insertContactGroup(string $prefix): int
    {
        $name = $this->uniqueName($prefix);
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertCommand(string $name, int $type): int
    {
        $this->connection->insert('command', [
            'command_name' => $name,
            'command_line' => '$USER1$/check_ping -H $HOSTADDRESS$',
            'command_type' => $type,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createImage(string $name): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imgId = (int) $this->connection->lastInsertId();

        // MediaRepository::findByIds() inner-joins the directory relation, so an unlinked media row
        // is invisible to it — link it, matching legacy's "every media belongs to a folder".
        $this->connection->insert('view_img_dir', ['dir_name' => 'dir']);
        $dirId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir_relation', [
            'dir_dir_parent_id' => $dirId,
            'img_img_id' => $imgId,
        ]);

        return $imgId;
    }

    private function uniqueName(string $prefix = 'host'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
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
     * Grants the "Configuration > Hosts > Hosts" read-only topology access, the legacy menu role
     * bridged to HostPermissionEnum::CanRead via DbalCredentialTransformer::LEGACY_PERMISSION_MAP
     * (topology_page 60101; access_right = 2 is read-only).
     *
     * @return int the Access Group id, reusable to scope centreon_acl rows
     */
    private function grantHostReadTopologyRole(int $contactId): int
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

        foreach ([6, 601, 60101] as $topologyPage) {
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

        return $aclGroupId;
    }

    private function linkHostToAcl(int $hostId, int $groupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $groupId,
            'host_id' => $hostId,
        ]);
    }
}
