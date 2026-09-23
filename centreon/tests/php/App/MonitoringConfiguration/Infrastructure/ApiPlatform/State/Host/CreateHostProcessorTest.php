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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\App\Shared\ApiTestCase;

final class CreateHostProcessorTest extends ApiTestCase
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
        $this->request('POST', self::BASE_ENDPOINT, ['json' => []]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '127.0.0.1',
                'poller_id' => $pollerId,
            ],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItCreatesAHostWithItsPollerAndHostGroups(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Linux servers');
        $name = $this->uniqueName('server');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.1',
                'poller_id' => $pollerId,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'name' => $name,
            'address' => '10.0.0.1',
            'activated' => true,
            'poller' => ['id' => $pollerId, 'name' => 'Central'],
            'templates' => [],
            'groups' => [
                ['id' => $groupId, 'name' => 'Linux servers'],
            ],
        ]);

        /** @var HostRepository $repository */
        $repository = self::getContainer()->get(HostRepository::class);
        self::assertTrue($repository->isNameUsedByHostOrTemplate(new HostName($name)));
    }

    /**
     * extended_informations is a nested sub-object in the request payload, not a set of flat
     * fields on the root — matches the endpoint's output shape (see MON-208990).
     */
    public function testItCreatesAHostWithExtendedInformations(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $imgId = $this->createImage('server.png');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.20',
                'poller_id' => $pollerId,
                'extended_informations' => [
                    'note_url' => 'https://example.com/notes',
                    'note' => 'a free-text note',
                    'action_url' => 'https://example.com/actions',
                    'icon_id' => $imgId,
                    'alt_icon' => 'server icon',
                    'comment' => 'internal comment',
                    'geo_coordinates' => '48.8566,2.3522',
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'extended_informations' => [
                'note_url' => 'https://example.com/notes',
                'note' => 'a free-text note',
                'action_url' => 'https://example.com/actions',
                'icon' => ['id' => $imgId, 'name' => 'server.png'],
                'alt_icon' => 'server icon',
                'comment' => 'internal comment',
                'geo_coordinates' => '48.8566,2.3522',
            ],
        ]);

        $row = $this->connection->fetchAssociative(
            'SELECT host_comment, geo_coords FROM host WHERE host_name = ?',
            [$name],
        );
        self::assertIsArray($row);
        self::assertSame('internal comment', $row['host_comment']);
        self::assertSame('48.8566,2.3522', $row['geo_coords']);

        $extendedInfoRow = $this->connection->fetchAssociative(
            'SELECT ehi_notes_url, ehi_notes, ehi_action_url, ehi_icon_image, ehi_icon_image_alt
             FROM extended_host_information ehi
             INNER JOIN host h ON h.host_id = ehi.host_host_id
             WHERE h.host_name = ?',
            [$name],
        );
        self::assertIsArray($extendedInfoRow);
        self::assertSame('https://example.com/notes', $extendedInfoRow['ehi_notes_url']);
        self::assertSame('a free-text note', $extendedInfoRow['ehi_notes']);
        self::assertSame('https://example.com/actions', $extendedInfoRow['ehi_action_url']);
        /** @var int|string $iconImage */
        $iconImage = $extendedInfoRow['ehi_icon_image'];
        self::assertSame($imgId, (int) $iconImage);
        self::assertSame('server icon', $extendedInfoRow['ehi_icon_image_alt']);
    }

    /**
     * Every field is null here, and the serializer omits null properties rather than emitting
     * them (same convention as the top-level `icon`/`alias`) — so `extended_informations` itself
     * stays present, but comes back with none of its own sub-keys.
     */
    public function testItCreatesAHostWithoutExtendedInformations(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.21',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $response->toArray()['extended_informations']);
    }

    public function testItRejectsAMalformedGeoCoordinatesInExtendedInformations(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.22',
                'poller_id' => $pollerId,
                'extended_informations' => [
                    'geo_coordinates' => 'not-a-valid-pair',
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAnUnknownIconIdInExtendedInformations(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.23',
                'poller_id' => $pollerId,
                'extended_informations' => [
                    'icon_id' => 999999,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * A repeated host_group_id is tolerated (matches legacy's array_unique(), see
     * CreateHostProcessor), not rejected — but must not produce more than one
     * hostgroup_relation row nor duplicate entries in the response's groups list.
     */
    public function testItDeduplicatesRepeatedHostGroupIds(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Linux servers');
        $name = $this->uniqueName('server');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.15',
                'poller_id' => $pollerId,
                'host_group_ids' => [$groupId, $groupId, $groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'groups' => [
                ['id' => $groupId, 'name' => 'Linux servers'],
            ],
        ]);

        /** @var int $hostId */
        $hostId = $response->toArray()['id'];
        /** @var int|string $relationCount */
        $relationCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM hostgroup_relation WHERE host_host_id = ? AND hostgroup_hg_id = ?',
            [$hostId, $groupId],
        );
        self::assertSame(1, (int) $relationCount);
    }

    public function testItNormalizesSpacesInTheName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '  my host  ',
                'address' => '10.0.0.2',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['name' => 'my_host']);
    }

    public function testItRejectsAModulePrefixedName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '_Module_Foo',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The reserved-prefix check must apply to the same normalized value HostName ends up
     * persisting: leading whitespace is trimmed away before the prefix ever gets a chance to
     * "hide" behind it.
     */
    public function testItRejectsAModulePrefixedNameWithLeadingWhitespace(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '  _Module_Foo',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * A space right after "_Module" also normalizes into the reserved prefix (HostName converts
     * inner spaces to underscores), so it must be rejected too — not just an underscore.
     */
    public function testItRejectsAModulePrefixedNameWithASpaceInsteadOfUnderscore(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '_Module Foo',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Matches legacy's Assertion::unauthorizedCharacters(MonitoringServer::ILLEGAL_CHARACTERS),
     * called by AddHostValidation::assertIsValidName() before this migration.
     */
    public function testItRejectsANameWithAnIllegalCharacter(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => 'host(1)',
                'address' => '10.0.0.3',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The UniqueHostName constraint on CreateHostInput now catches this before the command
     * handler ever runs, surfacing it as a 422 field violation rather than the handler's own 409
     * (still there as the authoritative check, see UniqueHostNameValidator).
     */
    public function testItRejectsADuplicateName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('dup');
        $this->insertHost($name, $pollerId);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.4',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The AccessiblePoller constraint on CreateHostInput now catches this before the command
     * handler ever runs, surfacing it as a 422 field violation rather than the handler's own 404
     * (still there as the authoritative check, see AccessiblePollerValidator).
     */
    public function testItRejectsAnUnknownPoller(): void
    {
        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.5',
                'poller_id' => 999999,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsANonPositivePollerIdWithoutCrashing(): void
    {
        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.5',
                'poller_id' => 0,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsNonIntegerHostGroupIdsWithoutCrashing(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.6',
                'poller_id' => $pollerId,
                'host_group_ids' => ['invalid'],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsNonIntegerHostGroupIdFloatsWithoutCrashing(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.7',
                'poller_id' => $pollerId,
                'host_group_ids' => [5.9],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The AccessibleHostGroups constraint on CreateHostInput now catches this before the command
     * handler ever runs, surfacing it as a 422 field violation rather than the handler's own 404
     * (still there as the authoritative check, see AccessibleHostGroupsValidator).
     */
    public function testItRejectsAnUnknownHostGroup(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.6',
                'poller_id' => $pollerId,
                'host_group_ids' => [999999],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => '',
                'address' => '10.0.0.7',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsAMalformedAddress(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => 'http://10.0.0.8',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * End-to-end check that the ReloadAclEventHandler chain actually fires on a real request —
     * ReloadAclEventHandlerTest already covers its internal branching in isolation with fakes,
     * this only proves the wiring (event fired by the handler, listener registered, real ACL
     * tables touched) is genuinely connected for a non-admin creator.
     */
    public function testItGrantsRealTimeAclAccessAndFlagsTheCreatorsGroupForANonAdminCreator(): void
    {
        $pollerId = $this->insertPoller('Central');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $aclGroupId = $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->connection->update('acl_groups', ['acl_group_changed' => 0], ['acl_group_id' => $aclGroupId]);

        $this->login($username);

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.9',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        /** @var int $hostId */
        $hostId = $response->toArray()['id'];

        /** @var array{group_id: int|string, service_id: int|string|null}|false $aclRow */
        $aclRow = $this->realTimeConnection->fetchAssociative(
            'SELECT group_id, service_id FROM centreon_acl WHERE host_id = ?',
            [$hostId],
        );
        self::assertNotFalse($aclRow, "Expected a centreon_acl row to have been seeded for the creator's group");
        self::assertSame($aclGroupId, (int) $aclRow['group_id']);
        self::assertNull($aclRow['service_id']);

        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT acl_group_changed FROM acl_groups WHERE acl_group_id = ?', [$aclGroupId]);
        self::assertSame(1, (int) $changedFlag);
    }

    public function testItFlagsAllAclResourcesForAnAdminCreator(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->insert('acl_resources', ['acl_res_name' => 'r-' . bin2hex(random_bytes(4)), 'acl_res_alias' => 'r', 'acl_res_activate' => '1', 'changed' => '0']);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.10',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        /** @var int|string $changedFlag */
        $changedFlag = $this->connection->fetchOne('SELECT changed FROM acl_resources WHERE acl_res_id = ?', [$aclResId]);
        self::assertSame(1, (int) $changedFlag);
    }

    /**
     * End-to-end check that FlagPollerChangedEventHandler actually fires on a real request —
     * FlagPollerChangedEventHandlerTest already covers its branching in isolation with fakes,
     * this only proves the wiring (event fired by the handler, listener registered, the real
     * poller row touched) is genuinely connected.
     */
    public function testItFlagsThePollerAsChangedWhenCreatingAHost(): void
    {
        $pollerId = $this->insertPoller('Central');
        $this->connection->update('nagios_server', ['updated' => '0'], ['id' => $pollerId]);
        $this->login();

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.14',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $updatedFlag = $this->connection->fetchOne('SELECT updated FROM nagios_server WHERE id = ?', [$pollerId]);
        self::assertSame('1', $updatedFlag);
    }

    /**
     * A restricted (non-admin) creator referencing a poller outside their own ACL scope gets
     * the same not-found error as a truly nonexistent poller — matching CreateHostCommandHandlerTest's
     * unit coverage of the same rule, exercised here through the real ACL tables end to end.
     * Surfaces as 422 (AccessiblePoller constraint), not 404, since the constraint now catches
     * this before the command handler runs — see testItRejectsAnUnknownPoller.
     */
    public function testARestrictedCreatorCannotReferenceAnInaccessiblePoller(): void
    {
        $accessiblePollerId = $this->insertPoller('Accessible');
        $inaccessiblePollerId = $this->insertPoller('Inaccessible');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$accessiblePollerId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.14',
                'poller_id' => $inaccessiblePollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testARestrictedCreatorCannotReferenceAnInaccessibleHostGroup(): void
    {
        $pollerId = $this->insertPoller('Central');
        $accessibleGroupId = $this->insertHostGroup('Accessible group');
        $inaccessibleGroupId = $this->insertHostGroup('Inaccessible group');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$pollerId]);
        $this->restrictContactToHostGroups($contactId, [$accessibleGroupId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.15',
                'poller_id' => $pollerId,
                'host_group_ids' => [$inaccessibleGroupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The positive counterpart to the two rejection tests above: a restricted creator referencing
     * only what they can actually see still succeeds.
     */
    public function testARestrictedCreatorCanCreateAHostWithinTheirAccessibleScope(): void
    {
        $pollerId = $this->insertPoller('Central');
        $groupId = $this->insertHostGroup('Accessible group');
        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantHostReadAndWriteTopologyRole($contactId);
        $this->restrictContactToPollers($contactId, [$pollerId]);
        $this->restrictContactToHostGroups($contactId, [$groupId]);

        $this->login($username);

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.16',
                'poller_id' => $pollerId,
                'host_group_ids' => [$groupId],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * The Cloud-mandatory-host-groups rule itself lives in the WhenPlatform validation
     * constraint on CreateHostInput::$hostGroupIds (see Shared\Infrastructure\Validator\Constraints\WhenPlatformTest for the
     * Cloud/on-premise branch coverage). WhenPlatformValidator is resolved through Symfony's
     * validator constraint locator, which is compiled once at container build time and cannot be
     * forced to a different IS_CLOUD_PLATFORM value per test — so this suite only exercises the
     * on-premise behavior, which matches this test environment's real, unforced default.
     */
    public function testItAllowsEmptyHostGroupsOnPremise(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.13',
                'poller_id' => $pollerId,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testItCreatesAHostWithNotifications(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $contactId = $this->insertNotificationContact('notified');
        $contactGroupId = $this->insertContactGroup('supervisors');
        $periodId = $this->insertTimePeriod('24x7');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.30',
                'poller_id' => $pollerId,
                'notifications' => [
                    'enabled' => 'true',
                    'contacts' => [$contactId],
                    'contact_groups' => [$contactGroupId],
                    'options' => ['down', 'recovery'],
                    'interval' => 30,
                    'period' => $periodId,
                    'first_delay' => 10,
                    'recovery_delay' => 20,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesResourceItemJsonSchema(HostResource::class);
        self::assertJsonContains([
            'notifications' => [
                'enabled' => 'true',
                'contacts' => [['id' => $contactId]],
                'contact_groups' => [['id' => $contactGroupId]],
                'options' => ['down', 'recovery'],
                'interval' => 30,
                'period' => ['id' => $periodId, 'name' => '24x7'],
                'first_delay' => 10,
                'recovery_delay' => 20,
                'contact_additive_inheritance' => false,
                'contact_group_additive_inheritance' => false,
            ],
        ]);

        /** @var array{host_id: numeric-string, host_notifications_enabled: string, host_notification_options: string|null,
         *      host_notification_interval: numeric-string|null, timeperiod_tp_id2: numeric-string|null,
         *      host_first_notification_delay: numeric-string|null, host_recovery_notification_delay: numeric-string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_id, host_notifications_enabled, host_notification_options, host_notification_interval,
                    timeperiod_tp_id2, host_first_notification_delay, host_recovery_notification_delay
             FROM host WHERE host_name = ?',
            [$name],
        );
        self::assertIsArray($row);
        self::assertSame('1', $row['host_notifications_enabled']);
        self::assertSame('d,r', $row['host_notification_options']);
        self::assertSame(30, (int) $row['host_notification_interval']);
        self::assertSame($periodId, (int) $row['timeperiod_tp_id2']);
        self::assertSame(10, (int) $row['host_first_notification_delay']);
        self::assertSame(20, (int) $row['host_recovery_notification_delay']);

        $hostId = (int) $row['host_id'];
        self::assertSame(
            [$contactId],
            $this->linkedIds('SELECT contact_id AS id FROM contact_host_relation WHERE host_host_id = ?', $hostId),
        );
        self::assertSame(
            [$contactGroupId],
            $this->linkedIds('SELECT contactgroup_cg_id AS id FROM contactgroup_host_relation WHERE host_host_id = ?', $hostId),
        );
    }

    /**
     * The notification columns are written either way, so the response reports the persisted
     * state rather than dropping the key — a client never has to handle two shapes.
     *
     * The Cloud branch (block rejected on input, key omitted from the response) cannot be
     * exercised here: both the WhenPlatform constraint and CreateHostProcessor read
     * IS_CLOUD_PLATFORM through the compiled container, which no test can re-bind per case —
     * same limitation as testItAllowsEmptyHostGroupsOnPremise below.
     */
    public function testItReportsTheDefaultNotificationsWhenTheBlockIsAbsent(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => ['name' => $name, 'address' => '10.0.0.31', 'poller_id' => $pollerId],
        ]);

        self::assertResponseStatusCodeSame(201);
        /** @var array<string, mixed> $notifications */
        $notifications = $response->toArray()['notifications'];
        // drop the hydra/JSON-LD metadata ApiPlatform adds to every nested object
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

        self::assertSame(
            '2',
            $this->connection->fetchOne('SELECT host_notifications_enabled FROM host WHERE host_name = ?', [$name]),
        );
    }

    public function testItLogsTheNotificationFieldsOnCreation(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $contactId = $this->insertNotificationContact('logged');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.32',
                'poller_id' => $pollerId,
                'notifications' => ['enabled' => 'false', 'contacts' => [$contactId], 'options' => ['unreachable']],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        // log_action / log_action_modification live in centstorage, not the configuration database
        $details = $this->realTimeConnection->fetchAllKeyValue(
            'SELECT lam.field_name, lam.field_value
             FROM log_action_modification lam
             INNER JOIN log_action la ON la.action_log_id = lam.action_log_id
             WHERE la.object_name = ?',
            [$name],
        );

        self::assertSame('0', $details['host_notifications_enabled'] ?? null);
        // the engine's letter format, matching the column the log entry is named after
        self::assertSame('u', $details['host_notification_options'] ?? null);
        self::assertSame((string) $contactId, $details['host_cs'] ?? null);
    }

    public function testItDeduplicatesRepeatedContactAndContactGroupIds(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $contactId = $this->insertNotificationContact('repeated');
        $contactGroupId = $this->insertContactGroup('repeated-group');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.33',
                'poller_id' => $pollerId,
                'notifications' => [
                    'contacts' => [$contactId, $contactId],
                    'contact_groups' => [$contactGroupId, $contactGroupId],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        // neither relation table carries a unique index, so a repeated id would otherwise become
        // a duplicate row and notify the same contact twice
        /** @var numeric-string $rawHostId */
        $rawHostId = $this->connection->fetchOne('SELECT host_id FROM host WHERE host_name = ?', [$name]);
        $hostId = (int) $rawHostId;
        self::assertSame(
            [$contactId],
            $this->linkedIds('SELECT contact_id AS id FROM contact_host_relation WHERE host_host_id = ?', $hostId),
        );
        self::assertSame(
            [$contactGroupId],
            $this->linkedIds('SELECT contactgroup_cg_id AS id FROM contactgroup_host_relation WHERE host_host_id = ?', $hostId),
        );
    }

    public function testItRejectsAnUnknownContact(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.34',
                'poller_id' => $pollerId,
                'notifications' => ['contacts' => [2147483647]],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('[notifications.contacts] One or more contacts do not exist or are not accessible.', $this->validationMessage($response));
    }

    public function testItRejectsAnUnknownContactGroup(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.35',
                'poller_id' => $pollerId,
                'notifications' => ['contact_groups' => [2147483647]],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('[notifications.contact_groups] One or more contact groups do not exist or are not accessible.', $this->validationMessage($response));
    }

    public function testItRejectsAnUnknownNotificationPeriod(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.36',
                'poller_id' => $pollerId,
                'notifications' => ['period' => 2147483647],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('[notifications.period] This time period does not exist.', $this->validationMessage($response));
    }

    public function testItRejectsTheNoneOptionCombinedWithAnother(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $response = $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.37',
                'poller_id' => $pollerId,
                'notifications' => ['options' => ['none', 'down']],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('[notifications.options] The "none" notification option cannot be combined with any other option.', $this->validationMessage($response));
    }

    public function testItRejectsAnUnknownNotificationOption(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.38',
                'poller_id' => $pollerId,
                'notifications' => ['options' => ['exploded']],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testItRejectsANegativeNotificationInterval(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $this->uniqueName('host'),
                'address' => '10.0.0.39',
                'poller_id' => $pollerId,
                'notifications' => ['interval' => -1],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Zero is a meaningful value for all three, not an empty one: a 0 interval means "notify
     * once", a 0 delay means "notify immediately".
     */
    public function testItAcceptsZeroForEveryDelayAndInterval(): void
    {
        $this->login();
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.40',
                'poller_id' => $pollerId,
                'notifications' => ['interval' => 0, 'first_delay' => 0, 'recovery_delay' => 0],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['notifications' => ['interval' => 0, 'first_delay' => 0, 'recovery_delay' => 0]]);

        /** @var array{host_notification_interval: numeric-string|null, host_first_notification_delay: numeric-string|null,
         *      host_recovery_notification_delay: numeric-string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_notification_interval, host_first_notification_delay, host_recovery_notification_delay
             FROM host WHERE host_name = ?',
            [$name],
        );
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['host_notification_interval']);
        self::assertSame(0, (int) $row['host_first_notification_delay']);
        self::assertSame(0, (int) $row['host_recovery_notification_delay']);
    }

    /**
     * The shipped `inheritance_mode` is '3', so additive inheritance is off and legacy drops the
     * flags silently — the API must not persist what it was asked for either.
     */
    public function testItDropsTheAdditiveInheritanceFlagsWhenThePlatformOptionIsDisabled(): void
    {
        $this->login();
        $this->connection->executeStatement('DELETE FROM options WHERE `key` = :key', ['key' => 'inheritance_mode']);
        $this->connection->executeStatement(
            'INSERT INTO options (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'inheritance_mode', 'value' => '3'],
        );
        $pollerId = $this->insertPoller('Central');
        $name = $this->uniqueName('host');

        $this->request('POST', self::BASE_ENDPOINT, [
            'json' => [
                'name' => $name,
                'address' => '10.0.0.41',
                'poller_id' => $pollerId,
                'notifications' => [
                    'contact_additive_inheritance' => true,
                    'contact_group_additive_inheritance' => true,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'notifications' => [
                'contact_additive_inheritance' => false,
                'contact_group_additive_inheritance' => false,
            ],
        ]);

        /** @var array{contact_additive_inheritance: numeric-string|null, cg_additive_inheritance: numeric-string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT contact_additive_inheritance, cg_additive_inheritance FROM host WHERE host_name = ?',
            [$name],
        );
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['contact_additive_inheritance']);
        self::assertSame(0, (int) $row['cg_additive_inheritance']);
    }

    /**
     * ApiPlatform reports every violation in one newline-separated "message" string, each line
     * prefixed by the offending property path — there is no per-field "detail" to assert on.
     */
    private function validationMessage(ResponseInterface $response): string
    {
        /** @var array{message?: string} $body */
        $body = $response->toArray(false);

        return $body['message'] ?? '';
    }

    /**
     * @return list<int>
     */
    private function linkedIds(string $sql, int $hostId): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, [$hostId]);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
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

    private function insertTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
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

    private function createImage(string $name): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);
        $imgId = (int) $this->connection->lastInsertId();

        // MediaRepository::findByIds() inner-joins the directory relation, so a media row with
        // no folder is invisible to it (though still visible to the plain existsOne() check used
        // by input validation) — link it, matching legacy's "every media belongs to a folder".
        $this->connection->insert('view_img_dir', ['dir_name' => 'dir']);
        $dirId = (int) $this->connection->lastInsertId();
        $this->connection->insert('view_img_dir_relation', [
            'dir_dir_parent_id' => $dirId,
            'img_img_id' => $imgId,
        ]);

        return $imgId;
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

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        /** @var int|string $contactId */
        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );

        return (int) $contactId;
    }

    /**
     * Grants the "Configuration > Hosts > Hosts" read-write topology access — the legacy menu
     * role bridged to HostPermissionEnum::CanReadAndWrite via
     * DbalCredentialTransformer::LEGACY_PERMISSION_MAP (topology_page 60101). access_right = 1
     * is read-write (2 would be read-only), see DbalCredentialRepository::MENU_ACCESS_READ_WRITE.
     */
    private function grantHostReadAndWriteTopologyRole(int $contactId): int
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'topology-rw-group-' . $contactId,
            'acl_group_alias' => 'topology-rw-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => 'topology-rw-rule-' . $contactId,
            'acl_topo_alias' => 'topology-rw-rule-' . $contactId,
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
                'access_right' => 1, // read-write
            ]);
        }

        return $aclGroupId;
    }

    /**
     * Restricts the contact's resource access to exactly these pollers (via a dedicated ACL
     * resource, separate from the topology-only group), mirroring
     * DbalResourceAccessRepositoryTest::linkContactToAclResourceForPollers().
     *
     * @param list<int> $pollerIds
     */
    private function restrictContactToPollers(int $contactId, array $pollerIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'resource-poller-group-' . $contactId,
            'acl_group_alias' => 'resource-poller-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-poller-' . $contactId,
            'acl_res_alias' => 'resource-poller-' . $contactId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($pollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }
    }

    /**
     * Restricts the contact's resource access to exactly these host groups (no `all_hostgroups`
     * flag), mirroring DbalResourceAccessRepositoryTest::linkContactToAclResourceForHostGroups().
     *
     * @param list<int> $hostGroupIds
     */
    private function restrictContactToHostGroups(int $contactId, array $hostGroupIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'resource-hg-group-' . $contactId,
            'acl_group_alias' => 'resource-hg-group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-hg-' . $contactId,
            'acl_res_alias' => 'resource-hg-' . $contactId,
            'acl_res_activate' => '1',
            'all_hostgroups' => '0',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($hostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', [
                'acl_res_id' => $aclResId,
                'hg_hg_id' => $hostGroupId,
            ]);
        }
    }
}
