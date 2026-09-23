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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\Security\Infrastructure\Double\FakeAccessGroupRepository;

final class DbalHostRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    private FakeAccessGroupRepository $accessGroupRepository;

    private DbalHostRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->accessGroupRepository = new FakeAccessGroupRepository();

        $this->repository = new DbalHostRepository(
            $this->connection,
            $this->realTimeConnection,
            new DbalHostTransformer(),
            $this->accessGroupRepository,
        );
    }

    public function testItMapsAHostWithItsPollerAndTemplates(): void
    {
        $pollerId = $this->createPoller('Central');
        $templateOneId = $this->createHostTemplate('generic-active-host');
        $templateTwoId = $this->createHostTemplate('generic-passive-host');
        $hostId = $this->createHost('server-01', $pollerId, alias: 'srv01', address: '10.0.0.1');
        $this->linkHostToTemplate($hostId, $templateOneId);
        $this->linkHostToTemplate($hostId, $templateTwoId);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertCount(1, $hosts);
        $host = $hosts[0];
        self::assertSame($hostId, $host->id()->value);
        self::assertSame('server-01', $host->name->value);
        self::assertSame('srv01', $host->alias?->value);
        self::assertSame('10.0.0.1', $host->address->value);
        self::assertTrue($host->activated);
        self::assertSame($pollerId, $host->pollerId->value);
        self::assertEqualsCanonicalizing(
            [$templateOneId, $templateTwoId],
            array_map(static fn (HostTemplateId $id): int => $id->value, iterator_to_array($host->templateIds)),
        );
    }

    public function testItDoesNotDuplicateAHostWithMultipleTemplates(): void
    {
        // The join used to gather template_ids is one-to-many; without the GROUP BY the
        // host would appear once per linked template instead of once overall.
        $pollerId = $this->createPoller('Central');
        $templateOneId = $this->createHostTemplate('template-a');
        $templateTwoId = $this->createHostTemplate('template-b');
        $hostId = $this->createHost('server-02', $pollerId);
        $this->linkHostToTemplate($hostId, $templateOneId);
        $this->linkHostToTemplate($hostId, $templateTwoId);

        self::assertCount(1, iterator_to_array($this->repository->findAll()));
    }

    public function testItTreatsAnEmptyAliasAsNull(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('server-03', $pollerId, alias: '');

        $host = iterator_to_array($this->repository->findAll())[0];

        self::assertNull($host->alias);
    }

    public function testItExcludesHostTemplatesFromTheListing(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHostTemplate('a-template');
        $this->createHost('a-real-host', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertCount(1, $hosts);
        self::assertSame('a-real-host', $hosts[0]->name->value);
    }

    public function testItFiltersByNameUsingLike(): void
    {
        $pollerId = $this->createPoller('Central');
        $matchingId = $this->createHost('web-frontend', $pollerId);
        $this->createHost('database-backend', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withName('front')));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByTemplateId(): void
    {
        $pollerId = $this->createPoller('Central');
        $templateId = $this->createHostTemplate('linux-template');
        $matchingId = $this->createHost('host-with-template', $pollerId);
        $this->linkHostToTemplate($matchingId, $templateId);
        $this->createHost('host-without-template', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withTemplateId($templateId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByGroupId(): void
    {
        $pollerId = $this->createPoller('Central');
        $groupId = $this->createHostGroup('Linux-Servers');
        $matchingId = $this->createHost('grouped-host', $pollerId);
        $this->linkHostToGroup($matchingId, $groupId);
        $this->createHost('ungrouped-host', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withGroupId($groupId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByPollerId(): void
    {
        $pollerOneId = $this->createPoller('Central');
        $pollerTwoId = $this->createPoller('Remote-Poller');
        $matchingId = $this->createHost('on-poller-one', $pollerOneId);
        $this->createHost('on-poller-two', $pollerTwoId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withPollerId($pollerOneId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByActivatedStatus(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('active-host', $pollerId, activated: true);
        $inactiveId = $this->createHost('inactive-host', $pollerId, activated: false);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withActivated(false)));

        self::assertCount(1, $hosts);
        self::assertSame($inactiveId, $hosts[0]->id()->value);
    }

    public function testItPaginatesResults(): void
    {
        $pollerId = $this->createPoller('Central');
        for ($hostNumber = 1; $hostNumber <= 3; $hostNumber++) {
            $this->createHost('host-' . $hostNumber, $pollerId);
        }

        $result = $this->repository->findAll((new HostCriteria())->withPagination(1, 2));

        self::assertInstanceOf(InMemoryPaginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertCount(2, iterator_to_array($result));
    }

    public function testViewerWithNoAccessibleGroupSeesNoHosts(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('any-host', $pollerId);

        $viewerId = new UserId(42);
        // No entry in $this->accessGroupRepository->groupIdsByUserId: the viewer belongs to no group.

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withViewerId($viewerId)));

        self::assertCount(0, $hosts);
    }

    public function testViewerSeesOnlyHostsAccessibleThroughItsGroups(): void
    {
        $pollerId = $this->createPoller('Central');
        $accessibleId = $this->createHost('accessible-host', $pollerId);
        $this->createHost('restricted-host', $pollerId);

        $viewerId = new UserId(43);
        $groupId = 501;
        $this->accessGroupRepository->groupIdsByUserId[$viewerId->value] = [$groupId];
        $this->linkHostToAcl($accessibleId, $groupId);
        // The restricted host is deliberately not linked to any centreon_acl row for this group.

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withViewerId($viewerId)));

        self::assertCount(1, $hosts);
        self::assertSame($accessibleId, $hosts[0]->id()->value);
    }

    public function testNoViewerIdSeesEveryHostRegardlessOfAcl(): void
    {
        // No withViewerId() call: mirrors how the Provider skips ACL scoping entirely for an admin.
        $pollerId = $this->createPoller('Central');
        $this->createHost('unrestricted-host', $pollerId);

        self::assertCount(1, iterator_to_array($this->repository->findAll()));
    }

    public function testAddPersistsTheHostAndItsRelations(): void
    {
        $pollerId = $this->createPoller('Central');
        $groupId = $this->createHostGroup('Linux servers');

        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: new HostAlias('srv01'),
            address: new HostAddress('10.0.0.1'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([new HostGroupId($groupId)], HostGroupId::class),
        );

        $this->repository->add($host);

        self::assertGreaterThan(0, $host->id()->value);

        $hosts = iterator_to_array($this->repository->findAll());
        self::assertCount(1, $hosts);
        $persisted = array_values($hosts)[0];
        self::assertSame('server-01', $persisted->name->value);
        self::assertSame('srv01', $persisted->alias?->value);
        self::assertSame('10.0.0.1', $persisted->address->value);
        self::assertSame($pollerId, $persisted->pollerId->value);
        self::assertSame([$groupId], array_map(static fn (HostGroupId $id): int => $id->value, $persisted->hostGroupIds->toArray()));

        // Every host gets a companion row here, even with no optional field set.
        /** @var int|string $extendedInfoCount */
        $extendedInfoCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM extended_host_information WHERE host_host_id = ?',
            [$host->id()->value],
        );
        self::assertSame(1, (int) $extendedInfoCount);
    }

    public function testAddPersistsExtendedInformations(): void
    {
        $pollerId = $this->createPoller('Central');
        $imgId = $this->createImage('server.png');

        $host = new Host(
            id: null,
            name: new HostName('server-02'),
            alias: null,
            address: new HostAddress('10.0.0.2'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            extendedInformations: new ExtendedInformations(
                noteUrl: 'https://example.com/notes',
                note: 'a free-text note',
                actionUrl: 'https://example.com/actions',
                iconId: new MediaId($imgId),
                altIcon: 'server icon',
                comment: 'internal comment',
                geoCoordinates: GeoCoordinates::fromString('48.8566,2.3522'),
            ),
        );

        $this->repository->add($host);

        $hostRow = $this->connection->fetchAssociative(
            'SELECT host_comment, geo_coords FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($hostRow);
        self::assertSame('internal comment', $hostRow['host_comment']);
        self::assertSame('48.8566,2.3522', $hostRow['geo_coords']);

        $extendedInfoRow = $this->connection->fetchAssociative(
            'SELECT ehi_notes_url, ehi_notes, ehi_action_url, ehi_icon_image, ehi_icon_image_alt
             FROM extended_host_information WHERE host_host_id = ?',
            [$host->id()->value],
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

    public function testItMapsAHostWithoutAnIconToANullIconId(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('no-icon-host', $pollerId);

        $host = iterator_to_array($this->repository->findAll())[0];

        self::assertNull($host->extendedInformations?->iconId);
    }

    public function testItMapsAHostIconIdFromExtendedHostInformation(): void
    {
        $pollerId = $this->createPoller('Central');
        $imgId = $this->createImage('server.png');
        $this->createHost('iconed-host', $pollerId, iconId: $imgId);

        $host = iterator_to_array($this->repository->findAll())[0];

        self::assertNotNull($host->extendedInformations?->iconId);
        self::assertSame($imgId, $host->extendedInformations->iconId->value);
    }

    public function testItStillReturnsAHostMissingItsExtendedInformationRow(): void
    {
        // Legacy always inserts this companion row today, but nothing guarantees every host in
        // every real database has it (pre-existing data, a non-standard insert path). The join
        // must stay a LEFT JOIN so such a host still appears, just without an icon, instead of
        // silently vanishing from the listing.
        $pollerId = $this->createPoller('Central');
        $this->connection->insert('host', [
            'host_name' => 'no-extended-info-host',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();
        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertCount(1, $hosts);
        self::assertSame($hostId, $hosts[0]->id()->value);
        self::assertNull($hosts[0]->extendedInformations?->iconId);
    }

    public function testIsNameUsedByHostOrTemplateFindsAHostByExactName(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('server-01', $pollerId);

        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostName('server-01')));
        self::assertFalse($this->repository->isNameUsedByHostOrTemplate(new HostName('server-02')));
    }

    /**
     * Name uniqueness spans hosts AND host templates in legacy — both share the `host` table
     * and the same uniqueness rule, so a host template with this name must also count as a match.
     */
    public function testIsNameUsedByHostOrTemplateFindsAHostTemplateWithTheSameName(): void
    {
        $this->createHostTemplate('shared-name');

        self::assertTrue($this->repository->isNameUsedByHostOrTemplate(new HostName('shared-name')));
    }

    public function testAddPersistsNotifications(): void
    {
        $pollerId = $this->createPoller('Central');
        $contactId = $this->createContact('notified-contact');
        $contactGroupId = $this->createContactGroup('notified-group');
        $periodId = $this->createTimePeriod('24x7');

        $host = $this->hostWithNotifications($pollerId, new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId($contactId)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId($contactGroupId)], ContactGroupId::class),
            options: [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery],
            interval: 30,
            periodId: new TimePeriodId($periodId),
            firstDelay: 10,
            recoveryDelay: 20,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: true,
        ));

        $this->repository->add($host);

        /** @var array{host_notifications_enabled: string, host_notification_options: string|null,
         *      host_notification_interval: numeric-string|null, timeperiod_tp_id2: numeric-string|null,
         *      host_first_notification_delay: numeric-string|null, host_recovery_notification_delay: numeric-string|null,
         *      contact_additive_inheritance: numeric-string|null, cg_additive_inheritance: numeric-string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_notifications_enabled, host_notification_options, host_notification_interval,
                    timeperiod_tp_id2, host_first_notification_delay, host_recovery_notification_delay,
                    contact_additive_inheritance, cg_additive_inheritance
             FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        self::assertSame('1', $row['host_notifications_enabled']);
        self::assertSame('d,r', $row['host_notification_options']);
        self::assertSame(30, (int) $row['host_notification_interval']);
        self::assertSame($periodId, (int) $row['timeperiod_tp_id2']);
        self::assertSame(10, (int) $row['host_first_notification_delay']);
        self::assertSame(20, (int) $row['host_recovery_notification_delay']);
        self::assertSame(1, (int) $row['contact_additive_inheritance']);
        self::assertSame(1, (int) $row['cg_additive_inheritance']);

        self::assertSame(
            [$contactId],
            $this->linkedIds('SELECT contact_id AS id FROM contact_host_relation WHERE host_host_id = ?', $host->id()->value),
        );
        self::assertSame(
            [$contactGroupId],
            $this->linkedIds('SELECT contactgroup_cg_id AS id FROM contactgroup_host_relation WHERE host_host_id = ?', $host->id()->value),
        );
    }

    /**
     * Legacy writes the Default tri-state and NULL everywhere else for a payload carrying no
     * notification field, and the engine then resolves the directive through the template chain.
     * Writing '0' instead would silently turn notifications off on every such host.
     */
    public function testAddPersistsTheDefaultTriStateWithoutANotificationsBlock(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = $this->hostWithNotifications($pollerId, null);

        $this->repository->add($host);

        /** @var array{host_notifications_enabled: string, host_notification_options: string|null,
         *      host_notification_interval: numeric-string|null, timeperiod_tp_id2: numeric-string|null,
         *      host_first_notification_delay: numeric-string|null, host_recovery_notification_delay: numeric-string|null,
         *      contact_additive_inheritance: numeric-string|null, cg_additive_inheritance: numeric-string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_notifications_enabled, host_notification_options, host_notification_interval,
                    timeperiod_tp_id2, host_first_notification_delay, host_recovery_notification_delay,
                    contact_additive_inheritance, cg_additive_inheritance
             FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        self::assertSame('2', $row['host_notifications_enabled']);
        self::assertNull($row['host_notification_options']);
        self::assertNull($row['host_notification_interval']);
        self::assertNull($row['timeperiod_tp_id2']);
        self::assertNull($row['host_first_notification_delay']);
        self::assertNull($row['host_recovery_notification_delay']);
        self::assertSame(0, (int) $row['contact_additive_inheritance']);
        self::assertSame(0, (int) $row['cg_additive_inheritance']);
    }

    public function testAddWritesNoNotificationOptionForAnEmptyList(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = $this->hostWithNotifications($pollerId, new Notifications(
            enabled: TriStateEnum::False,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
        ));

        $this->repository->add($host);

        /** @var array{host_notifications_enabled: string, host_notification_options: string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_notifications_enabled, host_notification_options FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        self::assertSame('0', $row['host_notifications_enabled']);
        // NULL, not an empty string: legacy leaves the column unset so the engine inherits it
        self::assertNull($row['host_notification_options']);
    }

    /**
     * Covers every letter of the engine's `host_notification_options` format in one go: a missing
     * or wrong mapping silently changes when the engine notifies, and nothing else would catch it.
     */
    public function testAddMapsEveryNotificationOptionToItsEngineLetter(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = $this->hostWithNotifications($pollerId, new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: [
                NotificationOptionEnum::Down,
                NotificationOptionEnum::Unreachable,
                NotificationOptionEnum::Recovery,
                NotificationOptionEnum::Flapping,
                NotificationOptionEnum::DowntimeScheduled,
            ],
        ));

        $this->repository->add($host);

        self::assertSame(
            'd,u,r,f,s',
            $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$host->id()->value]),
        );
    }

    public function testAddPersistsTheNoneNotificationOption(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = $this->hostWithNotifications($pollerId, new Notifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: [NotificationOptionEnum::None],
        ));

        $this->repository->add($host);

        // "none" is a real stored value ('n'), distinct from "no option set" (NULL): it tells the
        // engine to notify on nothing rather than to inherit the option from the template chain
        self::assertSame(
            'n',
            $this->connection->fetchOne('SELECT host_notification_options FROM host WHERE host_id = ?', [$host->id()->value]),
        );
    }

    private function hostWithNotifications(int $pollerId, ?Notifications $notifications): Host
    {
        return new Host(
            id: null,
            name: new HostName('server-notif-' . uniqid()),
            alias: null,
            address: new HostAddress('10.0.0.3'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            notifications: $notifications,
        );
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

    private function createContact(string $name): int
    {
        $unique = $name . '-' . uniqid();
        $this->connection->insert('contact', [
            'contact_name' => $unique,
            'contact_alias' => $unique,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $unique . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createContactGroup(string $name): int
    {
        $unique = $name . '-' . uniqid();
        $this->connection->insert('contactgroup', [
            'cg_name' => $unique,
            'cg_alias' => $unique,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createTimePeriod(string $name): int
    {
        $unique = $name . '-' . uniqid();
        $this->connection->insert('timeperiod', ['tp_name' => $unique, 'tp_alias' => $unique]);

        return (int) $this->connection->lastInsertId();
    }

    private function createPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHost(
        string $name,
        int $pollerId,
        ?string $alias = null,
        string $address = '127.0.0.1',
        bool $activated = true,
        ?int $iconId = null,
    ): int {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_alias' => $alias,
            'host_address' => $address,
            'host_activate' => $activated ? '1' : '0',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        // Every registered host always has a companion row here in practice (see
        // DbalHostRepository::add()'s own comment), so this helper mirrors that by default.
        $this->connection->insert('extended_host_information', [
            'host_host_id' => $hostId,
            'ehi_icon_image' => $iconId,
        ]);

        return $hostId;
    }

    private function linkHostToTemplate(int $hostId, int $templateId): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
        ]);
    }

    private function createHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostToGroup(int $hostId, int $groupId): void
    {
        $this->connection->insert('hostgroup_relation', [
            'host_host_id' => $hostId,
            'hostgroup_hg_id' => $groupId,
        ]);
    }

    private function createImage(string $name): int
    {
        $this->connection->insert('view_img', ['img_name' => $name, 'img_path' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostToAcl(int $hostId, int $groupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $groupId,
            'host_id' => $hostId,
        ]);
    }
}
