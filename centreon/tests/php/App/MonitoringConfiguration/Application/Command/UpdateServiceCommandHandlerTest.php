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

use App\MonitoringConfiguration\Application\Command\UpdateServiceCommand;
use App\MonitoringConfiguration\Application\Command\UpdateServiceCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
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
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceUpdated;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
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
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
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
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use App\Shared\Domain\VaultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
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
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class UpdateServiceCommandHandlerTest extends KernelTestCase
{
    private const SERVICE_ID = 5;
    private const HOST_ID = 10;
    private const POLLER_ID = 3;
    private const OTHER_HOST_ID = 12;
    private const OTHER_POLLER_ID = 4;
    private const CHECK_COMMAND_ID = 20;
    private const RESTRICTED_USER_ID = 7;
    private const SERVICE_PWD = 'secret::vault::monitoring/services/svc-uuid::_SERVICEPWD';

    private UpdateServiceCommandHandler $handler;

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

    private LoggerSpy $logger;

    /**
     * Boots the real container and swaps only the repositories, the vault, the logger and the event bus for fakes,
     * so the handler is built by Symfony's DI exactly as it is in production.
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
        $this->logger = new LoggerSpy();
        // The severity is read scoped to the actor, as the real repository reads it.
        $this->serviceRepository->accessScopes = new AccessScopeResolver($this->resourceAccess);

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
        $container->set(LoggerInterface::class, $this->logger);

        /** @var UpdateServiceCommandHandler $handler */
        $handler = $container->get(UpdateServiceCommandHandler::class);
        $this->handler = $handler;

        $this->hostRepository->states[self::HOST_ID] = new HostState(true, new PollerId(self::POLLER_ID));
        $this->addCommand(self::CHECK_COMMAND_ID);
        $this->contactRepository->names = [
            31 => new NotificationContactName('contact-31'),
            32 => new NotificationContactName('contact-32'),
            33 => new NotificationContactName('contact-33'),
        ];
        $this->contactGroupRepository->names = [41 => new ContactGroupName('group-41'), 42 => new ContactGroupName('group-42')];
        $this->store();
    }

    public function testItReplacesTheStoredServiceWithTheSubmittedOne(): void
    {
        $this->seedEveryReference();

        $service = ($this->handler)($this->command(
            name: 'pong',
            hostId: self::OTHER_HOST_ID,
            activated: false,
            templateId: 30,
            checkCommandArgs: ['80'],
            schedulingOptions: new ServiceSchedulingOptions(checkTimeperiodId: new TimePeriodId(40), maxCheckAttempts: 3),
            notifications: $this->notifications(periodId: 41, contactIds: [32]),
            dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(21), eventHandlerArgs: ['arg']),
            extendedInformations: new ServiceExtendedInformations(note: 'a note', iconId: new MediaId(50)),
            graphTemplateId: 60,
            severityId: 70,
            serviceGroupIds: [80],
            categoryIds: [90],
            trapIds: [100],
        ));

        self::assertSame(
            [self::SERVICE_ID, 'pong', self::OTHER_HOST_ID, self::OTHER_POLLER_ID, false, 30, ['80'], 40, 3, 41, [32], 21, ['arg'], 'a note', 50, 60, 70, [80], [90], [100]],
            [
                $service->id()->value,
                $service->name->value,
                $service->hostId->value,
                $service->hostPollerId?->value,
                $service->activated,
                $service->templateId?->value,
                $service->checkOptions->args,
                $service->schedulingOptions->checkTimeperiodId?->value,
                $service->schedulingOptions->maxCheckAttempts,
                $service->notifications?->periodId?->value,
                $service->notifications instanceof ServiceNotifications ? $this->idValues($service->notifications->contactIds) : null,
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
        self::assertSame([$service], $this->serviceRepository->updatedServices);
        self::assertSame([$service], $this->serviceRepository->relationsReplacedServices);
    }

    public function testItRefusesAnUnknownService(): void
    {
        try {
            ($this->handler)($this->command(id: 999));
            self::fail('An unknown service must be refused.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame(['id' => [999]], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * A service the actor cannot see is not found, the same as one that does not exist.
     */
    public function testItRefusesAServiceOutsideTheActorScope(): void
    {
        $this->serviceRepository->outOfRestrictedScopeIds = [self::SERVICE_ID];

        $this->expectException(ServiceNotFoundException::class);

        ($this->handler)($this->command(actorId: self::RESTRICTED_USER_ID));
    }

    /**
     * Every reference is checked again as the authority, refused with the criterion named after the input field
     * (a nested reference answers not found), and nothing is written.
     *
     * @param \Closure(self): UpdateServiceCommand $command
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

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): UpdateServiceCommand, class-string, array<string, mixed>}>
     */
    public static function refusedReferenceProvider(): iterable
    {
        yield 'unknown host' => [
            static fn (self $test): UpdateServiceCommand => $test->command(hostId: 999),
            HostNotFoundException::class,
            ['hostId' => [999]],
        ];

        yield 'unknown service template' => [
            static fn (self $test): UpdateServiceCommand => $test->command(templateId: 999),
            ServiceTemplateNotFoundException::class,
            ['serviceTemplateId' => 999],
        ];

        yield 'unknown service group' => [
            static fn (self $test): UpdateServiceCommand => $test->command(serviceGroupIds: [80, 999]),
            ServiceGroupNotFoundException::class,
            ['serviceGroupIds' => [999]],
        ];

        yield 'unknown category' => [
            static fn (self $test): UpdateServiceCommand => $test->command(categoryIds: [999]),
            ServiceCategoryNotFoundException::class,
            ['categoryIds' => [999]],
        ];

        yield 'unknown severity' => [
            static fn (self $test): UpdateServiceCommand => $test->command(severityId: 999),
            ServiceSeverityNotFoundException::class,
            ['severityId' => 999],
        ];

        yield 'unknown trap' => [
            static fn (self $test): UpdateServiceCommand => $test->command(trapIds: [999]),
            TrapNotFoundException::class,
            ['trapIds' => [999]],
        ];

        yield 'unknown graph template' => [
            static fn (self $test): UpdateServiceCommand => $test->command(graphTemplateId: 999),
            GraphTemplateNotFoundException::class,
            ['graphTemplateId' => 999],
        ];

        yield 'unknown check time period' => [
            static fn (self $test): UpdateServiceCommand => $test->command(schedulingOptions: new ServiceSchedulingOptions(checkTimeperiodId: new TimePeriodId(999))),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown notification time period' => [
            static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(periodId: 999)),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown icon' => [
            static fn (self $test): UpdateServiceCommand => $test->command(extendedInformations: new ServiceExtendedInformations(iconId: new MediaId(999))),
            MediaNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown check command' => [
            static fn (self $test): UpdateServiceCommand => $test->command(checkCommandId: 999),
            CommandNotFoundException::class,
            ['id' => 999],
        ];

        yield 'check command of another type' => [
            static fn (self $test): UpdateServiceCommand => $test->command(checkCommandId: 22),
            CommandNotFoundException::class,
            ['id' => 22],
        ];

        yield 'unknown event handler' => [
            static fn (self $test): UpdateServiceCommand => $test->command(dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(999))),
            CommandNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown contact' => [
            static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31, 999])),
            NotificationContactNotFoundException::class,
            ['contactIds' => [999]],
        ];

        yield 'unknown contact group' => [
            static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactGroupIds: [41, 999])),
            ContactGroupNotFoundException::class,
            ['contactGroupIds' => [999]],
        ];
    }

    /**
     * For an actor whose access is restricted, a reference outside their scope is refused as if it did not exist,
     * the contacts and contact groups included.
     *
     * @param \Closure(self): UpdateServiceCommand $command
     * @param class-string<AggregateNotFoundException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('referenceOutsideTheScopeProvider')]
    public function testItRefusesAReferenceOutsideTheActorScope(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->seedEveryReference();
        $this->restrictTheActor();

        try {
            ($this->handler)($command($this));
            self::fail('The reference must be refused.');
        } catch (AggregateNotFoundException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): UpdateServiceCommand, class-string, array<string, mixed>}>
     */
    public static function referenceOutsideTheScopeProvider(): iterable
    {
        yield 'host' => [
            static fn (self $test): UpdateServiceCommand => $test->command(hostId: self::OTHER_HOST_ID, actorId: self::RESTRICTED_USER_ID),
            HostNotFoundException::class,
            ['hostId' => [self::OTHER_HOST_ID]],
        ];

        yield 'service group' => [
            static fn (self $test): UpdateServiceCommand => $test->command(serviceGroupIds: [81], actorId: self::RESTRICTED_USER_ID),
            ServiceGroupNotFoundException::class,
            ['serviceGroupIds' => [81]],
        ];

        yield 'category' => [
            static fn (self $test): UpdateServiceCommand => $test->command(categoryIds: [91], actorId: self::RESTRICTED_USER_ID),
            ServiceCategoryNotFoundException::class,
            ['categoryIds' => [91]],
        ];

        yield 'severity' => [
            static fn (self $test): UpdateServiceCommand => $test->command(severityId: 71, actorId: self::RESTRICTED_USER_ID),
            ServiceSeverityNotFoundException::class,
            ['severityId' => 71],
        ];

        yield 'contact' => [
            static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31, 32], contactGroupIds: []), actorId: self::RESTRICTED_USER_ID),
            NotificationContactNotFoundException::class,
            ['contactIds' => [32]],
        ];

        yield 'contact group' => [
            static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31], contactGroupIds: [41, 42]), actorId: self::RESTRICTED_USER_ID),
            ContactGroupNotFoundException::class,
            ['contactGroupIds' => [42]],
        ];
    }

    /**
     * The references are checked before the name: a restricted actor cannot learn that a name exists on a host
     * they cannot use.
     */
    public function testARestrictedActorGetsHostNotFoundRatherThanANameConflict(): void
    {
        $this->seedEveryReference();
        $this->restrictTheActor();
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command(hostId: self::OTHER_HOST_ID, actorId: self::RESTRICTED_USER_ID));
    }

    public function testItRefusesAChangedTemplateTheLicenseNoLongerAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [30];

        try {
            ($this->handler)($this->command(templateId: 30));
            self::fail('A template the license no longer allows must be rejected.');
        } catch (UnlicensedServiceTemplatesException $exception) {
            self::assertSame(['serviceTemplateId' => 30], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * The service keeps the template it already has, even one it could no longer be given.
     */
    public function testItKeepsATemplateTheLicenseNoLongerAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(templateId: 30);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [30];

        $service = ($this->handler)($this->command(name: 'pong', templateId: 30));

        self::assertSame(30, $service->templateId?->value);
        self::assertCount(1, $this->serviceRepository->updatedServices);
    }

    public function testItRefusesAServiceWithoutCheckCommandNorTemplate(): void
    {
        try {
            ($this->handler)($this->command(checkCommandId: null));
            self::fail('A service needs a check command.');
        } catch (ServiceCheckCommandMissingException $exception) {
            self::assertSame(['checkOptions' => ['commandId']], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    public function testItRefusesEventHandlerArgumentsWithoutAnyEventHandler(): void
    {
        $this->expectException(EventHandlerArgumentsRequireAHandlerException::class);

        ($this->handler)($this->command(dataProcessing: new ServiceDataProcessing(eventHandlerArgs: ['arg'])));
    }

    /**
     * Either every check-command argument is filled or none is, checked again here as the authority, before the
     * name.
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

        $this->assertNothingWritten();
    }

    /**
     * A legacy note longer than the form allows reads back as stored, but resending it unchanged is refused: the
     * update must bring it back into the form bounds.
     */
    public function testOverLongLegacyTextIsRefusedOnWrite(): void
    {
        $legacy = new ServiceExtendedInformations(note: str_repeat('n', 513));
        $this->store(extendedInformations: $legacy);
        $this->serviceRepository->forceNameUsed = true;

        try {
            ($this->handler)($this->command(extendedInformations: $legacy));
            self::fail('A note above its form bound must be rejected.');
        } catch (ServiceExtendedInformationsTooLongException $exception) {
            self::assertSame(['monitoringEngine' => ['note']], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * The name is checked on the target host, the service itself left out, after every other rule: a name taken
     * there answers 409.
     */
    public function testNameConflictOnTheTargetHost(): void
    {
        $this->seedEveryReference();
        $this->serviceRepository->services[6] = new Service(
            id: new ServiceId(6),
            name: new ServiceName('PING'),
            hostId: new HostId(self::OTHER_HOST_ID),
            hostActivated: true,
            hostPollerId: new PollerId(self::OTHER_POLLER_ID),
        );

        try {
            ($this->handler)($this->command(hostId: self::OTHER_HOST_ID));
            self::fail('A name already used on the target host must be rejected.');
        } catch (ServiceAlreadyExistsException $exception) {
            self::assertSame(['name' => 'ping'], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    public function testNameConflictLeavesTheServiceItselfOut(): void
    {
        $service = ($this->handler)($this->command(activated: false));

        self::assertSame('ping', $service->name->value);
        self::assertCount(1, $this->serviceRepository->updatedServices);
    }

    /**
     * Groups, categories, contacts and contact groups the actor cannot see stay linked; the submitted ones
     * replace the ones they can see.
     */
    public function testARestrictedActorKeepsTheRelationsTheyCannotSee(): void
    {
        $this->seedEveryReference();
        $this->store(
            notifications: $this->notifications(contactIds: [31, 32], contactGroupIds: [41, 42]),
            serviceGroupIds: [80, 81],
            categoryIds: [90, 91],
        );
        $this->restrictTheActor();

        $service = ($this->handler)($this->command(
            notifications: $this->notifications(contactIds: [], contactGroupIds: []),
            actorId: self::RESTRICTED_USER_ID,
        ));

        self::assertSame([81], $this->idValues($service->serviceGroupIds));
        self::assertSame([91], $this->idValues($service->categoryIds));
        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame([32], $this->idValues($service->notifications->contactIds));
        self::assertSame([42], $this->idValues($service->notifications->contactGroupIds));
    }

    /**
     * A submitted severity replaces every stored one, hidden or not: the service keeps a single severity.
     */
    public function testASubmittedSeverityReplacesEveryStoredOne(): void
    {
        $this->seedEveryReference();
        $this->serviceRepository->storedSeverityIds[self::SERVICE_ID] = [70, 71];
        $this->restrictTheActor();

        $service = ($this->handler)($this->command(severityId: 70, actorId: self::RESTRICTED_USER_ID));

        self::assertSame(70, $service->severityId?->value);
    }

    /**
     * No severity submitted: the hidden one with the lowest id is kept, the only one written back. The service
     * as the actor loaded it shows no severity at all.
     */
    public function testNoSeverityKeepsTheLowestHiddenOne(): void
    {
        $this->seedEveryReference();
        $this->serviceRepository->storedSeverityIds[self::SERVICE_ID] = [70, 72, 71];
        $this->restrictTheActor();

        $service = ($this->handler)($this->command(actorId: self::RESTRICTED_USER_ID));

        self::assertSame(71, $service->severityId?->value);
    }

    /**
     * The actor resends the form as they read it, the severity they cannot see left out: nothing changed, so
     * nothing is written and nothing fires.
     */
    public function testARoundTripKeepingAHiddenSeverityWritesNothing(): void
    {
        $this->seedEveryReference();
        $this->store(severityId: 71);
        $this->restrictTheActor();

        ($this->handler)($this->command(actorId: self::RESTRICTED_USER_ID));

        $this->assertNothingWritten();
    }

    /**
     * A service holding several severities (legacy) keeps only one after an update, even when the one resent is
     * the one it already shows.
     */
    public function testResendingTheVisibleSeverityOfAServiceHoldingSeveralIsAWrite(): void
    {
        $this->seedEveryReference();
        $this->store(severityId: 70);
        $this->serviceRepository->storedSeverityIds[self::SERVICE_ID] = [70, 72];

        $service = ($this->handler)($this->command(severityId: 70));

        self::assertSame(70, $service->severityId?->value);
        self::assertSame([$service], $this->serviceRepository->relationsReplacedServices);
        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->loggable);
    }

    public function testNoSeverityClearsAVisibleOne(): void
    {
        $this->seedEveryReference();
        $this->store(severityId: 70);
        $this->restrictTheActor();

        $service = ($this->handler)($this->command(actorId: self::RESTRICTED_USER_ID));

        self::assertNull($service->severityId);
    }

    /**
     * Notifying the contacts of the host only clears the contacts and contact groups the actor can see; those
     * they cannot see stay linked. Both additive-inheritance flags are switched off.
     */
    public function testUsingOnlyTheContactsOfTheHostClearsOnlyTheVisibleContacts(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';
        $this->store(notifications: $this->notifications(contactIds: [31, 32], contactGroupIds: [41, 42]));
        $this->restrictTheActor();

        $service = ($this->handler)($this->command(
            notifications: $this->notifications(useOnlyContactsFromHost: true, additiveInheritance: true, contactIds: [31], contactGroupIds: [41]),
            actorId: self::RESTRICTED_USER_ID,
        ));

        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertSame([32], $this->idValues($service->notifications->contactIds));
        self::assertSame([42], $this->idValues($service->notifications->contactGroupIds));
        self::assertFalse($service->notifications->contactAdditiveInheritance);
        self::assertFalse($service->notifications->contactGroupAdditiveInheritance);
    }

    public function testItForcesTheAdditiveInheritanceFlagsOffWhenTheOptionIsDisabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '3';

        $service = ($this->handler)($this->command(notifications: $this->notifications(additiveInheritance: true)));

        self::assertInstanceOf(ServiceNotifications::class, $service->notifications);
        self::assertFalse($service->notifications->contactAdditiveInheritance);
    }

    /**
     * Without notification block (Cloud), the stored notification settings are kept.
     */
    public function testWithoutNotificationsTheStoredOnesAreKept(): void
    {
        $stored = $this->notifications(periodId: null, contactIds: [33]);
        $this->store(notifications: $stored);

        $service = ($this->handler)($this->command(name: 'pong', notifications: null));

        self::assertSame($stored, $service->notifications);
    }

    /**
     * A Centreon Monitoring Agent command set on the service always checks its freshness, on update too.
     */
    public function testACentreonMonitoringAgentCheckCommandForcesTheFreshness(): void
    {
        $this->addCommand(23, name: 'Centreon-Monitoring-Agent-Linux', fromMonitoringConnector: true);

        $service = ($this->handler)($this->command(
            checkCommandId: 23,
            dataProcessing: new ServiceDataProcessing(checkFreshness: TriStateEnum::False, freshnessThreshold: 30),
        ));

        self::assertSame([TriStateEnum::True, 500], [$service->dataProcessing->checkFreshness, $service->dataProcessing->freshnessThreshold]);
    }

    /**
     * A move to another host carries the previous host, and the poller of that host when it was enabled and is
     * not the new one; the service is built with the state of its new host.
     */
    public function testHostChangeCarriesThePreviousHostAndPoller(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::OTHER_POLLER_ID));

        $service = ($this->handler)($this->command(hostId: self::OTHER_HOST_ID));

        self::assertSame([self::OTHER_HOST_ID, true, self::OTHER_POLLER_ID], [$service->hostId->value, $service->hostActivated, $service->hostPollerId?->value]);
        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertTrue($events[0]->loggable);
        self::assertSame([self::HOST_ID, self::POLLER_ID], [$events[0]->previousHostId?->value, $events[0]->previousPollerId?->value]);
    }

    public function testHostChangeFromADisabledHostFlagsNoPreviousPoller(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::OTHER_POLLER_ID));
        $this->store(hostActivated: false);

        ($this->handler)($this->command(hostId: self::OTHER_HOST_ID));

        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertSame([self::HOST_ID, null], [$events[0]->previousHostId?->value, $events[0]->previousPollerId]);
    }

    /**
     * Both hosts on one poller: it is flagged once.
     */
    public function testHostChangeOnTheSamePollerFlagsItOnce(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::POLLER_ID));

        ($this->handler)($this->command(hostId: self::OTHER_HOST_ID));

        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertSame([self::HOST_ID, null], [$events[0]->previousHostId?->value, $events[0]->previousPollerId]);
    }

    /**
     * The previous host's poller is still flagged when the new host is disabled.
     */
    public function testHostChangeToADisabledHostStillFlagsThePreviousPoller(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(false, new PollerId(self::POLLER_ID));

        ($this->handler)($this->command(hostId: self::OTHER_HOST_ID));

        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertSame(self::POLLER_ID, $events[0]->previousPollerId?->value);
    }

    /**
     * Nothing differs from the stored service: nothing is written and nothing fires.
     */
    public function testNoOpWritesNothing(): void
    {
        $service = ($this->handler)($this->command());

        self::assertSame(self::SERVICE_ID, $service->id()->value);
        $this->assertNothingWritten();
    }

    /**
     * A stored password resent with no value keeps it: still nothing to write.
     */
    public function testNoOpWithAKeptPasswordWritesNothing(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(3))]);

        ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('pwd'), null, isPassword: true, id: new ServiceMacroId(3)),
        ]));

        $this->assertNothingWritten();
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAnActivationFlipAloneWritesOneLine(): void
    {
        ($this->handler)($this->command(activated: false));

        self::assertCount(1, $this->eventBus->getDispatchedEvents(ServiceDisabled::class));
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceUpdated::class));
    }

    public function testAFlipAndALoggedChangeWriteTwoLines(): void
    {
        $this->store(activated: false);

        ($this->handler)($this->command(name: 'pong'));

        self::assertCount(1, $this->eventBus->getDispatchedEvents(ServiceEnabled::class));
        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertTrue($events[0]->loggable);
        self::assertNull($events[0]->previousHostId);
    }

    /**
     * A change limited to what the log leaves out is written, its side effects run, but it writes no line.
     *
     * @param \Closure(self): UpdateServiceCommand $command
     */
    #[DataProvider('unloggedChangeProvider')]
    public function testAnUnloggedChangeWritesNoLine(\Closure $command): void
    {
        $this->seedEveryReference();

        ($this->handler)($command($this));

        self::assertCount(1, $this->serviceRepository->updatedServices);
        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->loggable);
    }

    /**
     * @return iterable<string, array{\Closure(self): UpdateServiceCommand}>
     */
    public static function unloggedChangeProvider(): iterable
    {
        yield 'contacts' => [static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactIds: [31, 32]))];

        yield 'contact groups' => [static fn (self $test): UpdateServiceCommand => $test->command(notifications: $test->notifications(contactGroupIds: [41, 42]))];

        yield 'service groups' => [static fn (self $test): UpdateServiceCommand => $test->command(serviceGroupIds: [80])];

        yield 'categories' => [static fn (self $test): UpdateServiceCommand => $test->command(categoryIds: [90])];

        yield 'severity' => [static fn (self $test): UpdateServiceCommand => $test->command(severityId: 70)];

        yield 'traps' => [static fn (self $test): UpdateServiceCommand => $test->command(trapIds: [100])];

        yield 'macros' => [static fn (self $test): UpdateServiceCommand => $test->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('port'), '80', isPassword: false)])];
    }

    /**
     * An own macro resent with its id is updated in place; a stored one not resent is dropped.
     */
    public function testItUpdatesAnOwnMacroInPlace(): void
    {
        $this->store(macros: [
            new ServiceMacro(new ServiceMacroName('port'), '80', isPassword: false, id: new ServiceMacroId(3)),
            new ServiceMacro(new ServiceMacroName('gone'), 'x', isPassword: false, id: new ServiceMacroId(4)),
        ]);

        $service = ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('http_port'), '8080', isPassword: false, id: new ServiceMacroId(3)),
        ]));

        self::assertSame(
            [[3, 'HTTP_PORT', '8080', false]],
            array_map(static fn (ServiceMacro $macro): array => [$macro->id?->value, $macro->name->value, $macro->value, $macro->isPassword], $service->checkOptions->macros),
        );
    }

    /**
     * The macros are resolved against what the submitted template makes the service inherit: one inherited only
     * through the stored template is unknown.
     */
    public function testItRefusesAMacroInheritedOnlyThroughTheStoredTemplate(): void
    {
        $this->serviceTemplateRepository->templates[30] = new ServiceTemplate(
            new ServiceTemplateId(30),
            new ServiceTemplateName('template-30'),
            checkCommandId: new CommandId(self::CHECK_COMMAND_ID),
            macros: new Collection([new ServiceMacro(new ServiceMacroName('url'), '/', isPassword: false, id: new ServiceMacroId(11))], ServiceMacro::class),
        );
        $this->serviceTemplateRepository->names[30] = 'template-30';
        $this->store(templateId: 30);

        try {
            ($this->handler)($this->command(templateId: null, macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('url'), '/health', isPassword: false, id: new ServiceMacroId(11), parent: ServiceMacroParentEnum::Template),
            ]));
            self::fail('Expected ServiceMacroNotFoundException');
        } catch (ServiceMacroNotFoundException $exception) {
            self::assertSame(['checkOptions' => [11]], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    public function testItRefusesAReservedMacroNameBeforeAnyVaultWrite(): void
    {
        $this->expectException(ReservedServiceMacroNameException::class);

        try {
            ($this->handler)($this->command(macroChanges: [
                new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true),
                new ServiceMacroChange(new ServiceMacroName('criticality_level'), '1', isPassword: false),
            ]));
        } finally {
            self::assertSame([], $this->vault->writeManyCalls);
        }
    }

    /**
     * A new password goes under the entry the service already has.
     */
    public function testVaultReusesTheServiceEntry(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(3))]);

        $service = ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('pwd'), null, isPassword: true, id: new ServiceMacroId(3)),
            new ServiceMacroChange(new ServiceMacroName('token'), 't0k3n', isPassword: true),
        ]));

        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => ['_SERVICETOKEN' => 't0k3n'], 'uuid' => 'svc-uuid', 'deletes' => []]],
            $this->vault->writeManyCalls,
        );
        self::assertSame('secret::vault::monitoring/services/svc-uuid::_SERVICETOKEN', $service->checkOptions->macros[1]->value);
    }

    /**
     * A password rewritten under the same key keeps the same reference: it is still a change, written and
     * notified (not logged: macros are not).
     */
    public function testARewrittenPasswordIsAChange(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(3))]);

        ($this->handler)($this->command(macroChanges: [
            new ServiceMacroChange(new ServiceMacroName('pwd'), 'n3w', isPassword: true, id: new ServiceMacroId(3)),
        ]));

        self::assertCount(1, $this->serviceRepository->updatedServices);
        $events = $this->eventBus->getDispatchedEvents(ServiceUpdated::class);
        self::assertCount(1, $events);
        self::assertFalse($events[0]->loggable);
    }

    /**
     * An unreachable vault is an upstream failure (HTTP 502): nothing is saved and nothing fires.
     */
    public function testVaultFailureSavesNothing(): void
    {
        $this->vault->writeThrows = true;

        try {
            ($this->handler)($this->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)]));
            self::fail('Expected VaultWriteFailedException');
        } catch (VaultWriteFailedException $exception) {
            self::assertSame('Service 5 was not updated: its credentials could not be written to the vault.', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }

        $this->assertNothingWritten();
        self::assertSame([], $this->logger->warningMessages);
    }

    /**
     * A failure after a successful vault write leaves the vault ahead of the database: it is logged as a warning,
     * and the failure is passed on.
     */
    public function testAFailureAfterTheVaultWriteIsLoggedAsAWarning(): void
    {
        $failure = new \RuntimeException('database down');
        $this->serviceRepository->updateThrows = $failure;

        try {
            ($this->handler)($this->command(macroChanges: [new ServiceMacroChange(new ServiceMacroName('pwd'), 's3cr3t', isPassword: true)]));
            self::fail('Expected the update failure');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame(self::SERVICE_ID, $this->logger->warningMessages[0]['context']['service_id']);
        self::assertSame($failure, $this->logger->warningMessages[0]['context']['exception']);
    }

    public function testAFailureWithoutVaultWriteIsNotLogged(): void
    {
        $this->serviceRepository->updateThrows = new \RuntimeException('database down');

        try {
            ($this->handler)($this->command(name: 'pong'));
            self::fail('Expected the update failure');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->logger->warningMessages);
    }

    /**
     * The last password dropped leaves the service's vault entry empty: it is purged once the save is committed,
     * on a best-effort basis, from the service as it was.
     */
    public function testAReleasedEntryIsPurgedAfterTheSave(): void
    {
        $this->store(macros: [new ServiceMacro(new ServiceMacroName('pwd'), self::SERVICE_PWD, isPassword: true, id: new ServiceMacroId(3))]);

        ($this->handler)($this->command());

        $purges = $this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class);
        self::assertCount(1, $purges);
        self::assertTrue($purges[0]->bestEffort);
        self::assertSame(self::SERVICE_PWD, $purges[0]->service->checkOptions->macros[0]->value);
        self::assertSame(['_SERVICEPWD'], $this->vault->writeManyCalls[0]['deletes']);
    }

    /**
     * @param list<string> $checkCommandArgs
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     * @param list<ServiceMacroChange> $macroChanges
     */
    public function command(
        int $id = self::SERVICE_ID,
        string $name = 'ping',
        int $hostId = self::HOST_ID,
        bool $activated = true,
        ?int $templateId = null,
        ?int $checkCommandId = self::CHECK_COMMAND_ID,
        array $checkCommandArgs = [],
        ServiceSchedulingOptions $schedulingOptions = new ServiceSchedulingOptions(),
        false|ServiceNotifications|null $notifications = false,
        ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        ServiceExtendedInformations $extendedInformations = new ServiceExtendedInformations(),
        ?int $graphTemplateId = null,
        ?int $severityId = null,
        array $serviceGroupIds = [],
        array $categoryIds = [],
        array $trapIds = [],
        ?int $actorId = null,
        array $macroChanges = [],
    ): UpdateServiceCommand {
        return new UpdateServiceCommand(
            id: new ServiceId($id),
            name: new ServiceName($name),
            hostId: new HostId($hostId),
            activated: $activated,
            actor: $actorId === self::RESTRICTED_USER_ID ? TestActingUsers::restricted($actorId) : TestActingUsers::unrestricted($actorId ?? 1),
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            checkCommandId: $checkCommandId !== null ? new CommandId($checkCommandId) : null,
            checkCommandArgs: $checkCommandArgs,
            schedulingOptions: $schedulingOptions,
            notifications: $notifications === false ? $this->notifications() : $notifications,
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            graphTemplateId: $graphTemplateId !== null ? new GraphTemplateId($graphTemplateId) : null,
            severityId: $severityId !== null ? new ServiceSeverityId($severityId) : null,
            serviceGroupIds: new Collection(array_map(static fn (int $value): ServiceGroupId => new ServiceGroupId($value), $serviceGroupIds), ServiceGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $value): ServiceCategoryId => new ServiceCategoryId($value), $categoryIds), ServiceCategoryId::class),
            trapIds: new Collection(array_map(static fn (int $value): TrapId => new TrapId($value), $trapIds), TrapId::class),
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
        array $contactIds = [31],
        array $contactGroupIds = [41],
    ): ServiceNotifications {
        return new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection(array_map(static fn (int $value): NotificationContactId => new NotificationContactId($value), $contactIds), NotificationContactId::class),
            contactGroupIds: new Collection(array_map(static fn (int $value): ContactGroupId => new ContactGroupId($value), $contactGroupIds), ContactGroupId::class),
            periodId: $periodId !== null ? new TimePeriodId($periodId) : null,
            contactAdditiveInheritance: $additiveInheritance,
            contactGroupAdditiveInheritance: $additiveInheritance,
            useOnlyContactsFromHost: $useOnlyContactsFromHost,
        );
    }

    /**
     * The stored service as the repository loads it; by default the one {@see Command()} describes.
     *
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<ServiceMacro> $macros
     */
    private function store(
        bool $activated = true,
        bool $hostActivated = true,
        ?int $templateId = null,
        ?ServiceNotifications $notifications = null,
        ServiceExtendedInformations $extendedInformations = new ServiceExtendedInformations(),
        ?int $severityId = null,
        array $serviceGroupIds = [],
        array $categoryIds = [],
        array $macros = [],
    ): void {
        $this->serviceRepository->services[self::SERVICE_ID] = new Service(
            id: new ServiceId(self::SERVICE_ID),
            name: new ServiceName('ping'),
            hostId: new HostId(self::HOST_ID),
            hostActivated: $hostActivated,
            hostPollerId: new PollerId(self::POLLER_ID),
            activated: $activated,
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            checkOptions: new ServiceCheckOptions(new CommandId(self::CHECK_COMMAND_ID), [], $macros),
            notifications: $notifications ?? $this->notifications(),
            extendedInformations: $extendedInformations,
            severityId: $severityId !== null ? new ServiceSeverityId($severityId) : null,
            serviceGroupIds: new Collection(array_map(static fn (int $value): ServiceGroupId => new ServiceGroupId($value), $serviceGroupIds), ServiceGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $value): ServiceCategoryId => new ServiceCategoryId($value), $categoryIds), ServiceCategoryId::class),
        );
        $this->vault->extractedUuids[self::SERVICE_PWD] = 'svc-uuid';
        $this->vault->resolved[self::SERVICE_PWD] = 'stored-secret';
    }

    /**
     * The actor sees host 10 only, service group 80, category 90, severity 70, contact 31 and contact group 41.
     */
    private function restrictTheActor(): void
    {
        $this->resourceAccess->accessibleHostIds = new Collection([new HostId(self::HOST_ID)], HostId::class);
        $this->resourceAccess->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId(80)]);
        $this->resourceAccess->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, [new ServiceCategoryId(90)]);
        $this->resourceAccess->serviceSeverityScope = AccessScope::restrictedTo(ServiceSeverityId::class, [new ServiceSeverityId(70)]);
        $this->resourceAccess->accessibleContactIds = new Collection([new NotificationContactId(31)], NotificationContactId::class);
        $this->resourceAccess->accessibleContactGroupIds = new Collection([new ContactGroupId(41)], ContactGroupId::class);
    }

    private function assertNothingWritten(): void
    {
        self::assertSame([], $this->serviceRepository->updatedServices);
        self::assertSame([], $this->serviceRepository->relationsReplacedServices);
        foreach ([ServiceUpdated::class, ServiceEnabled::class, ServiceDisabled::class, ServiceVaultPurgeRequested::class] as $eventClass) {
            self::assertSame([], $this->eventBus->getDispatchedEvents($eventClass), $eventClass);
        }
    }

    /**
     * Every reference a service can point to, each existing with one id: host 12 on poller 4, template 30 (with
     * the check command), a second check command 22 of another type, event handler 21, time periods 40 and 41,
     * icon 50, graph template 60, severities 70, 71 and 72, service groups 80 and 81, categories 90 and 91, trap
     * 100.
     */
    private function seedEveryReference(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::OTHER_POLLER_ID));
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addCommand(22, CommandTypeEnum::Notification);
        $this->timePeriodRepository->existingIds = [40, 41];
        $this->mediaRepository->existingIds = [50];
        $this->graphTemplateRepository->names = [60 => 'graph'];
        $this->serviceSeverityRepository->names = [70 => 'severity-70', 71 => 'severity-71', 72 => 'severity-72'];
        $this->serviceGroupRepository->names = [80 => 'group-80', 81 => 'group-81'];
        $this->serviceCategoryRepository->names = [90 => 'category-90', 91 => 'category-91'];
        $this->trapRepository->traps[100] = new Trap(
            new TrapId(100),
            new TrapName('linkDown'),
            new TrapVendor(new TrapVendorId(1), new TrapVendorName('Generic')),
        );
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
