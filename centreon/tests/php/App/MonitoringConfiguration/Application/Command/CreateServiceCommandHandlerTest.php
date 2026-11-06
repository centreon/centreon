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

use App\MonitoringConfiguration\Application\Command\CreateServiceCommand;
use App\MonitoringConfiguration\Application\Command\CreateServiceCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendor;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use App\MonitoringConfiguration\Domain\Event\ServiceCreated;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\EventHandlerArgumentsRequireAHandlerException;
use App\MonitoringConfiguration\Domain\Exception\GraphTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ReservedServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckArgumentsPartlyFilledException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckCommandMissingException;
use App\MonitoringConfiguration\Domain\Exception\ServiceExtendedInformationsTooLongException;
use App\MonitoringConfiguration\Domain\Exception\ServiceGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\ServiceSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TrapNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use App\Shared\Domain\VaultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTrapRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class CreateServiceCommandHandlerTest extends KernelTestCase
{
    private const HOST_ID = 10;
    private const POLLER_ID = 3;
    private const CHECK_COMMAND_ID = 20;
    private const RESTRICTED_USER_ID = 7;

    private CreateServiceCommandHandler $handler;

    private FakeServiceRepository $serviceRepository;

    private FakeHostRepository $hostRepository;

    private FakeResourceAccessRepository $resourceAccess;

    private FakeServiceTemplateRepository $serviceTemplateRepository;

    private FakeServiceGroupRepository $serviceGroupRepository;

    private FakeServiceCategoryRepository $serviceCategoryRepository;

    private FakeServiceSeverityRepository $serviceSeverityRepository;

    private FakeTrapRepository $trapRepository;

    private FakeGraphTemplateRepository $graphTemplateRepository;

    private FakeTimePeriodRepository $timePeriodRepository;

    private FakeMediaRepository $mediaRepository;

    private FakeCommandRepository $commandRepository;

    private FakeOptionRepository $optionRepository;

    private FakeNotificationContactRepository $contactRepository;

    private FakeContactGroupRepository $contactGroupRepository;

    private EventBusSpy $eventBus;

    private FakeVault $vault;

    /**
     * Boots the real container and swaps only the repositories and the event bus for fakes, so the handler is
     * built by Symfony's DI exactly as it is in production.
     */
    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->serviceRepository = new FakeServiceRepository();
        $this->hostRepository = new FakeHostRepository();
        $this->resourceAccess = $this->hostRepository->resourceAccess;
        $this->serviceTemplateRepository = new FakeServiceTemplateRepository();
        $this->serviceGroupRepository = new FakeServiceGroupRepository();
        $this->serviceCategoryRepository = new FakeServiceCategoryRepository();
        $this->serviceSeverityRepository = new FakeServiceSeverityRepository();
        $this->trapRepository = new FakeTrapRepository();
        $this->graphTemplateRepository = new FakeGraphTemplateRepository();
        $this->timePeriodRepository = new FakeTimePeriodRepository();
        $this->mediaRepository = new FakeMediaRepository();
        $this->commandRepository = new FakeCommandRepository();
        $this->optionRepository = new FakeOptionRepository();
        $this->contactRepository = new FakeNotificationContactRepository();
        $this->contactGroupRepository = new FakeContactGroupRepository();
        $this->eventBus = new EventBusSpy();
        $this->vault = new FakeVault();

        $container->set(ServiceRepository::class, $this->serviceRepository);
        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccess);
        $container->set(ServiceTemplateRepository::class, $this->serviceTemplateRepository);
        $container->set(ServiceGroupRepository::class, $this->serviceGroupRepository);
        $container->set(ServiceCategoryRepository::class, $this->serviceCategoryRepository);
        $container->set(ServiceSeverityRepository::class, $this->serviceSeverityRepository);
        $container->set(TrapRepository::class, $this->trapRepository);
        $container->set(GraphTemplateRepository::class, $this->graphTemplateRepository);
        $container->set(TimePeriodRepository::class, $this->timePeriodRepository);
        $container->set(MediaRepository::class, $this->mediaRepository);
        $container->set(CommandRepository::class, $this->commandRepository);
        $container->set(OptionRepository::class, $this->optionRepository);
        $container->set(NotificationContactRepository::class, $this->contactRepository);
        $container->set(ContactGroupRepository::class, $this->contactGroupRepository);
        $container->set(EventBus::class, $this->eventBus);
        $container->set(VaultInterface::class, $this->vault);
        // Rebuild the writer on the fake vault so vaulting is driven by the test's FakeVault.
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(StandardMacroRepository::class, new FakeStandardMacroRepository(['$_SERVICECRITICALITY_LEVEL$']));

        /** @var CreateServiceCommandHandler $handler */
        $handler = $container->get(CreateServiceCommandHandler::class);
        $this->handler = $handler;

        $this->addHost(self::HOST_ID, activated: true);
        $this->addCommand(self::CHECK_COMMAND_ID);
        $this->contactRepository->names = [31 => new NotificationContactName('contact-31'), 32 => new NotificationContactName('contact-32')];
        $this->contactGroupRepository->names = [41 => new ContactGroupName('group-41'), 42 => new ContactGroupName('group-42')];
    }

    public function testItCreatesTheServiceEnabledOnItsHost(): void
    {
        $service = ($this->handler)($this->command(name: 'ping'));

        self::assertSame([$service], array_values($this->serviceRepository->services));
        self::assertSame('ping', $service->name->value);
        self::assertSame(self::HOST_ID, $service->hostId->value);
        self::assertTrue($service->activated);
        self::assertSame(self::CHECK_COMMAND_ID, $service->checkOptions->checkCommandId?->value);
    }

    /**
     * The service is built with the state of its host as stored: the poller of the host is the one locked before
     * the write and flagged after it, and only while the host is enabled.
     */
    public function testItBuildsTheServiceWithThePollerAndTheStateOfItsHost(): void
    {
        $this->addHost(11, activated: false, pollerId: 4);

        $enabledHostService = ($this->handler)($this->command(name: 'on-enabled'));
        $disabledHostService = ($this->handler)($this->command(name: 'on-disabled', hostId: 11));

        self::assertSame([self::POLLER_ID, true], [$enabledHostService->hostPollerId?->value, $enabledHostService->hostActivated]);
        self::assertSame(self::POLLER_ID, $enabledHostService->pollerToFlag()?->value);
        self::assertSame([4, false], [$disabledHostService->hostPollerId?->value, $disabledHostService->hostActivated]);
        self::assertNull($disabledHostService->pollerToFlag());
    }

    /**
     * Only the activation and the poller of the host are read: a host whose whole aggregate could not be loaded
     * (legacy data the host form would refuse) still takes services.
     */
    public function testItCreatesAServiceOnAHostItOnlyReadsTheStateOf(): void
    {
        $this->hostRepository->states[13] = new HostState(true, new PollerId(6));

        $service = ($this->handler)($this->command(hostId: 13));

        self::assertSame([13, 6, true], [$service->hostId->value, $service->hostPollerId?->value, $service->hostActivated]);
    }

    public function testItDispatchesServiceCreatedWithTheActor(): void
    {
        $service = ($this->handler)($this->command(actorId: 5));

        $events = $this->eventBus->getDispatchedEvents(ServiceCreated::class);
        self::assertCount(1, $events);
        self::assertSame($service, $events[0]->aggregate);
        self::assertSame(5, $events[0]->creatorId);
    }

    public function testItKeepsTheAcceptedReferencesOnTheService(): void
    {
        $this->seedEveryReference();

        $service = ($this->handler)($this->command(
            templateId: 30,
            checkCommandArgs: ['80'],
            schedulingOptions: new ServiceSchedulingOptions(checkTimeperiodId: new TimePeriodId(40), maxCheckAttempts: 3),
            notifications: $this->notifications(periodId: 41),
            dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(21), eventHandlerArgs: ['arg']),
            extendedInformations: new ServiceExtendedInformations(note: 'a note', iconId: new MediaId(50)),
            graphTemplateId: 60,
            severityId: 70,
            serviceGroupIds: [80],
            categoryIds: [90],
            trapIds: [100],
        ));

        self::assertSame(
            [30, ['80'], 40, 3, 41, 21, ['arg'], 'a note', 50, 60, 70, [80], [90], [100]],
            [
                $service->templateId?->value,
                $service->checkOptions->args,
                $service->schedulingOptions->checkTimeperiodId?->value,
                $service->schedulingOptions->maxCheckAttempts,
                $service->notifications?->periodId?->value,
                $service->dataProcessing->eventHandlerCommandId?->value,
                $service->dataProcessing->eventHandlerArgs,
                $service->extendedInformations?->note,
                $service->extendedInformations?->iconId?->value,
                $service->graphTemplateId?->value,
                $service->severityId?->value,
                $this->idValues($service->serviceGroupIds),
                $this->idValues($service->categoryIds),
                $this->idValues($service->trapIds),
            ],
        );
    }

    /**
     * Every reference re-checked by the handler is refused with the criterion named after the input field, so
     * that a reference gone after the input validation answers the same 422 on that field; a nested reference
     * (time period, icon, command) answers 404. Nothing is written.
     *
     * @param \Closure(self): CreateServiceCommand $command
     * @param class-string<AggregateNotFoundException|AggregateConflictException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('refusedReferenceProvider')]
    public function testItRefusesAMissingReference(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->seedEveryReference();

        try {
            ($this->handler)($command($this));
            self::fail('The reference must be refused.');
        } catch (AggregateNotFoundException|AggregateConflictException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceCreated::class));
    }

    /**
     * @return iterable<string, array{\Closure(self): CreateServiceCommand, class-string, array<string, mixed>}>
     */
    public static function refusedReferenceProvider(): iterable
    {
        yield 'unknown host' => [
            static fn (self $test): CreateServiceCommand => $test->command(hostId: 999),
            HostNotFoundException::class,
            ['hostId' => [999]],
        ];

        yield 'unknown service template' => [
            static fn (self $test): CreateServiceCommand => $test->command(templateId: 999),
            ServiceTemplateNotFoundException::class,
            ['serviceTemplateId' => 999],
        ];

        yield 'unknown service group' => [
            static fn (self $test): CreateServiceCommand => $test->command(serviceGroupIds: [80, 998, 999]),
            ServiceGroupNotFoundException::class,
            ['serviceGroupIds' => [998, 999]],
        ];

        yield 'unknown category' => [
            static fn (self $test): CreateServiceCommand => $test->command(categoryIds: [90, 999]),
            ServiceCategoryNotFoundException::class,
            ['categoryIds' => [999]],
        ];

        yield 'unknown severity' => [
            static fn (self $test): CreateServiceCommand => $test->command(severityId: 999),
            ServiceSeverityNotFoundException::class,
            ['severityId' => 999],
        ];

        // The trap finder also leaves out a trap without vendor or name, which is refused the same way.
        yield 'unknown trap' => [
            static fn (self $test): CreateServiceCommand => $test->command(trapIds: [100, 999]),
            TrapNotFoundException::class,
            ['trapIds' => [999]],
        ];

        yield 'unknown graph template' => [
            static fn (self $test): CreateServiceCommand => $test->command(graphTemplateId: 999),
            GraphTemplateNotFoundException::class,
            ['graphTemplateId' => 999],
        ];

        yield 'unknown check time period' => [
            static fn (self $test): CreateServiceCommand => $test->command(schedulingOptions: new ServiceSchedulingOptions(checkTimeperiodId: new TimePeriodId(999))),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown notification time period' => [
            static fn (self $test): CreateServiceCommand => $test->command(notifications: $test->notifications(periodId: 999)),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown icon' => [
            static fn (self $test): CreateServiceCommand => $test->command(extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(999))),
            MediaNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown check command' => [
            static fn (self $test): CreateServiceCommand => $test->command(checkCommandId: 999),
            CommandNotFoundException::class,
            ['id' => 999],
        ];

        yield 'check command of another type' => [
            static fn (self $test): CreateServiceCommand => $test->command(checkCommandId: 22),
            CommandNotFoundException::class,
            ['id' => 22],
        ];

        yield 'unknown contact' => [
            static fn (self $test): CreateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31, 999])),
            NotificationContactNotFoundException::class,
            ['contactIds' => [999]],
        ];

        yield 'unknown contact group' => [
            static fn (self $test): CreateServiceCommand => $test->command(notifications: $test->notifications(contactGroupIds: [41, 999])),
            ContactGroupNotFoundException::class,
            ['contactGroupIds' => [999]],
        ];

        yield 'unknown event handler' => [
            static fn (self $test): CreateServiceCommand => $test->command(dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(999))),
            CommandNotFoundException::class,
            ['id' => 999],
        ];
    }

    /**
     * For an actor whose access is restricted, a reference outside their scope is refused as if it did not
     * exist.
     *
     * @param \Closure(self): CreateServiceCommand $command
     * @param class-string<AggregateNotFoundException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('referenceOutsideTheScopeProvider')]
    public function testItRefusesAReferenceOutsideTheActorScope(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->seedEveryReference();
        $this->resourceAccess->accessibleHostIds = new Collection([new HostId(self::HOST_ID)], HostId::class);
        $this->resourceAccess->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId(80)]);
        $this->resourceAccess->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, [new ServiceCategoryId(90)]);
        $this->resourceAccess->serviceSeverityScope = AccessScope::restrictedTo(ServiceSeverityId::class, [new ServiceSeverityId(70)]);
        $this->resourceAccess->accessibleContactIds = new Collection([new NotificationContactId(31)], NotificationContactId::class);
        $this->resourceAccess->accessibleContactGroupIds = new Collection([new ContactGroupId(41)], ContactGroupId::class);

        try {
            ($this->handler)($command($this));
            self::fail('The reference must be refused.');
        } catch (AggregateNotFoundException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * @return iterable<string, array{\Closure(self): CreateServiceCommand, class-string, array<string, mixed>}>
     */
    public static function referenceOutsideTheScopeProvider(): iterable
    {
        yield 'host' => [
            static fn (self $test): CreateServiceCommand => $test->command(hostId: 12, actorId: self::RESTRICTED_USER_ID),
            HostNotFoundException::class,
            ['hostId' => [12]],
        ];

        yield 'service group' => [
            static fn (self $test): CreateServiceCommand => $test->command(serviceGroupIds: [80, 81], actorId: self::RESTRICTED_USER_ID),
            ServiceGroupNotFoundException::class,
            ['serviceGroupIds' => [81]],
        ];

        yield 'category' => [
            static fn (self $test): CreateServiceCommand => $test->command(categoryIds: [91], actorId: self::RESTRICTED_USER_ID),
            ServiceCategoryNotFoundException::class,
            ['categoryIds' => [91]],
        ];

        yield 'severity' => [
            static fn (self $test): CreateServiceCommand => $test->command(severityId: 71, actorId: self::RESTRICTED_USER_ID),
            ServiceSeverityNotFoundException::class,
            ['severityId' => 71],
        ];

        yield 'contact' => [
            static fn (self $test): CreateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31, 32], contactGroupIds: [41]), actorId: self::RESTRICTED_USER_ID),
            NotificationContactNotFoundException::class,
            ['contactIds' => [32]],
        ];

        yield 'contact group' => [
            static fn (self $test): CreateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31], contactGroupIds: [41, 42]), actorId: self::RESTRICTED_USER_ID),
            ContactGroupNotFoundException::class,
            ['contactGroupIds' => [42]],
        ];
    }

    public function testARestrictedActorCreatesAServiceWithReferencesInTheirScope(): void
    {
        $this->seedEveryReference();
        $this->resourceAccess->accessibleHostIds = new Collection([new HostId(self::HOST_ID)], HostId::class);
        $this->resourceAccess->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId(80)]);

        $service = ($this->handler)($this->command(serviceGroupIds: [80], actorId: self::RESTRICTED_USER_ID));

        self::assertSame([80], $this->idValues($service->serviceGroupIds));
    }

    /**
     * The references are checked before the name: a restricted actor cannot learn that a name exists on a host
     * they cannot use.
     */
    public function testARestrictedActorGetsHostNotFoundRatherThanADuplicateName(): void
    {
        $this->addHost(12);
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command(hostId: 12, actorId: self::RESTRICTED_USER_ID));
    }

    public function testTheTemplateIsCheckedBeforeItsLicense(): void
    {
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [999];

        $this->expectException(ServiceTemplateNotFoundException::class);

        ($this->handler)($this->command(templateId: 999));
    }

    /**
     * Every reference is checked before the name, the contacts included.
     */
    public function testAContactIsCheckedBeforeTheName(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(NotificationContactNotFoundException::class);

        ($this->handler)($this->command(notifications: $this->notifications(contactIds: [999])));
    }

    /**
     * The rules depending on the template chain come before the name too.
     */
    public function testTheCheckCommandIsCheckedBeforeTheName(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(ServiceCheckCommandMissingException::class);

        ($this->handler)($this->command(checkCommandId: null));
    }

    public function testTheFormBoundsAreCheckedBeforeTheName(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(ServiceExtendedInformationsTooLongException::class);

        ($this->handler)($this->command(extendedInformations: new ServiceExtendedInformations(note: str_repeat('n', 513))));
    }

    public function testItRefusesATemplateTheLicenseNoLongerAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [30];

        try {
            ($this->handler)($this->command(templateId: 30));
            self::fail('A template the license no longer allows must be rejected.');
        } catch (UnlicensedServiceTemplatesException $exception) {
            self::assertSame(['serviceTemplateId' => 30], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
    }

    public function testItAcceptsATemplateTheLicenseStillAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [31];

        $service = ($this->handler)($this->command(templateId: 30));

        self::assertSame(30, $service->templateId?->value);
    }

    /**
     * The name is checked after every other rule, as the authority over the input check: a name taken in
     * between answers 409.
     */
    public function testItRefusesANameAlreadyUsedOnTheHost(): void
    {
        ($this->handler)($this->command(name: 'ping'));

        try {
            ($this->handler)($this->command(name: 'PING'));
            self::fail('A name already used on the host must be rejected.');
        } catch (ServiceAlreadyExistsException $exception) {
            self::assertSame(['name' => 'PING'], $exception->criteria);
        }

        self::assertCount(1, $this->serviceRepository->services);
    }

    public function testItAcceptsANameUsedOnAnotherHost(): void
    {
        $this->addHost(11);
        ($this->handler)($this->command(name: 'ping', hostId: 11));

        ($this->handler)($this->command(name: 'ping'));

        self::assertCount(2, $this->serviceRepository->services);
    }

    public function testItRefusesAServiceWithoutCheckCommandNorTemplate(): void
    {
        try {
            ($this->handler)($this->command(checkCommandId: null));
            self::fail('A service needs a check command.');
        } catch (ServiceCheckCommandMissingException $exception) {
            self::assertSame(['checkOptions' => ['commandId']], $exception->criteria);
        }
    }

    public function testItRefusesATemplateChainWithoutCheckCommand(): void
    {
        $this->addTemplate(31);
        $this->addTemplate(30, parentId: 31);

        try {
            ($this->handler)($this->command(templateId: 30, checkCommandId: null));
            self::fail('A service needs a check command.');
        } catch (ServiceCheckCommandMissingException $exception) {
            self::assertSame(['serviceTemplateId' => 30], $exception->criteria);
        }
    }

    /**
     * The check command and its arguments may come from anywhere up the template chain.
     */
    public function testItAcceptsACheckCommandInheritedFromTheTemplateChain(): void
    {
        $this->addTemplate(31, checkCommandId: self::CHECK_COMMAND_ID);
        $this->addTemplate(30, parentId: 31);

        $service = ($this->handler)($this->command(templateId: 30, checkCommandId: null, checkCommandArgs: ['80']));

        self::assertNull($service->checkOptions->checkCommandId);
        self::assertSame(['80'], $service->checkOptions->args);
    }

    public function testItRefusesEventHandlerArgumentsWithoutAnyEventHandler(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);

        try {
            ($this->handler)($this->command(templateId: 30, dataProcessing: new ServiceDataProcessing(eventHandlerArgs: ['arg'])));
            self::fail('Event handler arguments need an event handler.');
        } catch (EventHandlerArgumentsRequireAHandlerException $exception) {
            self::assertSame(['advanced' => ['eventHandlerArgs']], $exception->criteria);
        }
    }

    public function testItAcceptsEventHandlerArgumentsWithAnEventHandlerInheritedFromTheTemplateChain(): void
    {
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addTemplate(31, eventHandlerCommandId: 21);
        $this->addTemplate(30, parentId: 31);

        $service = ($this->handler)($this->command(templateId: 30, dataProcessing: new ServiceDataProcessing(eventHandlerArgs: ['arg'])));

        self::assertSame(['arg'], $service->dataProcessing->eventHandlerArgs);
    }

    /**
     * A list of empty arguments only is no argument: it needs no event handler.
     */
    public function testItAcceptsEmptyEventHandlerArgumentsWithoutEventHandler(): void
    {
        $service = ($this->handler)($this->command(dataProcessing: new ServiceDataProcessing(eventHandlerArgs: ['', ''])));

        self::assertSame([], $service->dataProcessing->eventHandlerArgs);
    }

    /**
     * Either every check-command argument is filled or none is, checked again here as the authority. It comes
     * before the name, as the other rules on the payload.
     */
    public function testItRefusesAPartlyFilledArgumentList(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        try {
            ($this->handler)($this->command(checkCommandArgs: ['80', '']));
            self::fail('A partly filled argument list must be rejected.');
        } catch (ServiceCheckArgumentsPartlyFilledException $exception) {
            self::assertSame(['checkOptions' => ['args']], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * A note, note URL or action URL above the form bounds is refused even though it fits its column.
     */
    public function testItRefusesExtendedInformationAboveTheFormBounds(): void
    {
        try {
            ($this->handler)($this->command(extendedInformations: new ServiceExtendedInformations(note: str_repeat('n', 513))));
            self::fail('A note above its form bound must be rejected.');
        } catch (ServiceExtendedInformationsTooLongException $exception) {
            self::assertSame(['monitoringEngine' => ['note']], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * A service checked by a Centreon Monitoring Agent command set on it always checks its freshness, with a
     * fixed threshold; a CMA command inherited from the template does not trigger it.
     */
    public function testACentreonMonitoringAgentCheckCommandForcesTheFreshness(): void
    {
        $this->addCommand(23, name: 'Centreon-Monitoring-Agent-Linux', fromMonitoringConnector: true);
        $this->addTemplate(30, checkCommandId: 23);
        $dataProcessing = new ServiceDataProcessing(checkFreshness: TriStateEnum::False, freshnessThreshold: 30);

        $direct = ($this->handler)($this->command(name: 'direct', checkCommandId: 23, dataProcessing: $dataProcessing));
        $inherited = ($this->handler)($this->command(name: 'inherited', templateId: 30, checkCommandId: null, dataProcessing: $dataProcessing));

        self::assertSame([TriStateEnum::True, 500], [$direct->dataProcessing->checkFreshness, $direct->dataProcessing->freshnessThreshold]);
        self::assertSame([TriStateEnum::False, 30], [$inherited->dataProcessing->checkFreshness, $inherited->dataProcessing->freshnessThreshold]);
    }

    /**
     * A service notifying the contacts of its host only has no contact of its own: the contacts the actor
     * submitted, all within their scope, are cleared, and both additive-inheritance flags are switched off.
     * Hidden contacts cannot be submitted (they are refused above): they only matter on an update.
     */
    public function testUsingOnlyTheContactsOfTheHostClearsTheSubmittedContacts(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';
        $this->resourceAccess->accessibleHostIds = new Collection([new HostId(self::HOST_ID)], HostId::class);
        $this->resourceAccess->accessibleContactIds = new Collection([new NotificationContactId(31), new NotificationContactId(32)], NotificationContactId::class);
        $this->resourceAccess->accessibleContactGroupIds = new Collection([new ContactGroupId(41)], ContactGroupId::class);

        $service = ($this->handler)($this->command(
            notifications: $this->notifications(useOnlyContactsFromHost: true, additiveInheritance: true),
            actorId: self::RESTRICTED_USER_ID,
        ));

        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame([], $this->idValues($service->notifications->contactIds));
        self::assertSame([], $this->idValues($service->notifications->contactGroupIds));
        self::assertFalse($service->notifications->contactAdditiveInheritance);
        self::assertFalse($service->notifications->contactGroupAdditiveInheritance);
        self::assertTrue($service->notifications->useOnlyContactsFromHost);
    }

    public function testItKeepsTheContactsOfAServiceNotUsingOnlyTheContactsOfTheHost(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';

        $service = ($this->handler)($this->command(notifications: $this->notifications(additiveInheritance: true)));

        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame([31, 32], $this->idValues($service->notifications->contactIds));
        self::assertTrue($service->notifications->contactAdditiveInheritance);
    }

    /**
     * The additive-inheritance flags only hold when the platform's inheritance mode enables them.
     */
    public function testItForcesTheAdditiveInheritanceFlagsOffWhenTheOptionIsDisabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '3';

        $service = ($this->handler)($this->command(notifications: $this->notifications(additiveInheritance: true)));

        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertFalse($service->notifications->contactAdditiveInheritance);
        self::assertFalse($service->notifications->contactGroupAdditiveInheritance);
    }

    /**
     * The own macros are stored with the service, the password ones as references to the service's vault entry,
     * written under `monitoring/services` before the service is saved.
     */
    public function testItCreatesTheServiceWithItsOwnMacros(): void
    {
        $service = ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('port'), '8080', isPassword: false),
            new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true),
        ]));

        self::assertSame(
            [['PORT', '8080', false], ['PWD', 'secret::vault::monitoring/services/new-uuid::_SERVICEPWD', true]],
            $this->describeMacros($service->checkOptions->macros),
        );
        self::assertSame([$service], array_values($this->serviceRepository->services));
        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => ['_SERVICEPWD' => 's3cr3t'], 'uuid' => null, 'deletes' => []]],
            $this->vault->writeManyCalls,
        );
    }

    /**
     * The macros are resolved against what the submitted template and the effective check command (here the one
     * of the template, no command being set) make the service inherit: a template macro resent with a new value
     * becomes an own macro, a command macro echoed back unchanged stays inherited.
     */
    public function testItResolvesTheMacrosAgainstTheSubmittedTemplateAndItsCheckCommand(): void
    {
        $command = new Command(
            new CommandId(23),
            new CommandName('check_http'),
            CommandTypeEnum::Check,
            new CommandLine('$USER1$/check -p $_SERVICEPORT$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
        $command->setStoredMacros([new CommandMacro(new CommandMacroId(31), 'PORT', CommandMacroTypeEnum::Service)]);
        $this->commandRepository->commands[23] = $command;
        $this->serviceTemplateRepository->templates[30] = new ServiceTemplate(
            new ServiceTemplateId(30),
            new ServiceTemplateName('template-30'),
            checkCommandId: new CommandId(23),
            macros: new Collection([new ServiceMacro(new ServiceMacroName('url'), '/', isPassword: false, id: new ServiceMacroId(11))], ServiceMacro::class),
        );
        $this->serviceTemplateRepository->names[30] = 'template-30';

        $service = ($this->handler)($this->command(templateId: 30, checkCommandId: null, macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('url'), '/health', isPassword: false, id: new ServiceMacroId(11), parent: ServiceMacroParentEnum::Template),
            new ServiceMacroChange(new ServiceMacroName('port'), '', isPassword: false, id: new ServiceMacroId(31), parent: ServiceMacroParentEnum::Command),
        ]));

        self::assertSame([['URL', '/health', false]], $this->describeMacros($service->checkOptions->macros));
        self::assertNull($service->checkOptions->macros[0]->id);
    }

    /**
     * A macro inherited only through another template answers not found.
     */
    public function testItRefusesAMacroTheServiceDoesNotInherit(): void
    {
        try {
            ($this->handler)($this->command(macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('url'), '/', isPassword: false, id: new ServiceMacroId(11), parent: ServiceMacroParentEnum::Template),
            ]));
            self::fail('Expected ServiceMacroNotFoundException');
        } catch (ServiceMacroNotFoundException $exception) {
            self::assertSame(['checkOptions' => [11]], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
    }

    /**
     * The handler checks the reserved names again, as the authority: the list may change after the input
     * validation. Nothing is written, not even to the vault.
     */
    public function testItRefusesAReservedMacroName(): void
    {
        try {
            ($this->handler)($this->command(macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true),
                new ServiceMacroChange(new ServiceMacroName('criticality_level'), '1', isPassword: false),
            ]));
            self::fail('Expected ReservedServiceMacroNameException');
        } catch (ReservedServiceMacroNameException $exception) {
            self::assertSame(['checkOptions' => ['CRITICALITY_LEVEL']], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItRefusesAMacroValueThatIsAVaultReference(): void
    {
        try {
            ($this->handler)($this->command(macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('pwd'), 'secret::vault::monitoring/services/other::_SERVICEPWD', isPassword: true),
            ]));
            self::fail('Expected ServiceMacroValueIsAVaultReferenceException');
        } catch (ServiceMacroValueIsAVaultReferenceException $exception) {
            self::assertSame(['checkOptions' => ['PWD']], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItRefusesDuplicateMacroNames(): void
    {
        try {
            ($this->handler)($this->command(macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('pwd'), 'a', isPassword: true),
                new ServiceMacroChange(new ServiceMacroName('PWD'), 'b', isPassword: true),
            ]));
            self::fail('Expected DuplicateServiceMacroNameException');
        } catch (DuplicateServiceMacroNameException $exception) {
            self::assertSame(['checkOptions' => ['PWD']], $exception->criteria);
        }

        self::assertSame([], $this->serviceRepository->services);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    /**
     * The macros come after the name.
     */
    public function testTheNameIsCheckedBeforeTheMacros(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(ServiceAlreadyExistsException::class);

        ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('criticality_level'), '1', isPassword: false),
        ]));
    }

    /**
     * The vault is reached only once every rule passed, and before the service is saved: what is saved already
     * holds the references.
     */
    public function testVaultWriteBeforeAdd(): void
    {
        $this->serviceRepository->forceNameUsed = true;
        try {
            ($this->handler)($this->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)]));
            self::fail('Expected ServiceAlreadyExistsException');
        } catch (ServiceAlreadyExistsException) {
        }
        self::assertSame([], $this->vault->writeManyCalls);

        $this->serviceRepository->forceNameUsed = false;
        ($this->handler)($this->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)]));

        $saved = array_values($this->serviceRepository->services);
        self::assertCount(1, $saved);
        self::assertSame('secret::vault::monitoring/services/new-uuid::_SERVICEPWD', $saved[0]->checkOptions->macros[0]->value);
    }

    /**
     * A vault failure is an upstream failure (HTTP 502): nothing is saved and no event fires.
     */
    public function testVaultFailureSavesNothing(): void
    {
        $this->vault->writeThrows = true;

        try {
            ($this->handler)($this->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)]));
            self::fail('Expected VaultWriteFailedException');
        } catch (VaultWriteFailedException $exception) {
            self::assertSame('The service was not created: its credentials could not be written to the vault.', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }

        self::assertSame([], $this->serviceRepository->services);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceCreated::class));
    }

    /**
     * @param list<string> $checkCommandArgs
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     * @param list<ServiceMacroChange> $macroChanges
     */
    public function command(
        string $name = 'ping',
        int $hostId = self::HOST_ID,
        ?int $templateId = null,
        ?int $checkCommandId = self::CHECK_COMMAND_ID,
        array $checkCommandArgs = [],
        ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        ?ServiceNotifications $notifications = null,
        ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        ?ServiceExtendedInformations $extendedInformations = null,
        ?int $graphTemplateId = null,
        ?int $severityId = null,
        array $serviceGroupIds = [],
        array $categoryIds = [],
        array $trapIds = [],
        ?int $actorId = null,
        array $macroChanges = [],
    ): CreateServiceCommand {
        return new CreateServiceCommand(
            name: new ServiceName($name),
            hostId: new HostId($hostId),
            actor: $actorId === self::RESTRICTED_USER_ID ? TestActingUsers::restricted($actorId) : TestActingUsers::unrestricted($actorId ?? 1),
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            checkCommandId: $checkCommandId !== null ? new CommandId($checkCommandId) : null,
            checkCommandArgs: $checkCommandArgs,
            schedulingOptions: $schedulingOptions,
            notifications: $notifications,
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            graphTemplateId: $graphTemplateId !== null ? new GraphTemplateId($graphTemplateId) : null,
            severityId: $severityId !== null ? new ServiceSeverityId($severityId) : null,
            serviceGroupIds: new Collection(array_map(static fn (int $id): ServiceGroupId => new ServiceGroupId($id), $serviceGroupIds), ServiceGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $id): ServiceCategoryId => new ServiceCategoryId($id), $categoryIds), ServiceCategoryId::class),
            trapIds: new Collection(array_map(static fn (int $id): TrapId => new TrapId($id), $trapIds), TrapId::class),
            macroChanges: new Collection($macroChanges, ServiceMacroChange::class),
        );
    }

    /**
     * @param list<int> $contactIds
     * @param list<int> $contactGroupIds
     */
    public function notifications(
        ?int $periodId = null,
        bool $useOnlyContactsFromHost = false,
        bool $additiveInheritance = false,
        array $contactIds = [31, 32],
        array $contactGroupIds = [41],
    ): ServiceNotifications {
        return new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection(array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $contactIds), NotificationContactId::class),
            contactGroupIds: new Collection(array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), $contactGroupIds), ContactGroupId::class),
            periodId: $periodId !== null ? new TimePeriodId($periodId) : null,
            contactAdditiveInheritance: $additiveInheritance,
            contactGroupAdditiveInheritance: $additiveInheritance,
            useOnlyContactsFromHost: $useOnlyContactsFromHost,
        );
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return list<array{string, string, bool}>
     */
    private function describeMacros(array $macros): array
    {
        return array_map(static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->isPassword], $macros);
    }

    /**
     * Every reference a service can point to, each existing with one id: template 30 (with the check command), a
     * second check command 22 of another type, event handler 21, time periods 40 and 41, icon 50, graph template
     * 60, severities 70 and 71, service groups 80 and 81, categories 90 and 91, trap 100, and a second host 12.
     */
    private function seedEveryReference(): void
    {
        $this->addHost(12);
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addCommand(22, CommandTypeEnum::Notification);
        $this->timePeriodRepository->existingIds = [40, 41];
        $this->mediaRepository->existingIds = [50];
        $this->graphTemplateRepository->names = [60 => 'graph'];
        $this->serviceSeverityRepository->names = [70 => 'severity-70', 71 => 'severity-71'];
        $this->serviceGroupRepository->names = [80 => 'group-80', 81 => 'group-81'];
        $this->serviceCategoryRepository->names = [90 => 'category-90', 91 => 'category-91'];
        $this->trapRepository->traps[100] = new Trap(
            new TrapId(100),
            new TrapName('linkDown'),
            new TrapVendor(new TrapVendorId(1), new TrapVendorName('Generic')),
        );
    }

    private function addHost(int $id, bool $activated = true, int $pollerId = self::POLLER_ID): void
    {
        $host = new Host(
            id: null,
            name: new HostName('host-' . $id),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setValue($host, new HostId($id));

        $this->hostRepository->hosts[$id] = $host;
    }

    private function addTemplate(int $id, ?int $parentId = null, ?int $checkCommandId = null, ?int $eventHandlerCommandId = null): void
    {
        $this->serviceTemplateRepository->templates[$id] = new ServiceTemplate(
            new ServiceTemplateId($id),
            new ServiceTemplateName('template-' . $id),
            parentId: $parentId !== null ? new ServiceTemplateId($parentId) : null,
            checkCommandId: $checkCommandId !== null ? new CommandId($checkCommandId) : null,
            eventHandlerCommandId: $eventHandlerCommandId !== null ? new CommandId($eventHandlerCommandId) : null,
        );
        $this->serviceTemplateRepository->names[$id] = 'template-' . $id;
    }

    private function addCommand(
        int $id,
        CommandTypeEnum $type = CommandTypeEnum::Check,
        string $name = 'check_ping',
        bool $fromMonitoringConnector = false,
    ): void {
        $this->commandRepository->commands[$id] = new Command(
            new CommandId($id),
            new CommandName($name),
            $type,
            new CommandLine('$USER1$/check'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: $fromMonitoringConnector,
            connector: null,
            comment: null,
        );
    }

    /**
     * @param Collection<covariant AggregateRootId> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }
}
