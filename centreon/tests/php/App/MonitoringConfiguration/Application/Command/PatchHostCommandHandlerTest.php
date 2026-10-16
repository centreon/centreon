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
use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Application\Service\HostMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\HostMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Application\Service\HostTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
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
use App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class PatchHostCommandHandlerTest extends TestCase
{
    private const HOST_ID = 5;
    private const COMMUNITY_REFERENCE = 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY';

    private FakeHostRepository $repository;

    private FakeCommandRepository $commandRepository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private LoggerSpy $logger;

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
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->logger = new LoggerSpy();
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
            new HostRelationsUpdater(
                new FakeHostGroupRepository(),
                new FakeHostTemplateRepository(),
                new FakeHostCategoryRepository(),
                $this->repository,
                new FakeNotificationContactRepository(),
                new FakeContactGroupRepository(),
                $this->resourceAccessRepository,
                new HostTemplateLicenseGuard(new UnlicensedHostTemplates(
                    new HostLicenseLimit(new FakeModuleRepository(), new FakeHostRepository()),
                    $this->hostTemplateRepository,
                )),
            ),
            new HostTemplateServicesCleaner($this->hostTemplateRepository, new FakeServiceRepository(), $this->eventBus),
            new HostMacrosUpdater(
                new InheritedHostMacrosResolver($this->hostTemplateRepository, $this->commandRepository),
                new HostMacroChangesResolver(),
                new FakeStandardMacroRepository(['$_HOSTRESERVED$']),
                $this->vault,
            ),
            new HostMacroSecretsSynchronizer($this->vault, new VaultCredentialWriter($this->vault)),
            $this->vault,
            new VaultCredentialWriter($this->vault),
            new AdditiveInheritanceModeApplier($this->optionRepository),
            $this->eventBus,
            $this->logger,
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
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
    }

    public function testRemovingTheCommunityClearsItsKeyAndKeepsTheEntryWhileAPasswordMacroStillUsesIt(): void
    {
        $this->seedHostWithVaultedCommunity(withPasswordMacro: true);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            snmpCommunity: null,
        ));

        self::assertCount(1, $this->vault->writeManyCalls);
        self::assertSame('uuid-1', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
        self::assertSame([], $this->eventBus->getDispatchedEvents(HostVaultPurgeRequested::class));
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

    /**
     * @param \Closure(mixed): PatchHostCommand $command
     */
    #[DataProvider('textChanges')]
    public function testATextIsReplacedOrCleared(string $path, \Closure $command, mixed $sent, mixed $expected): void
    {
        $this->assertOnlyChanges([$path], $command($sent), $path, $expected);
    }

    /**
     * @return iterable<string, array{string, \Closure, mixed, mixed}>
     */
    public static function textChanges(): iterable
    {
        $alias = static fn (?HostAlias $value): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, alias: $value);

        yield 'alias replaced' => ['alias', $alias, new HostAlias('new alias'), 'new alias'];

        yield 'alias cleared' => ['alias', $alias, null, null];

        $texts = [
            'noteUrl' => ['https://new.example/n', static fn (?string $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(noteUrl: $sent))],
            'note' => ['new note', static fn (?string $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(note: $sent))],
            'actionUrl' => ['https://new.example/a', static fn (?string $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(actionUrl: $sent))],
            'altIcon' => ['new alt', static fn (?string $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(altIcon: $sent))],
            'comment' => ['new comment', static fn (?string $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(comment: $sent))],
        ];
        foreach ($texts as $field => [$value, $command]) {
            yield $field . ' replaced' => ['extendedInformations.' . $field, $command, $value, $value];

            yield $field . ' cleared' => ['extendedInformations.' . $field, $command, null, null];
        }
    }

    /**
     * @param \Closure(mixed): PatchHostCommand $command
     * @param list<string> $alsoChanged
     */
    #[DataProvider('numberAndReferenceChanges')]
    public function testANumberOrAReferenceIsReplacedOrCleared(string $path, \Closure $command, mixed $sent, mixed $expected, array $alsoChanged = []): void
    {
        $this->assertOnlyChanges([$path, ...$alsoChanged], $command($sent), $path, $expected);
    }

    /**
     * @return iterable<string, array{string, \Closure, mixed, mixed, 4?: list<string>}>
     */
    public static function numberAndReferenceChanges(): iterable
    {
        $rows = [
            'timezone' => ['timezoneId', static fn (?TimezoneId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, timezoneId: $sent), new TimezoneId(13), 13],
            'severity' => ['severityId', static fn (?HostSeverityId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, severityId: $sent), new HostSeverityId(5), 5],
            'icon' => ['extendedInformations.iconId', static fn (?MediaId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, extendedInformations: new ExtendedInformationsChanges(iconId: $sent)), new MediaId(18), 18],
            'check time period' => ['schedulingOptions.checkTimeperiodId', static fn (?TimePeriodId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(checkTimeperiodId: $sent)), new TimePeriodId(2), 2],
            'max check attempts' => ['schedulingOptions.maxCheckAttempts', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(maxCheckAttempts: $sent)), 7, 7],
            'normal check interval' => ['schedulingOptions.normalCheckInterval', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(normalCheckInterval: $sent)), 9, 9],
            'retry check interval' => ['schedulingOptions.retryCheckInterval', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(retryCheckInterval: $sent)), 2, 2],
            'acknowledgment timeout' => ['dataProcessing.acknowledgmentTimeout', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(acknowledgmentTimeout: $sent)), 45, 45],
            'freshness threshold' => ['dataProcessing.freshnessThreshold', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(freshnessThreshold: $sent)), 120, 120],
            'low flap threshold' => ['dataProcessing.lowFlapThreshold', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(lowFlapThreshold: $sent)), 20, 20],
            'high flap threshold' => ['dataProcessing.highFlapThreshold', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(highFlapThreshold: $sent)), 80, 80],
            'notification interval' => ['notifications.interval', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(interval: $sent)), 20, 20],
            'notification time period' => ['notifications.periodId', static fn (?TimePeriodId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(periodId: $sent)), new TimePeriodId(2), 2],
            'first notification delay' => ['notifications.firstDelay', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(firstDelay: $sent)), 4, 4],
            'recovery notification delay' => ['notifications.recoveryDelay', static fn (?int $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(recoveryDelay: $sent)), 5, 5],
        ];
        foreach ($rows as $label => [$path, $command, $sent, $expected]) {
            yield $label . ' replaced' => [$path, $command, $sent, $expected];

            yield $label . ' cleared' => [$path, $command, null, null];
        }

        $eventCommand = static fn (?CommandId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(eventHandlerCommandId: $sent));

        yield 'event handler command replaced' => ['dataProcessing.eventHandlerCommandId', $eventCommand, new CommandId(23), 23];

        // The arguments of the event handler stay when its command goes, unlike the ones of the check command.
        yield 'event handler command cleared' => ['dataProcessing.eventHandlerCommandId', $eventCommand, null, null];

        // Removing the check command removes what only makes sense with it: its arguments.
        $checkCommand = static fn (?CommandId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, checkOptions: new CheckOptionsChanges(checkCommandId: $sent));

        yield 'check command replaced' => ['checkOptions.checkCommandId', $checkCommand, new CommandId(21), 21];

        yield 'check command cleared' => ['checkOptions.checkCommandId', $checkCommand, null, null, ['checkOptions.args']];
    }

    /**
     * @param \Closure(TriStateEnum): PatchHostCommand $command
     */
    #[DataProvider('triStateChanges')]
    public function testATriStateTakesEachOfItsValues(string $path, \Closure $command, TriStateEnum $sent): void
    {
        $this->assertOnlyChanges([$path], $command($sent), $path, $sent->value);
    }

    /**
     * @return iterable<string, array{string, \Closure, TriStateEnum}>
     */
    public static function triStateChanges(): iterable
    {
        $fields = [
            'check freshness' => ['dataProcessing.checkFreshness', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(checkFreshness: $sent))],
            'flap detection' => ['dataProcessing.flapDetectionEnabled', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(flapDetectionEnabled: $sent))],
            'event handler' => ['dataProcessing.eventHandlerEnabled', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(eventHandlerEnabled: $sent))],
            'active checks' => ['schedulingOptions.activeCheckEnabled', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(activeCheckEnabled: $sent))],
            'passive checks' => ['schedulingOptions.passiveCheckEnabled', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, schedulingOptions: new SchedulingOptionsChanges(passiveCheckEnabled: $sent))],
            'notifications' => ['notifications.enabled', static fn (TriStateEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(enabled: $sent))],
        ];
        foreach ($fields as $label => [$path, $command]) {
            // The host starts with every one of them on, so "on" is the unchanged case.
            yield $label . ' off' => [$path, $command, TriStateEnum::False];

            yield $label . ' by default' => [$path, $command, TriStateEnum::UseDefault];
        }
    }

    /**
     * @param \Closure(bool): PatchHostCommand $command
     */
    #[DataProvider('booleanChanges')]
    public function testABooleanIsSwitched(string $path, \Closure $command): void
    {
        $this->assertOnlyChanges([$path], $command(false), $path, false);
    }

    /**
     * @return iterable<string, array{string, \Closure}>
     */
    public static function booleanChanges(): iterable
    {
        yield 'activated' => ['activated', static fn (bool $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, activated: $sent)];

        yield 'contact additive inheritance' => ['notifications.contactAdditiveInheritance', static fn (bool $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(contactAdditiveInheritance: $sent))];

        yield 'contact group additive inheritance' => ['notifications.contactGroupAdditiveInheritance', static fn (bool $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, notifications: new NotificationsChanges(contactGroupAdditiveInheritance: $sent))];
    }

    public function testAnSnmpVersionIsReplacedOrCleared(): void
    {
        $command = static fn (?SnmpVersionEnum $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, snmpVersion: $sent);

        $this->assertOnlyChanges(['snmpVersion'], $command(SnmpVersionEnum::Three), 'snmpVersion', '3');
        $this->setUp();
        $this->assertOnlyChanges(['snmpVersion'], $command(null), 'snmpVersion', null);
    }

    /**
     * @param \Closure(list<string>): PatchHostCommand $command
     * @param list<string> $sent
     */
    #[DataProvider('listOfValuesChanges')]
    public function testAListOfValuesIsReplacedOrEmptied(string $path, \Closure $command, array $sent): void
    {
        $this->assertOnlyChanges([$path], $command($sent), $path, $sent);
    }

    /**
     * @return iterable<string, array{string, \Closure, list<string>}>
     */
    public static function listOfValuesChanges(): iterable
    {
        $checkArguments = static fn (array $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, checkOptions: new CheckOptionsChanges(args: array_values(array_filter($sent, is_string(...)))));

        yield 'check arguments replaced' => ['checkOptions.args', $checkArguments, ['x', 'y']];

        yield 'check arguments emptied' => ['checkOptions.args', $checkArguments, []];

        $eventArguments = static fn (array $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, dataProcessing: new DataProcessingChanges(eventHandlerArgs: array_values(array_filter($sent, is_string(...)))));

        yield 'event handler arguments replaced' => ['dataProcessing.eventHandlerArgs', $eventArguments, ['a']];

        yield 'event handler arguments emptied' => ['dataProcessing.eventHandlerArgs', $eventArguments, []];
    }

    /**
     * @param \Closure(mixed): PatchHostCommand $command
     */
    #[DataProvider('requiredFieldChanges')]
    public function testAFieldThatCannotBeClearedIsReplaced(string $path, \Closure $command, mixed $sent, mixed $expected): void
    {
        $this->assertOnlyChanges([$path], $command($sent), $path, $expected);
    }

    /**
     * @return iterable<string, array{string, \Closure, mixed, mixed}>
     */
    public static function requiredFieldChanges(): iterable
    {
        yield 'name' => ['name', static fn (HostName $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, name: $sent), new HostName('renamed'), 'renamed'];

        yield 'address' => ['address', static fn (HostAddress $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, address: $sent), new HostAddress('10.9.9.9'), '10.9.9.9'];

        yield 'poller' => ['pollerId', static fn (PollerId $sent): PatchHostCommand => new PatchHostCommand(id: new HostId(self::HOST_ID), updatedBy: 1, pollerId: $sent), new PollerId(2), 2];
    }

    /**
     * Applies the command to a host that has a value everywhere, and checks that the field named changed to
     * the value expected, and that nothing else did (but what the field carries along).
     *
     * @param list<string> $changedPaths
     */
    private function assertOnlyChanges(array $changedPaths, PatchHostCommand $command, string $path, mixed $expected): void
    {
        $before = $this->flatten($this->seedPopulatedHost());

        $result = ($this->handler)($command);

        $after = $this->flatten($result);
        $actualPaths = $this->changedPaths($before, $after);
        sort($actualPaths);
        sort($changedPaths);
        self::assertSame($changedPaths, $actualPaths, 'the fields that changed');
        self::assertSame($expected, $this->valueAt($after, $path), 'the value of ' . $path);
        self::assertEquals($after, $this->flatten($this->repository->hosts[self::HOST_ID]), 'what is stored');
    }

    /**
     * A host with a value everywhere, and the references an update can move it to.
     */
    private function seedPopulatedHost(): Host
    {
        $this->optionRepository->options['inheritance_mode'] = '1';
        $this->pollerRepository->seed(1);
        $this->pollerRepository->seed(2);
        $this->hostSeverityRepository->hostSeverities[4] = new HostSeverityName('Critical');
        $this->hostSeverityRepository->hostSeverities[5] = new HostSeverityName('Major');
        $this->timezoneRepository->timezones[12] = new Timezone(new TimezoneId(12), new TimezoneName('Europe/Paris'));
        $this->timezoneRepository->timezones[13] = new Timezone(new TimezoneId(13), new TimezoneName('Europe/Berlin'));
        $this->mediaRepository->existingIds = [17, 18];
        $this->timePeriodRepository->existingIds = [1, 2];
        foreach ([20, 21, 22, 23] as $commandId) {
            $this->addCommand($commandId, 'command-' . $commandId);
        }

        return $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: new HostAlias('seed alias'),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            timezoneId: new TimezoneId(12),
            severityId: new HostSeverityId(4),
            extendedInformations: new ExtendedInformations(
                noteUrl: 'https://notes.example/seed',
                note: 'seed note',
                actionUrl: 'https://actions.example/seed',
                iconId: new MediaId(17),
                altIcon: 'seed icon',
                comment: 'seed comment',
                geoCoordinates: new GeoCoordinates('48.85', '2.35'),
            ),
            schedulingOptions: new SchedulingOptions(
                checkTimeperiodId: new TimePeriodId(1),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::True,
            ),
            dataProcessing: new DataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::True,
                eventHandlerEnabled: TriStateEnum::True,
                acknowledgmentTimeout: 30,
                freshnessThreshold: 60,
                lowFlapThreshold: 10,
                highFlapThreshold: 40,
                eventHandlerCommandId: new CommandId(22),
                eventHandlerArgs: ['seed-event-arg'],
            ),
            checkOptions: new CheckOptions(new CommandId(20), ['seed-arg-1', 'seed-arg-2']),
            notifications: new Notifications(
                enabled: TriStateEnum::True,
                contactIds: new Collection([], NotificationContactId::class),
                contactGroupIds: new Collection([], ContactGroupId::class),
                options: [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery],
                interval: 10,
                periodId: new TimePeriodId(1),
                firstDelay: 2,
                recoveryDelay: 3,
                contactAdditiveInheritance: true,
                contactGroupAdditiveInheritance: true,
            ),
        ), self::HOST_ID);
    }

    /**
     * The host as plain values: the value objects, enums and collections are reduced to what they hold.
     */
    private function flatten(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Collection) {
            return array_map($this->flatten(...), array_values($value->toArray()));
        }

        if (\is_array($value)) {
            return array_map($this->flatten(...), $value);
        }

        if (\is_object($value)) {
            $properties = get_object_vars($value);
            if (array_keys($properties) === ['value']) {
                return $this->flatten($properties['value']);
            }

            return array_map($this->flatten(...), $properties);
        }

        return $value;
    }

    /**
     * The paths whose value differs. A list is compared as a whole, an object field by field.
     *
     * @return list<string>
     */
    private function changedPaths(mixed $before, mixed $after, string $prefix = ''): array
    {
        $isObject = static fn (mixed $value): bool => \is_array($value) && ($value === [] || ! array_is_list($value));
        if ($isObject($before) && $isObject($after)) {
            \assert(\is_array($before) && \is_array($after));
            $paths = [];
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $paths = [...$paths, ...$this->changedPaths($before[$key] ?? null, $after[$key] ?? null, $prefix === '' ? (string) $key : $prefix . '.' . $key)];
            }

            return $paths;
        }

        return $before === $after ? [] : [$prefix];
    }

    private function valueAt(mixed $flattened, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            $flattened = \is_array($flattened) ? ($flattened[$key] ?? null) : null;
        }

        return $flattened;
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
