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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\CheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\DataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostCommandHandler;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\Timezone;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CheckArgumentsRequireACommandException;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class PatchHostCommandHandlerTest extends TestCase
{
    private const HOST_ID = 5;
    private const COMMUNITY_REFERENCE = 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY';

    private FakeHostRepository $repository;

    private FakeCommandRepository $commandRepository;

    private FakeOptionRepository $optionRepository;

    private FakePollerRepository $pollerRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeTimezoneRepository $timezoneRepository;

    private FakeTimePeriodRepository $timePeriodRepository;

    private FakeMediaRepository $mediaRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private PatchHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->commandRepository = new FakeCommandRepository();
        $this->optionRepository = new FakeOptionRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->timezoneRepository = new FakeTimezoneRepository();
        $this->timePeriodRepository = new FakeTimePeriodRepository();
        $this->mediaRepository = new FakeMediaRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        $this->eventBus = new EventBusSpy();
        $this->handler = new PatchHostCommandHandler(
            $this->repository,
            $this->commandRepository,
            new HostReferencesChecker(
                $this->pollerRepository,
                $this->hostSeverityRepository,
                $this->timezoneRepository,
                $this->timePeriodRepository,
                $this->mediaRepository,
                $this->commandRepository,
                $this->resourceAccessRepository,
            ),
            $this->vault,
            new VaultCredentialWriter($this->vault),
            new AdditiveInheritanceModeApplier($this->optionRepository),
            $this->eventBus,
        );
    }

    public function testItEnablesADisabledHost(): void
    {
        $this->seedHost(activated: false);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            activated: true,
        ));

        self::assertTrue($result->activated);
        self::assertSame([['id' => self::HOST_ID, 'activated' => true]], $this->repository->activationUpdates);
        self::assertSame([], $this->repository->updatedHosts);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostEnabled::class, 1));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostMassChanged::class));

        $dispatched = $this->eventBus->getDispatchedEvents(HostEnabled::class)[0];
        self::assertSame($result, $dispatched->aggregate);
        self::assertSame(1, $dispatched->creatorId);
    }

    public function testItDisablesAnEnabledHost(): void
    {
        $this->seedHost(activated: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            activated: false,
        ));

        self::assertFalse($result->activated);
        self::assertSame([['id' => self::HOST_ID, 'activated' => false]], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDisabled::class, 1));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
    }

    public function testItDoesNothingWhenNothingChanges(): void
    {
        $this->seedHost(activated: true);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            activated: true,
            name: new HostName('server-01'),
        ));

        self::assertSame([], $this->repository->activationUpdates);
        self::assertSame([], $this->repository->updatedHosts);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostMassChanged::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
    }

    public function testItFailsWhenTheHostDoesNotExist(): void
    {
        $this->expectException(HostNotFoundException::class);

        try {
            ($this->handler)(new PatchHostCommand(id: new HostId(404), updatedBy: 1, activated: true));
        } finally {
            self::assertSame([], $this->repository->activationUpdates);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        }
    }

    public function testItHidesAHostOutsideTheViewerAclScope(): void
    {
        $this->seedHost(activated: true);
        $this->repository->accessibleHostIds = [];

        $this->expectException(HostNotFoundException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                activated: false,
                viewerId: new UserId(42),
            ));
        } finally {
            self::assertSame([], $this->repository->activationUpdates);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
        }
    }

    public function testItUpdatesOnlyTheProvidedFields(): void
    {
        $this->seedHost(activated: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            alias: new HostAlias('front'),
        ));

        self::assertSame('front', $result->alias?->value);
        self::assertSame('server-01', $result->name->value);
        self::assertSame('127.0.0.1', $result->address->value);
        self::assertSame([$result], $this->repository->updatedHosts);
        self::assertSame([], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
    }

    public function testNullClearsAnOptionalField(): void
    {
        $this->seedHost(activated: true, alias: 'front');

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            alias: null,
        ));

        self::assertNull($result->alias);
        self::assertSame([$result], $this->repository->updatedHosts);
    }

    public function testActivatingAndChangingAnotherFieldFiresOnlyTheMassChange(): void
    {
        $this->seedHost(activated: false);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            activated: true,
            alias: new HostAlias('front'),
        ));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        self::assertSame([], $this->repository->activationUpdates);
        self::assertTrue($this->repository->updatedHosts[0]->activated);
    }

    public function testItRejectsANameAlreadyUsedByAnotherHostOrTemplate(): void
    {
        $this->seedHost(activated: true);
        $this->repository->add($this->host('server-02', activated: true));

        $this->expectException(HostAlreadyExistsException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                name: new HostName('server-02'),
            ));
        } finally {
            self::assertSame([], $this->repository->updatedHosts);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostMassChanged::class));
        }
    }

    public function testKeepingItsOwnNameIsNotADuplicate(): void
    {
        $this->seedHost(activated: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            name: new HostName('server-01'),
            alias: new HostAlias('front'),
        ));

        self::assertSame('front', $result->alias?->value);
    }

    public function testRenamingAHostOnlyByTheCaseOfItsNameIsNotADuplicate(): void
    {
        $this->seedHost(activated: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            name: new HostName('SERVER-01'),
        ));

        self::assertSame('SERVER-01', $result->name->value);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
    }

    public function testItRejectsANameThatOnlyDiffersByCaseFromAnotherHost(): void
    {
        $this->seedHost(activated: true);
        $this->repository->add($this->host('server-02', activated: true));

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            name: new HostName('SERVER-02'),
        ));
    }

    public function testItAcceptsReferencesThatExist(): void
    {
        $this->seedHost(activated: true);
        $this->pollerRepository->seed(2);
        $this->hostSeverityRepository->hostSeverities[4] = new HostSeverityName('Critical');
        $this->timezoneRepository->timezones[12] = new Timezone(new TimezoneId(12), new TimezoneName('Europe/Paris'));
        $this->mediaRepository->existingIds = [17];
        $this->timePeriodRepository->existingIds = [1];

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            pollerId: new PollerId(2),
            timezoneId: new TimezoneId(12),
            severityId: new HostSeverityId(4),
            extendedInformations: new ExtendedInformationsChanges(iconId: new MediaId(17)),
            schedulingOptions: new SchedulingOptionsChanges(checkTimeperiodId: new TimePeriodId(1)),
            notifications: new NotificationsChanges(periodId: new TimePeriodId(1)),
        ));

        self::assertSame(2, $result->pollerId->value);
        self::assertSame(12, $result->timezoneId?->value);
        self::assertSame(4, $result->severityId?->value);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('unknownReferenceProvider')]
    public function testItRejectsAReferenceThatNoLongerExists(PatchHostCommand $command, string $exception): void
    {
        $this->seedHost(activated: true);

        try {
            ($this->handler)($command);
            self::fail('The reference should have been rejected.');
        } catch (\Throwable $thrown) {
            self::assertInstanceOf($exception, $thrown);
        }

        self::assertSame([], $this->repository->updatedHosts);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostMassChanged::class));
    }

    /**
     * @return iterable<string, array{PatchHostCommand, class-string<\Throwable>}>
     */
    public static function unknownReferenceProvider(): iterable
    {
        $id = new HostId(self::HOST_ID);

        yield 'poller' => [new PatchHostCommand($id, 1, pollerId: new PollerId(99)), PollerNotFoundException::class];

        yield 'severity' => [new PatchHostCommand($id, 1, severityId: new HostSeverityId(99)), HostSeverityNotFoundException::class];

        yield 'timezone' => [new PatchHostCommand($id, 1, timezoneId: new TimezoneId(99)), TimezoneNotFoundException::class];

        yield 'icon' => [new PatchHostCommand($id, 1, extendedInformations: new ExtendedInformationsChanges(iconId: new MediaId(99))), MediaNotFoundException::class];

        yield 'check time period' => [new PatchHostCommand($id, 1, schedulingOptions: new SchedulingOptionsChanges(checkTimeperiodId: new TimePeriodId(99))), TimePeriodNotFoundException::class];

        yield 'notification time period' => [new PatchHostCommand($id, 1, notifications: new NotificationsChanges(periodId: new TimePeriodId(99))), TimePeriodNotFoundException::class];

        yield 'event handler command' => [new PatchHostCommand($id, 1, dataProcessing: new DataProcessingChanges(eventHandlerCommandId: new CommandId(99))), CommandNotFoundException::class];
    }

    public function testAPollerOutsideTheViewerScopeIsReportedAsNotFound(): void
    {
        $this->seedHost(activated: true);
        $this->pollerRepository->seed(2);
        $this->resourceAccessRepository->unrestrictedPollerAccess = false;

        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            pollerId: new PollerId(2),
            viewerId: new UserId(7),
        ));
    }

    public function testASeverityOutsideTheViewerScopeIsReportedAsNotFound(): void
    {
        $this->seedHost(activated: true);
        $this->hostSeverityRepository->hostSeverities[4] = new HostSeverityName('Critical');
        $this->resourceAccessRepository->accessibleHostSeverityIds = new Collection([new HostSeverityId(5)], HostSeverityId::class);

        $this->expectException(HostSeverityNotFoundException::class);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            severityId: new HostSeverityId(4),
            viewerId: new UserId(7),
        ));
    }

    public function testReferencesAreCheckedBeforeTheName(): void
    {
        $this->seedHost(activated: true);
        $this->repository->add($this->host('server-02', activated: true));

        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            name: new HostName('server-02'),
            pollerId: new PollerId(99),
        ));
    }

    public function testMovingAHostCarriesThePreviousPollerOnTheEvent(): void
    {
        $this->seedHost(activated: true);
        $this->pollerRepository->seed(2);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            pollerId: new PollerId(2),
        ));

        self::assertSame(2, $result->pollerId->value);
        $event = $this->eventBus->getDispatchedEvents(HostMassChanged::class)[0];
        self::assertSame(1, $event->previousPollerId?->value);
    }

    public function testAnUnchangedPollerIsNotReportedAsPrevious(): void
    {
        $this->seedHost(activated: true);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            alias: new HostAlias('front'),
        ));

        $event = $this->eventBus->getDispatchedEvents(HostMassChanged::class)[0];
        self::assertNull($event->previousPollerId);
    }

    public function testACentreonMonitoringAgentCommandImposesTheFreshnessSettings(): void
    {
        $this->seedHost(activated: true);
        $this->addCommand(9, 'Centreon-Monitoring-Agent-check', isFromMonitoringConnector: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            dataProcessing: new DataProcessingChanges(
                checkFreshness: TriStateEnum::False,
                freshnessThreshold: 30,
            ),
            checkOptions: new CheckOptionsChanges(checkCommandId: new CommandId(9)),
        ));

        self::assertSame(TriStateEnum::True, $result->dataProcessing->checkFreshness);
        self::assertSame(120, $result->dataProcessing->freshnessThreshold);
    }

    public function testAnOrdinaryCommandLeavesTheFreshnessSettingsAlone(): void
    {
        $this->seedHost(activated: true);
        $this->addCommand(9, 'check_ping');

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            checkOptions: new CheckOptionsChanges(checkCommandId: new CommandId(9)),
        ));

        self::assertSame(9, $result->checkOptions->checkCommandId?->value);
        self::assertSame(TriStateEnum::UseDefault, $result->dataProcessing->checkFreshness);
        self::assertNull($result->dataProcessing->freshnessThreshold);
    }

    public function testArgumentsAloneApplyToTheCommandTheHostAlreadyHas(): void
    {
        $this->addCommand(9, 'check_ping');
        $this->seedHost(activated: true);
        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            checkOptions: new CheckOptionsChanges(checkCommandId: new CommandId(9)),
        ));

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            checkOptions: new CheckOptionsChanges(args: ['-w', '80']),
        ));

        self::assertSame(['-w', '80'], $result->checkOptions->args);
        self::assertSame(9, $result->checkOptions->checkCommandId?->value);
    }

    public function testArgumentsAreRefusedWhenTheHostHasNoCheckCommand(): void
    {
        $this->seedHost(activated: true);

        $this->expectException(CheckArgumentsRequireACommandException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                checkOptions: new CheckOptionsChanges(args: ['-w', '80']),
            ));
        } finally {
            self::assertSame([], $this->repository->updatedHosts);
        }
    }

    public function testRemovingTheCommandClearsItsArguments(): void
    {
        $this->addCommand(9, 'check_ping');
        $this->seedHost(activated: true);
        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            checkOptions: new CheckOptionsChanges(checkCommandId: new CommandId(9), args: ['-w', '80']),
        ));

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            checkOptions: new CheckOptionsChanges(checkCommandId: null),
        ));

        self::assertNull($result->checkOptions->checkCommandId);
        self::assertSame([], $result->checkOptions->args);
    }

    public function testTheAdditiveInheritanceFlagsAreForcedOffWhenTheOptionIsDisabled(): void
    {
        $this->seedHost(activated: true);
        $this->optionRepository->options['inheritance_mode'] = '3';

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            notifications: new NotificationsChanges(
                enabled: TriStateEnum::True,
                contactAdditiveInheritance: true,
                contactGroupAdditiveInheritance: true,
            ),
        ));

        self::assertNotNull($result->notifications);
        self::assertFalse($result->notifications->contactAdditiveInheritance);
        self::assertFalse($result->notifications->contactGroupAdditiveInheritance);
    }

    public function testTheAdditiveInheritanceFlagsAreKeptWhenTheOptionIsEnabled(): void
    {
        $this->seedHost(activated: true);
        $this->optionRepository->options['inheritance_mode'] = '1';

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            notifications: new NotificationsChanges(contactAdditiveInheritance: true),
        ));

        self::assertTrue($result->notifications?->contactAdditiveInheritance);
    }

    public function testANewCommunityIsVaultedUnderTheHostsOwnEntry(): void
    {
        $this->seedHostWithVaultedCommunity();

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: 'new-secret',
        ));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertSame('uuid-1', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTSNMPCOMMUNITY' => 'new-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame(self::COMMUNITY_REFERENCE, $result->snmpCommunity?->value);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class));
    }

    /**
     * The path of a vaulted value only depends on the entry and the key, so a new value written under
     * the host's own entry gives back the reference the host already holds: the update still has to be
     * saved and announced, or the poller keeps running with the old community.
     */
    public function testRewritingAVaultedCommunityIsStillAnUpdate(): void
    {
        $this->seedHostWithVaultedCommunity();

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: 'new-secret',
        ));

        self::assertSame([$result], $this->repository->updatedHosts);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
    }

    public function testACommunityIsStoredAsIsWhenTheVaultIsOff(): void
    {
        $this->seedHost(activated: true);
        $this->vault->vaultEnabled = false;

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: 'public',
        ));

        self::assertSame('public', $result->snmpCommunity?->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAVaultReferenceFromTheCallerIsIgnored(): void
    {
        $this->seedHostWithVaultedCommunity();

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            alias: new HostAlias('front'),
            snmpCommunity: 'secret::vault::monitoring/hosts/other::_HOSTSNMPCOMMUNITY',
        ));

        self::assertSame(self::COMMUNITY_REFERENCE, $result->snmpCommunity?->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testRemovingTheOnlySecretReleasesTheWholeVaultEntry(): void
    {
        $before = $this->seedHostWithVaultedCommunity();

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: null,
        ));

        self::assertNull($result->snmpCommunity);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostVaultPurgeRequested::class, 1));
        $event = $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class)[0];
        self::assertSame([], $event->keys);
        self::assertTrue($event->bestEffort);
        self::assertSame($before, $event->host);
    }

    public function testRemovingTheCommunityKeepsTheEntryWhileAPasswordMacroStillUsesIt(): void
    {
        $this->seedHostWithVaultedCommunity(withPasswordMacro: true);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: null,
        ));

        $event = $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class)[0];
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $event->keys);
        self::assertTrue($event->bestEffort);
    }

    public function testRemovingAPlaintextCommunityReleasesNothing(): void
    {
        $this->seedHost(activated: true, community: 'public');

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: null,
        ));

        self::assertNull($result->snmpCommunity);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class));
    }

    public function testARejectedUpdateLeavesNoSecretInTheVault(): void
    {
        $this->seedHostWithVaultedCommunity();
        $this->repository->add($this->host('server-02', activated: true));

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                name: new HostName('server-02'),
                snmpCommunity: 'new-secret',
            ));
            self::fail('The duplicate name should have been rejected.');
        } catch (HostAlreadyExistsException) {
            self::assertSame([], $this->vault->writeManyCalls);
            self::assertSame([], $this->repository->updatedHosts);
        }
    }

    public function testAVaultFailureSavesNothing(): void
    {
        $this->seedHostWithVaultedCommunity();
        $this->vault->writeThrows = true;

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                updatedBy: 1,
                alias: new HostAlias('front'),
                snmpCommunity: 'new-secret',
            ));
            self::fail('The vault failure should have been reported.');
        } catch (VaultWriteFailedException $exception) {
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            self::assertSame([], $this->repository->updatedHosts);
            self::assertSame([], $this->eventBus->getDispatchedEvents(HostMassChanged::class));
        }
    }

    private function seedHostWithVaultedCommunity(bool $withPasswordMacro = false): Host
    {
        $this->vault->extractedUuids[self::COMMUNITY_REFERENCE] = 'uuid-1';
        $macros = [];
        if ($withPasswordMacro) {
            $reference = 'secret::vault::monitoring/hosts/uuid-1::_HOSTTOKEN';
            $this->vault->extractedUuids[$reference] = 'uuid-1';
            $macros[] = new HostMacro(new HostMacroName('TOKEN'), $reference, isPassword: true);
        }

        return $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: new SnmpCommunity(self::COMMUNITY_REFERENCE),
            checkOptions: new CheckOptions(null, macros: $macros),
        ), self::HOST_ID);
    }

    private function seedHost(bool $activated, ?string $alias = null, ?string $community = null): Host
    {
        return $this->repository->seed($this->host('server-01', $activated, $alias, $community), self::HOST_ID);
    }

    private function host(string $name, bool $activated, ?string $alias = null, ?string $community = null): Host
    {
        return new Host(
            id: null,
            name: new HostName($name),
            alias: $alias !== null ? new HostAlias($alias) : null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpCommunity: $community !== null ? new SnmpCommunity($community) : null,
        );
    }

    private function addCommand(int $id, string $name, bool $isFromMonitoringConnector = false): void
    {
        $this->commandRepository->commands[$id] = new Command(
            new CommandId($id),
            new CommandName($name),
            CommandTypeEnum::Check,
            new CommandLine('$USER1$/check_ping -H $HOSTADDRESS$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: $isFromMonitoringConnector,
            connector: null,
            comment: null,
        );
    }
}
