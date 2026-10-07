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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostNotificationsTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostResourceTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\PutHostProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Shared\Application\Command\CommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Shared\ApiTestCase;

final class PutHostProcessorTest extends ApiTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The activity log (log_action) lives in centstorage, on the realtime connection.
        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('PUT', $this->endpoint(1), ['json' => []]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($this->uniqueName('server'), $pollerId),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testItUpdatesAHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Linux servers');
        $hostId = $this->insertHost($this->uniqueName('server-old'), $pollerId);
        $newName = $this->uniqueName('server-new');

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $newName,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'id' => $hostId,
            'name' => $newName,
            'address' => '10.0.0.9',
            'activated' => true,
            'poller' => ['id' => $pollerId, 'name' => 'Central'],
            'groups' => [
                ['id' => $groupId, 'name' => 'Linux servers'],
            ],
        ]);

        self::assertSame(
            $newName,
            $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]),
        );
    }

    public function testItReturns404ForAnUnknownHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('PUT', $this->endpoint(999999), [
            'json' => $this->payload($this->uniqueName('server'), $pollerId),
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testItReturns409WhenTheNameIsUsedByAnotherHost(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $otherName = $this->uniqueName('other');
        $this->insertHost($otherName, $pollerId);
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($otherName, $pollerId),
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testItAllowsAHostToKeepItsOwnName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($name, $pollerId),
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testItReturns422ForABlankName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => '   ',
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItChangesTheActivationState(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['activated' => false]);
        self::assertSame(
            '0',
            $this->connection->fetchOne('SELECT host_activate FROM host WHERE host_id = ?', [$hostId]),
        );
    }

    public function testItFlagsBothPollersWhenThePollerChanges(): void
    {
        $this->login();
        $oldPollerId = $this->insertPoller('poller-old');
        $newPollerId = $this->insertPoller('poller-new');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $oldPollerId);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $oldPollerId]);
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $newPollerId]);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($name, $newPollerId),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$newPollerId]));
        self::assertSame('1', $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$oldPollerId]));
    }

    public function testItWritesASingleChangeLineWhenOnlyNonActivationFieldsChange(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        // Stored with host_activate = '1'; the PUT keeps it activated, so only the address changes.
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        // ISO with legacy: a change that does not flip activation writes exactly one "change" line.
        self::assertSame(['c'], $this->logActionTypes($hostId));
    }

    public function testAnUnchangedPutWritesNoActivityLogLine(): void
    {
        // ISO with legacy: resending the host as stored is a no-op, so nothing is logged.
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $contactId = $this->insertNotificationContact('contact');
        $name = $this->uniqueName('server');
        $payload = fn (array $macro): array => [
            ...$this->payload($name, $pollerId),
            'alias' => 'alias',
            'notifications' => [
                'enabled' => 'true',
                'contacts' => [$contactId],
                'options' => ['recovery', 'down'],
                'interval' => 30,
                'first_delay' => 10,
            ],
            'check_options' => ['macros' => [['name' => 'own', 'value' => 'kept', 'is_password' => false, ...$macro]]],
        ];
        $this->request('PUT', $this->endpoint($hostId), ['json' => $payload([])]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['c'], $this->logActionTypes($hostId));
        /** @var int|string $macroId */
        $macroId = $this->connection->fetchOne('SELECT host_macro_id FROM on_demand_macro_host WHERE host_host_id = ?', [$hostId]);

        // The same host again, its macro now addressed by the id it was stored under.
        $this->request('PUT', $this->endpoint($hostId), ['json' => $payload(['id' => (int) $macroId, 'parent' => null])]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['c'], $this->logActionTypes($hostId));
    }

    public function testItWritesADisableAndAChangeLineWhenActivationAndOtherFieldsChange(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        // Stored activated; the PUT turns it off and also changes the address.
        $hostId = $this->insertHost($name, $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => false,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        // ISO with legacy: activation flip + other change writes two lines, a disable and a change.
        self::assertSame(['c', 'disable'], $this->logActionTypes($hostId));
    }

    public function testItRejectsACircularRelation(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('edited');
        $editedId = $this->insertHost($name, $pollerId);
        $childId = $this->insertHost($this->uniqueName('child'), $pollerId);
        $parentId = $this->insertHost($this->uniqueName('parent'), $pollerId);

        // Existing chain edited -> child -> parent. Setting parent as the edited host's parent while
        // keeping child as its child closes the loop edited -> child -> parent -> edited.
        $this->insertParentRelation(childId: $childId, parentId: $editedId);
        $this->insertParentRelation(childId: $parentId, parentId: $childId);

        $this->request('PUT', $this->endpoint($editedId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
                'parent_host_ids' => [$parentId],
                'child_host_ids' => [$childId],
            ],
        ]);

        // The circular-relation conflict names an input field (parent_host_ids), so the
        // InvalidReferenceExceptionListener surfaces it as a 422 validation error, like the create path.
        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAHostReferencingItselfAsParent(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        // Self as parent with no children: exercises the handler's self-reference guard (a 422),
        // not the aggregate's raw assertion (which would be a 500).
        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
                'parent_host_ids' => [$hostId],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The PUT response reflects the CreateHost response, Cloud included: on a Cloud platform the
     * three on-premise-only pans of the contract are dropped exactly as CreateHostProcessor drops
     * them. The platform is forced by swapping the container's processor (the technique the sibling
     * CreateHostProcessorTest::forcePlatform() uses) — only the processor sees Cloud, so the input
     * validator (which reads the compiled IS_CLOUD_PLATFORM) still accepts the on-premise blocks and
     * it is the processor that must drop them on the way out.
     */
    public function testItOmitsTheCloudSensitiveFieldsOnACloudPlatform(): void
    {
        $this->login();
        $this->forceCloudPlatform();

        $pollerId = $this->insertPoller('Central');
        $eventHandlerCommandId = $this->insertCommand($this->uniqueName('eh'), 2);
        $contactId = $this->insertNotificationContact('notified');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);

        $fetched = $this->request('PUT', $this->endpoint($hostId), [
            'headers' => ['accept' => 'application/json'],
            'json' => [
                'name' => $name,
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
                'activated' => true,
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
                    'max_check_attempts' => 3,
                    'active_check_enabled' => 'true',
                    'passive_check_enabled' => 'false',
                ],
                'notifications' => [
                    'enabled' => 'true',
                    'contacts' => [$contactId],
                    'options' => ['down', 'recovery'],
                    'interval' => 30,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $body = $fetched->toArray();

        // 1. Notifications: the whole block is dropped on Cloud.
        self::assertArrayNotHasKey('notifications', $body);

        // 2. scheduling_options: the tri-state checks are dropped, the platform-agnostic fields stay.
        $schedulingOptions = $body['scheduling_options'];
        self::assertIsArray($schedulingOptions);
        self::assertSame(3, $schedulingOptions['max_check_attempts']);
        self::assertArrayNotHasKey('active_check_enabled', $schedulingOptions);
        self::assertArrayNotHasKey('passive_check_enabled', $schedulingOptions);

        // 3. data_processing: the on-premise-only members are nulled (and so omitted); the array
        // member event_handler_args collapses to an empty list; the platform-agnostic fields stay.
        $dataProcessing = $body['data_processing'];
        self::assertIsArray($dataProcessing);
        self::assertSame('true', $dataProcessing['check_freshness']);
        self::assertSame(120, $dataProcessing['freshness_threshold']);
        self::assertArrayNotHasKey('acknowledgment_timeout', $dataProcessing);
        self::assertArrayNotHasKey('flap_detection_enabled', $dataProcessing);
        self::assertArrayNotHasKey('low_flap_threshold', $dataProcessing);
        self::assertArrayNotHasKey('high_flap_threshold', $dataProcessing);
        self::assertSame([], $dataProcessing['event_handler_args']);
    }

    public function testItUpdatesADirectMacroInPlaceKeepingItsIdAndDescription(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        // A description written by the legacy form, which this API neither reads nor writes.
        $macroId = $this->insertMacro($hostId, '$_HOSTOLD$', 'v1', description: 'set by legacy');

        $response = $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'check_options' => ['macros' => [
                    ['id' => $macroId, 'parent' => null, 'name' => 'new', 'value' => 'v2', 'is_password' => false],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [['host_macro_id' => $macroId, 'host_macro_name' => '$_HOSTNEW$', 'host_macro_value' => 'v2', 'description' => 'set by legacy']],
            $this->macroRows($hostId),
        );
        /** @var array{check_options: array{macros: list<array{id: ?int, parent: ?string}>}} $payload */
        $payload = $response->toArray();
        self::assertSame([['id' => $macroId, 'parent' => null]], array_map(
            static fn (array $macro): array => ['id' => $macro['id'], 'parent' => $macro['parent']],
            $payload['check_options']['macros'],
        ));
    }

    public function testItDeletesADirectMacroNoLongerSubmitted(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $keptId = $this->insertMacro($hostId, '$_HOSTKEPT$', 'a');
        $this->insertMacro($hostId, '$_HOSTDROPPED$', 'b');

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'check_options' => ['macros' => [
                    ['id' => $keptId, 'parent' => null, 'name' => 'kept', 'value' => 'a', 'is_password' => false],
                    ['name' => 'added', 'value' => 'c', 'is_password' => false],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $rows = $this->macroRows($hostId);
        self::assertSame(['$_HOSTKEPT$', '$_HOSTADDED$'], array_column($rows, 'host_macro_name'));
        self::assertSame($keptId, $rows[0]['host_macro_id']);
    }

    public function testItKeepsAStoredPasswordWhenItsValueIsNotResent(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $macroId = $this->insertMacro($hostId, '$_HOSTPWD$', 'stored-secret', isPassword: true);

        $response = $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'check_options' => ['macros' => [
                    ['id' => $macroId, 'parent' => null, 'name' => 'pwd', 'value' => null, 'is_password' => true],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('stored-secret', $this->macroRows($hostId)[0]['host_macro_value']);
        /** @var array{check_options: array{macros: list<array<string, mixed>>}} $payload */
        $payload = $response->toArray();
        self::assertArrayNotHasKey('value', $payload['check_options']['macros'][0]);
    }

    public function testAChangedInheritedMacroBecomesADirectMacroWithoutTouchingTheTemplate(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $templateId = $this->insertHostTemplate($this->uniqueName('tpl'));
        $templateMacroId = $this->insertMacro($templateId, '$_HOSTSHARED$', 'tpl-value');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $response = $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'template_ids' => [$templateId],
                'create_services_linked_to_templates' => false,
                'check_options' => ['macros' => [
                    // Every part changed: name, value and password flag.
                    ['id' => $templateMacroId, 'parent' => 'template', 'name' => 'renamed', 'value' => 'host-value', 'is_password' => true],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        // The template's row is left exactly as it was...
        self::assertSame(
            [['host_macro_id' => $templateMacroId, 'host_macro_name' => '$_HOSTSHARED$', 'host_macro_value' => 'tpl-value', 'description' => null]],
            $this->macroRows($templateId),
        );
        // ...and the host got a row of its own.
        $hostRows = $this->macroRows($hostId);
        self::assertCount(1, $hostRows);
        self::assertNotSame($templateMacroId, $hostRows[0]['host_macro_id']);
        self::assertSame('$_HOSTRENAMED$', $hostRows[0]['host_macro_name']);

        // The response lists the host's own macro, then the template macro it still inherits.
        /** @var array{check_options: array{macros: list<array{id: ?int, name: string, parent: ?string}>}} $payload */
        $payload = $response->toArray();
        self::assertSame(
            [
                ['id' => $hostRows[0]['host_macro_id'], 'name' => 'RENAMED', 'parent' => null],
                ['id' => $templateMacroId, 'name' => 'SHARED', 'parent' => 'template'],
            ],
            array_map(
                static fn (array $macro): array => ['id' => $macro['id'], 'name' => $macro['name'], 'parent' => $macro['parent']],
                $payload['check_options']['macros'],
            ),
        );
    }

    public function testAnInheritedMacroIdSentAsADirectOneIsRejected(): void
    {
        // A template's macro lives in the same table as the host's: its id must never be taken for
        // one of the host's own macros.
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $templateId = $this->insertHostTemplate($this->uniqueName('tpl'));
        $templateMacroId = $this->insertMacro($templateId, '$_HOSTSHARED$', 'tpl-value');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'template_ids' => [$templateId],
                'create_services_linked_to_templates' => false,
                'check_options' => ['macros' => [
                    ['id' => $templateMacroId, 'parent' => null, 'name' => 'shared', 'value' => 'host-value', 'is_password' => false],
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('tpl-value', $this->macroRows($templateId)[0]['host_macro_value']);
        self::assertSame([], $this->macroRows($hostId));
    }

    public function testItRejectsAVaultReferenceAsAMacroValue(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'check_options' => ['macros' => [[
                    'name' => 'pwd',
                    'value' => 'secret::hashicorp_vault::monitoring/hosts/other-uuid::_HOSTPWD',
                    'is_password' => true,
                ]]],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItHidesAHostOutsideTheRestrictedViewerScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('server');
        $hostId = $this->insertHost($name, $pollerId);
        // The host is deliberately not linked to the viewer's access group in centreon_acl.
        $this->loginRestrictedViewer(pollerIds: [$pollerId]);

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($this->uniqueName('server'), $pollerId),
        ]);

        // Out of ACL scope reads as not found (no existence leak), and nothing is written.
        self::assertResponseStatusCodeSame(404);
        self::assertSame($name, $this->connection->fetchOne('SELECT host_name FROM host WHERE host_id = ?', [$hostId]));
    }

    public function testARestrictedViewerCannotMoveAHostToAnInaccessiblePoller(): void
    {
        $pollerId = $this->insertPoller('Accessible');
        $inaccessiblePollerId = $this->insertPoller('Inaccessible');
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $this->linkHostToAcl($hostId, $this->loginRestrictedViewer(pollerIds: [$pollerId]));

        $this->request('PUT', $this->endpoint($hostId), [
            'json' => $this->payload($this->uniqueName('server'), $inaccessiblePollerId),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [$pollerId],
            $this->intColumn('SELECT nagios_server_id FROM ns_host_relation WHERE host_host_id = ?', $hostId),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inaccessibleReferences(): iterable
    {
        yield 'host group' => ['host_group_ids', 'group'];

        yield 'category' => ['category_ids', 'category'];

        yield 'severity' => ['severity_id', 'severity'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('inaccessibleReferences')]
    public function testARestrictedViewerCannotReferenceAnInaccessibleResource(string $field, string $kind): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleGroupId = $this->insertHostGroup($this->uniqueName('group'));
        $accessibleCategoryId = $this->insertHostCategory($this->uniqueName('category'));
        $accessibleSeverityId = $this->insertHostSeverity($this->uniqueName('severity'));
        $inaccessibleIds = [
            'group' => $this->insertHostGroup($this->uniqueName('group')),
            'category' => $this->insertHostCategory($this->uniqueName('category')),
            'severity' => $this->insertHostSeverity($this->uniqueName('severity')),
        ];
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        $this->linkHostToAcl($hostId, $this->loginRestrictedViewer(
            pollerIds: [$pollerId],
            hostGroupIds: [$accessibleGroupId],
            hostCategoryIds: [$accessibleCategoryId, $accessibleSeverityId],
        ));

        $value = $kind === 'severity' ? $inaccessibleIds[$kind] : [$inaccessibleIds[$kind]];
        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [...$this->payload($this->uniqueName('server'), $pollerId), $field => $value],
        ]);

        // Unknown and inaccessible are not told apart.
        self::assertResponseStatusCodeSame(422);
    }

    public function testARestrictedViewerRoundTripKeepsOutOfScopeGroupsCategoriesAndSeverity(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleGroupId = $this->insertHostGroup($this->uniqueName('group'));
        $hiddenGroupId = $this->insertHostGroup($this->uniqueName('group'));
        $accessibleCategoryId = $this->insertHostCategory($this->uniqueName('category'));
        $hiddenCategoryId = $this->insertHostCategory($this->uniqueName('category'));
        $hiddenSeverityId = $this->insertHostSeverity($this->uniqueName('severity'));
        $hostId = $this->insertHost($this->uniqueName('server'), $pollerId);
        foreach ([$accessibleGroupId, $hiddenGroupId] as $groupId) {
            $this->connection->insert('hostgroup_relation', ['hostgroup_hg_id' => $groupId, 'host_host_id' => $hostId]);
        }
        foreach ([$accessibleCategoryId, $hiddenCategoryId, $hiddenSeverityId] as $categoryId) {
            $this->connection->insert('hostcategories_relation', ['hostcategories_hc_id' => $categoryId, 'host_host_id' => $hostId]);
        }
        $this->linkHostToAcl($hostId, $this->loginRestrictedViewer(
            pollerIds: [$pollerId],
            hostGroupIds: [$accessibleGroupId],
            hostCategoryIds: [$accessibleCategoryId],
        ));

        // What the viewer can see of the host, sent back unchanged.
        $this->request('PUT', $this->endpoint($hostId), [
            'json' => [
                ...$this->payload($this->uniqueName('server'), $pollerId),
                'host_group_ids' => [$accessibleGroupId],
                'category_ids' => [$accessibleCategoryId],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertEqualsCanonicalizing(
            [$accessibleGroupId, $hiddenGroupId],
            $this->intColumn('SELECT hostgroup_hg_id FROM hostgroup_relation WHERE host_host_id = ?', $hostId),
        );
        self::assertEqualsCanonicalizing(
            [$accessibleCategoryId, $hiddenCategoryId, $hiddenSeverityId],
            $this->intColumn('SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ?', $hostId),
        );
    }

    private function forceCloudPlatform(): void
    {
        $this->forcePlatform(isCloudPlatform: true);
    }

    /**
     * PutHostProcessor shapes its Cloud-sensitive output from its own $isCloudPlatform (bound from
     * IS_CLOUD_PLATFORM), which cannot be flipped per test through the env. Force it by replacing
     * the container's processor with one built with the desired value, reusing its real
     * dependencies. Must run before the request is made (same technique as the sibling
     * CreateHostProcessorTest::forcePlatform()).
     */
    private function forcePlatform(bool $isCloudPlatform): void
    {
        $container = self::getContainer();

        /** @var CommandBus $commandBus */
        $commandBus = $container->get(CommandBus::class);
        /** @var HostResourceTransformer $transformer */
        $transformer = $container->get(HostResourceTransformer::class);
        /** @var Security $security */
        $security = $container->get(Security::class);
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
        /** @var HostRepository $hostRepository */
        $hostRepository = $container->get(HostRepository::class);
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = $container->get(MediaRepository::class);
        /** @var HostNotificationsTransformer $notificationsTransformer */
        $notificationsTransformer = $container->get(HostNotificationsTransformer::class);
        /** @var MediaUrlGenerator $mediaUrlGenerator */
        $mediaUrlGenerator = $container->get(MediaUrlGenerator::class);
        /** @var TimePeriodRepository $timePeriodRepository */
        $timePeriodRepository = $container->get(TimePeriodRepository::class);
        /** @var HostMacroTransformer $macroTransformer */
        $macroTransformer = $container->get(HostMacroTransformer::class);
        /** @var InheritedHostMacrosResolver $inheritedHostMacrosResolver */
        $inheritedHostMacrosResolver = $container->get(InheritedHostMacrosResolver::class);

        $container->set(
            PutHostProcessor::class,
            new PutHostProcessor(
                $commandBus,
                $transformer,
                $security,
                $pollerRepository,
                $hostGroupRepository,
                $commandRepository,
                $hostTemplateRepository,
                $hostCategoryRepository,
                $hostSeverityRepository,
                $timezoneRepository,
                $hostRepository,
                $mediaRepository,
                $notificationsTransformer,
                $mediaUrlGenerator,
                $timePeriodRepository,
                $macroTransformer,
                $inheritedHostMacrosResolver,
                $isCloudPlatform,
            ),
        );
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

    /**
     * The host's activity-log action types, sorted, so a caller can assert the set regardless of
     * insertion order.
     *
     * @return list<string>
     */
    private function logActionTypes(int $hostId): array
    {
        /** @var list<string> $types */
        $types = $this->realTimeConnection->fetchFirstColumn(
            "SELECT action_type FROM log_action WHERE object_id = ? AND object_type = 'host'",
            [$hostId],
        );
        sort($types);

        return $types;
    }

    private function insertParentRelation(int $childId, int $parentId): void
    {
        $this->connection->insert('host_hostparent_relation', [
            'host_host_id' => $childId,
            'host_parent_hp_id' => $parentId,
        ]);
    }

    /**
     * Logs in a non-admin user with read-write access to the host pages, restricted by one ACL
     * resource to the given pollers, host groups and host categories (severities included).
     *
     * @param list<int> $pollerIds
     * @param list<int> $hostGroupIds
     * @param list<int> $hostCategoryIds
     *
     * @return int the viewer's access group, to link hosts to in centreon_acl
     */
    private function loginRestrictedViewer(array $pollerIds, array $hostGroupIds = [], array $hostCategoryIds = []): int
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        /** @var int|string $contactId */
        $contactId = $this->connection->fetchOne('SELECT contact_id FROM contact WHERE contact_alias = ?', [$username]);

        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'put-host-' . $username,
            'acl_group_alias' => 'put-host-' . $username,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', ['acl_group_id' => $aclGroupId, 'contact_contact_id' => (int) $contactId]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'put-host-' . $username,
            'acl_topo_alias' => 'put-host-' . $username,
            'acl_topo_activate' => '1',
        ]);
        $aclTopologyId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_topology_relations', ['acl_group_id' => $aclGroupId, 'acl_topology_id' => $aclTopologyId]);
        foreach ([6, 601, 60101] as $topologyPage) {
            $topologyId = $this->connection->fetchOne('SELECT topology_id FROM topology WHERE topology_page = ?', [$topologyPage]);
            self::assertIsScalar($topologyId, "topology_page {$topologyPage} not found in fixtures");
            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopologyId,
                'access_right' => 1, // read-write
            ]);
        }

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'put-host-' . $username,
            'acl_res_alias' => 'put-host-' . $username,
            'acl_res_activate' => '1',
            'all_hostgroups' => '0',
        ]);
        $aclResourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', ['acl_res_id' => $aclResourceId, 'acl_group_id' => $aclGroupId]);
        foreach ($pollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', ['acl_res_id' => $aclResourceId, 'poller_id' => $pollerId]);
        }
        foreach ($hostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', ['acl_res_id' => $aclResourceId, 'hg_hg_id' => $hostGroupId]);
        }
        foreach ($hostCategoryIds as $hostCategoryId) {
            $this->connection->insert('acl_resources_hc_relations', ['acl_res_id' => $aclResourceId, 'hc_id' => $hostCategoryId]);
        }

        $this->login($username);

        return $aclGroupId;
    }

    private function linkHostToAcl(int $hostId, int $aclGroupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', ['group_id' => $aclGroupId, 'host_id' => $hostId]);
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

    /**
     * @return list<int>
     */
    private function intColumn(string $sql, int $parameter): array
    {
        /** @var list<int|string> $values */
        $values = $this->connection->fetchFirstColumn($sql, [$parameter]);

        return array_map(static fn (int|string $value): int => (int) $value, $values);
    }

    private function insertHostTemplate(string $name): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '0']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMacro(int $hostId, string $name, string $value, bool $isPassword = false, ?string $description = null): int
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : null,
            'description' => $description,
            'host_host_id' => $hostId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return list<array{host_macro_id: int, host_macro_name: string, host_macro_value: string, description: ?string}>
     */
    private function macroRows(int $hostId): array
    {
        /** @var list<array{host_macro_id: int|string, host_macro_name: string, host_macro_value: string, description: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT host_macro_id, host_macro_name, host_macro_value, description
                FROM on_demand_macro_host WHERE host_host_id = ? ORDER BY host_macro_id',
            [$hostId],
        );

        return array_map(
            static fn (array $row): array => ['host_macro_id' => (int) $row['host_macro_id']] + $row,
            $rows,
        );
    }

    private function endpoint(int $id): string
    {
        return '/api/configuration/hosts/' . $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $name, int $pollerId): array
    {
        return [
            'name' => $name,
            'address' => '10.0.0.9',
            'poller_id' => $pollerId,
            'activated' => true,
        ];
    }

    private function uniqueName(string $prefix = 'host'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
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

    private function insertHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

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

        // Every registered host has a companion row in practice; update() UPDATEs it, so mirror that.
        $this->connection->insert('extended_host_information', ['host_host_id' => $hostId]);

        return $hostId;
    }
}
