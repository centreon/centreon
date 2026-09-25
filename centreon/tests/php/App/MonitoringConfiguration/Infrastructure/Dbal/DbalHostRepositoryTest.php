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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
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

    public function testAddPersistsTheDataProcessingColumns(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = new Host(
            id: null,
            name: new HostName('server-dp'),
            alias: null,
            address: new HostAddress('10.0.0.2'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            dataProcessing: new DataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::False,
                eventHandlerEnabled: TriStateEnum::UseDefault,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 120,
                lowFlapThreshold: 10,
                highFlapThreshold: 60,
                eventHandlerArgs: ['warn', 'crit'],
            ),
        );

        $this->repository->add($host);

        /** @var array<string, mixed> $row */
        $row = $this->connection->fetchAssociative(
            'SELECT host_check_freshness, host_flap_detection_enabled, host_event_handler_enabled,
                    host_acknowledgement_timeout, host_freshness_threshold, host_low_flap_threshold,
                    host_high_flap_threshold, command_command_id2, command_command_id_arg2
               FROM host WHERE host_id = ?',
            [$host->id()->value],
        );

        // Three-state directives are stored as the DB column values '0'/'1'/'2'.
        self::assertSame('1', $row['host_check_freshness']);
        self::assertSame('0', $row['host_flap_detection_enabled']);
        self::assertSame('2', $row['host_event_handler_enabled']);
        // Integer columns come back as int (enum columns above come back as string); assert without
        // a cast, which PHPStan rejects on a mixed value.
        self::assertSame(15, $row['host_acknowledgement_timeout']);
        self::assertSame(120, $row['host_freshness_threshold']);
        self::assertSame(10, $row['host_low_flap_threshold']);
        self::assertSame(60, $row['host_high_flap_threshold']);
        self::assertNull($row['command_command_id2']);
        self::assertSame('!warn!crit', $row['command_command_id_arg2']);
    }

    public function testAddPersistsTheEventHandlerCommandAndArgs(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->connection->insert('command', [
            'command_id' => 2,
            'command_name' => 'event-handler',
            'command_line' => '$USER1$/handle',
            'command_type' => 2,
            'enable_shell' => '0',
            'command_activate' => '1',
            'command_locked' => '0',
        ]);

        $host = new Host(
            id: null,
            name: new HostName('server-eh'),
            alias: null,
            address: new HostAddress('10.0.0.3'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            dataProcessing: new DataProcessing(
                eventHandlerCommandId: new CommandId(2),
                eventHandlerArgs: ['-w', '80'],
            ),
        );

        $this->repository->add($host);

        /** @var array<string, mixed> $row */
        $row = $this->connection->fetchAssociative(
            'SELECT command_command_id2, command_command_id_arg2 FROM host WHERE host_id = ?',
            [$host->id()->value],
        );

        self::assertSame(2, $row['command_command_id2']);
        // Arguments (validated free of the '!' delimiter upstream) are stored as a plain '!'-join.
        self::assertSame('!-w!80', $row['command_command_id_arg2']);
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

    public function testAddPersistsTheSchedulingOptionsColumns(): void
    {
        $pollerId = $this->createPoller('Central');
        $timePeriodId = $this->createTimePeriod('24x7');

        $host = new Host(
            id: null,
            name: new HostName('server-scheduling'),
            alias: null,
            address: new HostAddress('10.0.0.3'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            schedulingOptions: new SchedulingOptions(
                checkTimeperiodId: new TimePeriodId($timePeriodId),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::False,
            ),
        );

        $this->repository->add($host);

        $row = $this->connection->fetchAssociative(
            'SELECT timeperiod_tp_id, host_max_check_attempts, host_check_interval,
                    host_retry_check_interval, host_active_checks_enabled, host_passive_checks_enabled
               FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        /** @var int|string $timeperiodTpId */
        $timeperiodTpId = $row['timeperiod_tp_id'];
        /** @var int|string $maxCheckAttempts */
        $maxCheckAttempts = $row['host_max_check_attempts'];
        /** @var int|string $checkInterval */
        $checkInterval = $row['host_check_interval'];
        /** @var int|string $retryCheckInterval */
        $retryCheckInterval = $row['host_retry_check_interval'];
        self::assertSame($timePeriodId, (int) $timeperiodTpId);
        self::assertSame(3, (int) $maxCheckAttempts);
        self::assertSame(5, (int) $checkInterval);
        self::assertSame(1, (int) $retryCheckInterval);
        // Three-state directives are stored as the DB column values '0'/'1'/'2'.
        self::assertSame('1', $row['host_active_checks_enabled']);
        self::assertSame('0', $row['host_passive_checks_enabled']);
    }

    public function testAddPersistsTheDefaultSchedulingOptions(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = new Host(
            id: null,
            name: new HostName('server-scheduling-default'),
            alias: null,
            address: new HostAddress('10.0.0.4'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $this->repository->add($host);

        $row = $this->connection->fetchAssociative(
            'SELECT timeperiod_tp_id, host_max_check_attempts, host_check_interval,
                    host_retry_check_interval, host_active_checks_enabled, host_passive_checks_enabled
               FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        self::assertNull($row['timeperiod_tp_id']);
        self::assertNull($row['host_max_check_attempts']);
        self::assertNull($row['host_check_interval']);
        self::assertNull($row['host_retry_check_interval']);
        self::assertSame('2', $row['host_active_checks_enabled']);
        self::assertSame('2', $row['host_passive_checks_enabled']);
    }

    public function testAddPersistsTheCheckCommandAndItsEncodedArguments(): void
    {
        $pollerId = $this->createPoller('Central');
        $commandId = $this->createCheckCommand('check_ping');

        $host = new Host(
            id: null,
            name: new HostName('server-check'),
            alias: null,
            address: new HostAddress('10.0.0.2'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(new CommandId($commandId), ['-H 10.0.0.2', "line1\nline2\tcol\rret"]),
        );

        $this->repository->add($host);

        /** @var array{command_command_id: int, command_command_id_arg1: string} $row */
        $row = $this->connection->fetchAssociative(
            'SELECT command_command_id, command_command_id_arg1 FROM host WHERE host_id = ?',
            [$host->id()->value],
        );

        self::assertSame($commandId, (int) $row['command_command_id']);
        // Bang-joined, with newline/tab/carriage-return stored as #BR#/#T#/#R# (legacy CentreonHost::insert).
        self::assertSame('!-H 10.0.0.2!line1#BR#line2#T#col#R#ret', $row['command_command_id_arg1']);
    }

    public function testAddLeavesTheCheckCommandNullWhenNoneIsSet(): void
    {
        $pollerId = $this->createPoller('Central');

        $host = new Host(
            id: null,
            name: new HostName('server-nocheck'),
            alias: null,
            address: new HostAddress('10.0.0.5'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $this->repository->add($host);

        /** @var array{command_command_id: ?int, command_command_id_arg1: ?string} $row */
        $row = $this->connection->fetchAssociative(
            'SELECT command_command_id, command_command_id_arg1 FROM host WHERE host_id = ?',
            [$host->id()->value],
        );

        self::assertNull($row['command_command_id']);
        self::assertNull($row['command_command_id_arg1']);
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

    public function testAddPersistsTheSnmpAndTimezoneColumns(): void
    {
        $pollerId = $this->createPoller('Central');
        $timezoneId = $this->createTimezone('Europe/Paris');

        $host = new Host(
            id: null,
            name: new HostName('server-snmp'),
            alias: new HostAlias('srv-snmp'),
            address: new HostAddress('10.0.0.1'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('public'),
            timezoneId: new TimezoneId($timezoneId),
        );

        $this->repository->add($host);

        $row = $this->connection->fetchAssociative(
            'SELECT host_snmp_version, host_snmp_community, host_location FROM host WHERE host_id = ?',
            [$host->id()->value],
        );
        self::assertIsArray($row);
        self::assertSame('2c', $row['host_snmp_version']);
        self::assertSame('public', $row['host_snmp_community']);
        // `host_location` is the timezone column, despite its name.
        /** @var int|string $location */
        $location = $row['host_location'];
        self::assertSame($timezoneId, (int) $location);
    }

    public function testAddPersistsAVaultReferenceAsTheSnmpCommunity(): void
    {
        $pollerId = $this->createPoller('Central');
        $path = 'secret::hashicorp_vault::monitoring/hosts/3f2a::_HOSTSNMPCOMMUNITY';

        $host = new Host(
            id: null,
            name: new HostName('server-vault'),
            alias: null,
            address: new HostAddress('10.0.0.2'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: new SnmpCommunity($path),
        );

        $this->repository->add($host);

        self::assertSame($path, $this->connection->fetchOne(
            'SELECT host_snmp_community FROM host WHERE host_id = ?',
            [$host->id()->value],
        ));
    }

    public function testAddPersistsTemplatesInContiguousOrder(): void
    {
        $pollerId = $this->createPoller('Central');
        $firstTemplateId = $this->createHostTemplate('generic-active-host');
        $secondTemplateId = $this->createHostTemplate('generic-passive-host');

        $host = new Host(
            id: null,
            name: new HostName('server-templated'),
            alias: null,
            address: new HostAddress('10.0.0.3'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection(
                [new HostTemplateId($secondTemplateId), new HostTemplateId($firstTemplateId)],
                HostTemplateId::class,
            ),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $this->repository->add($host);

        /** @var list<array{host_tpl_id: int|string, order: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT host_tpl_id, `order` FROM host_template_relation WHERE host_host_id = ? ORDER BY `order`',
            [$host->id()->value],
        );
        self::assertSame(
            [[$secondTemplateId, 0], [$firstTemplateId, 1]],
            array_map(static fn (array $row): array => [(int) $row['host_tpl_id'], (int) $row['order']], $rows),
        );
    }

    public function testAddPersistsCategoriesAndSeverityIntoTheSameTable(): void
    {
        $pollerId = $this->createPoller('Central');
        $categoryId = $this->createHostCategory('Production');
        $severityId = $this->createHostSeverity('Critical', level: 1);

        $host = new Host(
            id: null,
            name: new HostName('server-categorised'),
            alias: null,
            address: new HostAddress('10.0.0.4'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            categoryIds: new Collection([new HostCategoryId($categoryId)], HostCategoryId::class),
            severityId: new HostSeverityId($severityId),
        );

        $this->repository->add($host);

        $linkedIds = $this->intColumn(
            'SELECT hostcategories_hc_id FROM hostcategories_relation WHERE host_host_id = ? ORDER BY hostcategories_hc_id',
            $host->id()->value,
        );
        sort($linkedIds);
        $expected = [$categoryId, $severityId];
        sort($expected);
        self::assertSame($expected, $linkedIds);
    }

    public function testAddPersistsParentAndChildRelationsInBothDirections(): void
    {
        $pollerId = $this->createPoller('Central');
        $parentId = $this->createHost('router-01', $pollerId);
        $childId = $this->createHost('vm-03', $pollerId);

        $host = new Host(
            id: null,
            name: new HostName('server-linked'),
            alias: null,
            address: new HostAddress('10.0.0.5'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: new Collection([new HostId($parentId)], HostId::class),
            childHostIds: new Collection([new HostId($childId)], HostId::class),
        );

        $this->repository->add($host);
        $hostId = $host->id()->value;

        self::assertSame([$parentId], $this->intColumn(
            'SELECT host_parent_hp_id FROM host_hostparent_relation WHERE host_host_id = ?',
            $hostId,
        ));
        self::assertSame([$childId], $this->intColumn(
            'SELECT host_host_id FROM host_hostparent_relation WHERE host_parent_hp_id = ?',
            $hostId,
        ));
    }

    public function testFindNamesByIdsIgnoresHostTemplates(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId);
        $templateId = $this->createHostTemplate('generic-active-host');

        $names = $this->repository->findNamesByIds(
            new Collection([new HostId($hostId), new HostId($templateId)], HostId::class),
        )->toArray();

        self::assertSame([$hostId], array_keys($names));
        self::assertSame('server-01', $names[$hostId]->value);
    }

    public function testFindNamesByIdsReturnsNothingForAnEmptyInput(): void
    {
        self::assertCount(0, $this->repository->findNamesByIds(new Collection([], HostId::class)));
    }

    public function testFindAncestorIdsWalksTheWholeParentChain(): void
    {
        $pollerId = $this->createPoller('Central');
        $grandParentId = $this->createHost('core-router', $pollerId);
        $parentId = $this->createHost('edge-router', $pollerId);
        $childId = $this->createHost('server-01', $pollerId);
        $this->linkHostToParent($parentId, $grandParentId);
        $this->linkHostToParent($childId, $parentId);

        $ancestorIds = $this->ancestorIdsOf($childId);

        // Sorted, and inserted in ancestry order, hence ascending.
        self::assertSame([$grandParentId, $parentId, $childId], $ancestorIds);
    }

    public function testFindAncestorIdsTerminatesOnAnAlreadyLoopedGraph(): void
    {
        $pollerId = $this->createPoller('Central');
        $firstId = $this->createHost('host-a', $pollerId);
        $secondId = $this->createHost('host-b', $pollerId);
        $this->linkHostToParent($firstId, $secondId);
        $this->linkHostToParent($secondId, $firstId);

        self::assertSame([$firstId, $secondId], $this->ancestorIdsOf($firstId));
    }

    public function testFindAncestorIdsReturnsNothingForAnEmptyInput(): void
    {
        self::assertCount(0, $this->repository->findAncestorIds(new Collection([], HostId::class)));
    }

    public function testItReadsTemplatesInInheritanceOrderRatherThanById(): void
    {
        $pollerId = $this->createPoller('Central');
        $firstTemplateId = $this->createHostTemplate('generic-active-host');
        $secondTemplateId = $this->createHostTemplate('generic-passive-host');
        $hostId = $this->createHost('server-01', $pollerId);
        // Inherits from the higher id first, so an id-ordered read would give the wrong answer.
        $this->linkHostToTemplate($hostId, $secondTemplateId, order: 0);
        $this->linkHostToTemplate($hostId, $firstTemplateId, order: 1);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertSame(
            [$secondTemplateId, $firstTemplateId],
            array_map(static fn (HostTemplateId $id): int => $id->value, $hosts[0]->templateIds->toArray()),
        );
    }

    public function testFindOneReturnsNullForANonExistentId(): void
    {
        self::assertNull($this->repository->findOne(new HostId(999999)));
    }

    public function testFindOneReturnsNullForAHostTemplate(): void
    {
        $templateId = $this->createHostTemplate('a-template');

        self::assertNull($this->repository->findOne(new HostId($templateId)));
    }

    public function testFindOneReturnsTheHostWithItsBaseFields(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId, alias: 'srv01', address: '10.0.0.1');

        $host = $this->repository->findOne(new HostId($hostId));

        self::assertNotNull($host);
        self::assertSame($hostId, $host->id()->value);
        self::assertSame('server-01', $host->name->value);
        self::assertSame('srv01', $host->alias?->value);
        self::assertSame($pollerId, $host->pollerId->value);
    }

    public function testFindOneHydratesTheSnmpCommunity(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-snmp', $pollerId);
        $this->connection->update('host', ['host_snmp_community' => 'public'], ['host_id' => $hostId]);

        $host = $this->repository->findOne(new HostId($hostId));

        self::assertNotNull($host);
        self::assertSame('public', $host->snmpCommunity?->value);
    }

    public function testFindOneHydratesTheHostMacros(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-macros', $pollerId);
        $this->insertHostMacro($hostId, '$_HOSTCOMMUNITY$', 'public', isPassword: false, order: 0);
        $this->insertHostMacro($hostId, '$_HOSTTOKEN$', 's3cr3t', isPassword: true, order: 1);

        $host = $this->repository->findOne(new HostId($hostId));

        self::assertNotNull($host);
        self::assertCount(2, $host->checkOptions->macros);
        self::assertSame('COMMUNITY', $host->checkOptions->macros[0]->name->value);
        self::assertSame('public', $host->checkOptions->macros[0]->value);
        self::assertFalse($host->checkOptions->macros[0]->isPassword);
        self::assertSame('TOKEN', $host->checkOptions->macros[1]->name->value);
        self::assertTrue($host->checkOptions->macros[1]->isPassword);
    }

    public function testFindOneReturnsNullWhenTheViewerHasNoAccess(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('restricted-host', $pollerId);
        $viewerId = new UserId(43);
        $this->accessGroupRepository->groupIdsByUserId[$viewerId->value] = [501];
        // Deliberately not linked to any centreon_acl row for this group.

        self::assertNull($this->repository->findOne(new HostId($hostId), $viewerId));
    }

    public function testFindOneReturnsTheHostWhenTheViewerHasAccess(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('accessible-host', $pollerId);
        $viewerId = new UserId(44);
        $groupId = 502;
        $this->accessGroupRepository->groupIdsByUserId[$viewerId->value] = [$groupId];
        $this->linkHostToAcl($hostId, $groupId);

        $host = $this->repository->findOne(new HostId($hostId), $viewerId);

        self::assertNotNull($host);
        self::assertSame($hostId, $host->id()->value);
    }

    public function testRemoveDeletesTheHostRow(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('to-be-deleted', $pollerId);
        $host = $this->repository->findOne(new HostId($hostId));
        self::assertNotNull($host);

        $this->repository->remove($host);

        self::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM host WHERE host_id = ?',
            [$hostId],
        ));
    }

    public function testFindOneRoundTripsExtendedInformationsAndSnmp(): void
    {
        $pollerId = $this->createPoller('Central');
        $timezoneId = $this->createTimezone('Europe/Paris');
        $imgId = $this->createImage('server.png');

        $host = new Host(
            id: null,
            name: new HostName('server-full'),
            alias: new HostAlias('srv-full'),
            address: new HostAddress('10.0.0.9'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('public'),
            timezoneId: new TimezoneId($timezoneId),
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

        $found = $this->repository->findOne(new HostId($host->id()->value));

        self::assertNotNull($found);
        self::assertSame(SnmpVersionEnum::TwoC, $found->snmpVersion);
        self::assertSame('public', $found->snmpCommunity?->value);
        self::assertSame($timezoneId, $found->timezoneId?->value);
        self::assertSame('https://example.com/notes', $found->extendedInformations?->noteUrl);
        self::assertSame('a free-text note', $found->extendedInformations?->note);
        self::assertSame('https://example.com/actions', $found->extendedInformations?->actionUrl);
        self::assertSame($imgId, $found->extendedInformations?->iconId?->value);
        self::assertSame('server icon', $found->extendedInformations?->altIcon);
        self::assertSame('internal comment', $found->extendedInformations?->comment);
        self::assertSame('48.8566', $found->extendedInformations?->geoCoordinates?->latitude);
        self::assertSame('2.3522', $found->extendedInformations?->geoCoordinates?->longitude);
    }

    public function testFindOneRoundTripsSchedulingOptionsAndDataProcessing(): void
    {
        $pollerId = $this->createPoller('Central');
        $timePeriodId = $this->createTimePeriod('24x7');
        $eventHandlerCommandId = $this->createCheckCommand('event-handler');

        $host = new Host(
            id: null,
            name: new HostName('server-scheduling-full'),
            alias: null,
            address: new HostAddress('10.0.0.10'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            schedulingOptions: new SchedulingOptions(
                checkTimeperiodId: new TimePeriodId($timePeriodId),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::False,
            ),
            dataProcessing: new DataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::False,
                eventHandlerEnabled: TriStateEnum::UseDefault,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 120,
                lowFlapThreshold: 10,
                highFlapThreshold: 60,
                eventHandlerCommandId: new CommandId($eventHandlerCommandId),
                eventHandlerArgs: ['warn', "line1\nline2\tcol\rret"],
            ),
        );
        $this->repository->add($host);

        $found = $this->repository->findOne(new HostId($host->id()->value));

        self::assertNotNull($found);
        self::assertSame($timePeriodId, $found->schedulingOptions->checkTimeperiodId?->value);
        self::assertSame(3, $found->schedulingOptions->maxCheckAttempts);
        self::assertSame(5, $found->schedulingOptions->normalCheckInterval);
        self::assertSame(1, $found->schedulingOptions->retryCheckInterval);
        self::assertSame(TriStateEnum::True, $found->schedulingOptions->activeCheckEnabled);
        self::assertSame(TriStateEnum::False, $found->schedulingOptions->passiveCheckEnabled);
        self::assertSame(TriStateEnum::True, $found->dataProcessing->checkFreshness);
        self::assertSame(TriStateEnum::False, $found->dataProcessing->flapDetectionEnabled);
        self::assertSame(TriStateEnum::UseDefault, $found->dataProcessing->eventHandlerEnabled);
        self::assertSame(15, $found->dataProcessing->acknowledgmentTimeout);
        self::assertSame(120, $found->dataProcessing->freshnessThreshold);
        self::assertSame(10, $found->dataProcessing->lowFlapThreshold);
        self::assertSame(60, $found->dataProcessing->highFlapThreshold);
        self::assertSame($eventHandlerCommandId, $found->dataProcessing->eventHandlerCommandId?->value);
        self::assertSame(['warn', "line1\nline2\tcol\rret"], $found->dataProcessing->eventHandlerArgs);
    }

    public function testFindOneDefaultsTriStateColumnsToUseDefaultWhenUnset(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-defaults', $pollerId);

        $found = $this->repository->findOne(new HostId($hostId));

        self::assertNotNull($found);
        self::assertSame(TriStateEnum::UseDefault, $found->schedulingOptions->activeCheckEnabled);
        self::assertSame(TriStateEnum::UseDefault, $found->schedulingOptions->passiveCheckEnabled);
        self::assertNull($found->schedulingOptions->checkTimeperiodId);
        self::assertNull($found->schedulingOptions->maxCheckAttempts);
        self::assertSame([], $found->dataProcessing->eventHandlerArgs);
        self::assertSame([], $found->checkOptions->args);
    }

    public function testFindOneRoundTripsTheCheckCommandAndItsArguments(): void
    {
        $pollerId = $this->createPoller('Central');
        $commandId = $this->createCheckCommand('check_ping');

        $host = new Host(
            id: null,
            name: new HostName('server-checkopts'),
            alias: null,
            address: new HostAddress('10.0.0.11'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(new CommandId($commandId), ['-H 10.0.0.11', "line1\nline2\tcol\rret"]),
        );
        $this->repository->add($host);

        $found = $this->repository->findOne(new HostId($host->id()->value));

        self::assertNotNull($found);
        self::assertSame($commandId, $found->checkOptions->checkCommandId?->value);
        self::assertSame(['-H 10.0.0.11', "line1\nline2\tcol\rret"], $found->checkOptions->args);
    }

    public function testFindOneHydratesCategoriesAndSeverity(): void
    {
        $pollerId = $this->createPoller('Central');
        $categoryId = $this->createHostCategory('Production');
        $severityId = $this->createHostSeverity('Critical', level: 1);

        $host = new Host(
            id: null,
            name: new HostName('server-categorised-findone'),
            alias: null,
            address: new HostAddress('10.0.0.12'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            categoryIds: new Collection([new HostCategoryId($categoryId)], HostCategoryId::class),
            severityId: new HostSeverityId($severityId),
        );
        $this->repository->add($host);

        $found = $this->repository->findOne(new HostId($host->id()->value));

        self::assertNotNull($found);
        self::assertSame(
            [$categoryId],
            array_map(static fn (HostCategoryId $id): int => $id->value, $found->categoryIds->toArray()),
        );
        self::assertSame($severityId, $found->severityId?->value);
    }

    public function testFindOneHydratesParentAndChildHostIds(): void
    {
        $pollerId = $this->createPoller('Central');
        $parentId = $this->createHost('router-findone', $pollerId);
        $childId = $this->createHost('vm-findone', $pollerId);

        $host = new Host(
            id: null,
            name: new HostName('server-linked-findone'),
            alias: null,
            address: new HostAddress('10.0.0.13'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: new Collection([new HostId($parentId)], HostId::class),
            childHostIds: new Collection([new HostId($childId)], HostId::class),
        );
        $this->repository->add($host);

        $found = $this->repository->findOne(new HostId($host->id()->value));

        self::assertNotNull($found);
        self::assertSame(
            [$parentId],
            array_map(static fn (HostId $id): int => $id->value, $found->parentHostIds->toArray()),
        );
        self::assertSame(
            [$childId],
            array_map(static fn (HostId $id): int => $id->value, $found->childHostIds->toArray()),
        );
    }

    /**
     * @return list<int>
     */
    private function ancestorIdsOf(int $hostId): array
    {
        $ids = array_map(
            static fn (HostId $id): int => $id->value,
            $this->repository->findAncestorIds(new Collection([new HostId($hostId)], HostId::class))->toArray(),
        );
        sort($ids);

        return $ids;
    }

    private function createTimezone(string $name): int
    {
        $this->connection->insert('timezone', [
            'timezone_name' => $name . '-' . bin2hex(random_bytes(4)),
            'timezone_offset' => '+00:00',
            'timezone_dst_offset' => '+00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostCategory(string $name): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostSeverity(string $name, int $level): int
    {
        $this->connection->insert('hostcategories', ['hc_name' => $name, 'hc_alias' => $name, 'level' => $level]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostToParent(int $hostId, int $parentId): void
    {
        $this->connection->insert('host_hostparent_relation', [
            'host_host_id' => $hostId,
            'host_parent_hp_id' => $parentId,
        ]);
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

    private function linkHostToTemplate(int $hostId, int $templateId, int $order = 0): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
            '`order`' => $order,
        ]);
    }

    private function createHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function createCheckCommand(string $name): int
    {
        $this->connection->insert('command', [
            'command_name' => $name,
            'command_line' => '$USER1$/check_ping -H $HOSTADDRESS$',
            'command_type' => 2, // check
        ]);

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

    private function createTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostToAcl(int $hostId, int $groupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $groupId,
            'host_id' => $hostId,
        ]);
    }

    private function insertHostMacro(int $hostId, string $name, string $value, bool $isPassword, int $order): void
    {
        $this->connection->insert('on_demand_macro_host', [
            'host_macro_name' => $name,
            'host_macro_value' => $value,
            'is_password' => $isPassword ? 1 : 0,
            'host_host_id' => $hostId,
            'macro_order' => $order,
        ]);
    }
}
