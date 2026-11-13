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

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommandHandler;
use App\MonitoringConfiguration\Application\Command\ServiceCheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
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
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendor;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceMassChanged;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
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
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;
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

/**
 * Boots the real container and swaps only the repositories, the vault, the logger and the event bus for fakes, so
 * the handler is built by Symfony's DI exactly as it is in production. The stored service is "ping" on host 10
 * (poller 3, enabled), with its own check command 20, contact 31 and contact group 41.
 */
abstract class PatchServiceCommandHandlerTestCase extends KernelTestCase
{
    protected const SERVICE_ID = 5;
    protected const HOST_ID = 10;
    protected const POLLER_ID = 3;
    protected const OTHER_HOST_ID = 12;
    protected const OTHER_POLLER_ID = 4;
    protected const CHECK_COMMAND_ID = 20;
    protected const RESTRICTED_USER_ID = 7;
    protected const SERVICE_PWD = 'secret::vault::monitoring/services/svc-uuid::_SERVICEPWD';

    protected PatchServiceCommandHandler $handler;

    protected FakeServiceRepository $serviceRepository;

    protected FakeHostRepository $hostRepository;

    protected FakeResourceAccessRepository $resourceAccess;

    protected FakeServiceTemplateRepository $serviceTemplateRepository;

    protected FakeServiceGroupRepository $serviceGroupRepository;

    protected FakeServiceCategoryRepository $serviceCategoryRepository;

    protected FakeServiceSeverityRepository $serviceSeverityRepository;

    protected FakeTrapRepository $trapRepository;

    protected FakeGraphTemplateRepository $graphTemplateRepository;

    protected FakeTimePeriodRepository $timePeriodRepository;

    protected FakeMediaRepository $mediaRepository;

    protected FakeCommandRepository $commandRepository;

    protected FakeOptionRepository $optionRepository;

    protected FakeNotificationContactRepository $contactRepository;

    protected FakeContactGroupRepository $contactGroupRepository;

    protected EventBusSpy $eventBus;

    protected FakeVault $vault;

    protected LoggerSpy $logger;

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
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(StandardMacroRepository::class, new FakeStandardMacroRepository(['$_SERVICECRITICALITY_LEVEL$']));
        $container->set(LoggerInterface::class, $this->logger);

        /** @var PatchServiceCommandHandler $handler */
        $handler = $container->get(PatchServiceCommandHandler::class);
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

    /**
     * @param NoValue|ListChange<ServiceGroupId> $serviceGroupIds
     * @param NoValue|ListChange<ServiceCategoryId> $categoryIds
     * @param NoValue|ListChange<TrapId> $trapIds
     * @param NoValue|ListChange<NotificationContactId> $contactIds
     * @param NoValue|ListChange<ContactGroupId> $contactGroupIds
     */
    public function command(
        ?int $actorId = null,
        int $id = self::SERVICE_ID,
        NoValue|bool $activated = new NoValue(),
        NoValue|string $name = new NoValue(),
        NoValue|int $hostId = new NoValue(),
        NoValue|int|null $templateId = new NoValue(),
        NoValue|int|null $severityId = new NoValue(),
        NoValue|int|null $graphTemplateId = new NoValue(),
        NoValue|ListChange $serviceGroupIds = new NoValue(),
        NoValue|ListChange $categoryIds = new NoValue(),
        NoValue|ListChange $trapIds = new NoValue(),
        NoValue|ServiceCheckOptionsChanges $checkOptions = new NoValue(),
        NoValue|ServiceMacroChanges $macros = new NoValue(),
        NoValue|ServiceSchedulingOptionsChanges $schedulingOptions = new NoValue(),
        NoValue|ServiceExtendedInformationsChanges $extendedInformations = new NoValue(),
        NoValue|ServiceNotificationsChanges $notifications = new NoValue(),
        NoValue|ListChange $contactIds = new NoValue(),
        NoValue|ListChange $contactGroupIds = new NoValue(),
        NoValue|ServiceDataProcessingChanges $dataProcessing = new NoValue(),
    ): PatchServiceCommand {
        return new PatchServiceCommand(
            id: new ServiceId($id),
            actor: $actorId === self::RESTRICTED_USER_ID ? TestActingUsers::restricted($actorId) : TestActingUsers::unrestricted($actorId ?? 1),
            activated: $activated,
            name: is_string($name) ? new ServiceName($name) : $name,
            hostId: is_int($hostId) ? new HostId($hostId) : $hostId,
            templateId: is_int($templateId) ? new ServiceTemplateId($templateId) : $templateId,
            severityId: is_int($severityId) ? new ServiceSeverityId($severityId) : $severityId,
            graphTemplateId: is_int($graphTemplateId) ? new GraphTemplateId($graphTemplateId) : $graphTemplateId,
            serviceGroupIds: $serviceGroupIds,
            categoryIds: $categoryIds,
            trapIds: $trapIds,
            checkOptions: $checkOptions,
            macros: $macros,
            schedulingOptions: $schedulingOptions,
            extendedInformations: $extendedInformations,
            notifications: $notifications,
            contactIds: $contactIds,
            contactGroupIds: $contactGroupIds,
            dataProcessing: $dataProcessing,
        );
    }

    /**
     * @param list<int> $contactIds
     * @param list<int> $contactGroupIds
     */
    public function notifications(
        bool $useOnlyContactsFromHost = false,
        bool $additiveInheritance = false,
        array $contactIds = [31],
        array $contactGroupIds = [41],
    ): ServiceNotifications {
        return new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection(array_map(static fn (int $value): NotificationContactId => new NotificationContactId($value), $contactIds), NotificationContactId::class),
            contactGroupIds: new Collection(array_map(static fn (int $value): ContactGroupId => new ContactGroupId($value), $contactGroupIds), ContactGroupId::class),
            contactAdditiveInheritance: $additiveInheritance,
            contactGroupAdditiveInheritance: $additiveInheritance,
            useOnlyContactsFromHost: $useOnlyContactsFromHost,
        );
    }

    /**
     * The stored service as the repository loads it.
     *
     * @param list<string> $args
     * @param list<int> $serviceGroupIds
     * @param list<int> $categoryIds
     * @param list<int> $trapIds
     * @param list<ServiceMacro> $macros
     */
    protected function store(
        bool $activated = true,
        bool $hostActivated = true,
        ?int $templateId = null,
        ?int $checkCommandId = self::CHECK_COMMAND_ID,
        array $args = [],
        ?ServiceNotifications $notifications = null,
        ServiceDataProcessing $dataProcessing = new ServiceDataProcessing(),
        ?ServiceExtendedInformations $extendedInformations = new ServiceExtendedInformations(),
        ?int $severityId = null,
        array $serviceGroupIds = [],
        array $categoryIds = [],
        array $trapIds = [],
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
            checkOptions: new ServiceCheckOptions($checkCommandId !== null ? new CommandId($checkCommandId) : null, $args, $macros),
            notifications: $notifications ?? $this->notifications(),
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            severityId: $severityId !== null ? new ServiceSeverityId($severityId) : null,
            serviceGroupIds: new Collection(array_map(static fn (int $value): ServiceGroupId => new ServiceGroupId($value), $serviceGroupIds), ServiceGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $value): ServiceCategoryId => new ServiceCategoryId($value), $categoryIds), ServiceCategoryId::class),
            trapIds: new Collection(array_map(static fn (int $value): TrapId => new TrapId($value), $trapIds), TrapId::class),
        );
        $this->vault->extractedUuids[self::SERVICE_PWD] = 'svc-uuid';
        $this->vault->resolved[self::SERVICE_PWD] = 'stored-secret';
    }

    protected function patch(PatchServiceCommand $command): void
    {
        ($this->handler)($command);
    }

    /**
     * The service as the last mass change wrote it.
     */
    protected function written(): Service
    {
        self::assertCount(1, $this->serviceRepository->updatedServices);
        self::assertSame($this->serviceRepository->updatedServices, $this->serviceRepository->relationsReplacedServices);

        return $this->serviceRepository->updatedServices[0];
    }

    protected function massChange(): ServiceMassChanged
    {
        $events = $this->eventBus->getDispatchedEvents(ServiceMassChanged::class);
        self::assertCount(1, $events);

        return $events[0];
    }

    /**
     * The actor sees host 10 only, service group 80, category 90, severity 70, contact 31 and contact group 41.
     */
    protected function restrictTheActor(): void
    {
        $this->resourceAccess->accessibleHostIds = new Collection([new HostId(self::HOST_ID)], HostId::class);
        $this->resourceAccess->serviceGroupScope = AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId(80)]);
        $this->resourceAccess->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, [new ServiceCategoryId(90)]);
        $this->resourceAccess->serviceSeverityScope = AccessScope::restrictedTo(ServiceSeverityId::class, [new ServiceSeverityId(70)]);
        $this->resourceAccess->accessibleContactIds = new Collection([new NotificationContactId(31)], NotificationContactId::class);
        $this->resourceAccess->accessibleContactGroupIds = new Collection([new ContactGroupId(41)], ContactGroupId::class);
    }

    protected function assertNothingWritten(): void
    {
        self::assertSame([], $this->serviceRepository->updatedServices);
        self::assertSame([], $this->serviceRepository->relationsReplacedServices);
        self::assertSame([], $this->serviceRepository->activationUpdates);
        foreach ([ServiceMassChanged::class, ServiceEnabled::class, ServiceDisabled::class, ServiceVaultPurgeRequested::class] as $eventClass) {
            self::assertSame([], $this->eventBus->getDispatchedEvents($eventClass), $eventClass);
        }
    }

    /**
     * Every reference a service can point to, each existing with one id: host 12 on poller 4, template 30 (with the
     * check command), event handler 21, a command 22 of another type, time periods 40 and 41, icon 50, graph
     * template 60, severities 70, 71 and 72, service groups 80, 81 and 82, categories 90, 91 and 92, traps 100 and
     * 101.
     */
    protected function seedEveryReference(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::OTHER_POLLER_ID));
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addCommand(22, CommandTypeEnum::Notification);
        $this->timePeriodRepository->existingIds = [40, 41];
        $this->mediaRepository->existingIds = [50];
        $this->graphTemplateRepository->names = [60 => 'graph'];
        $this->serviceSeverityRepository->names = [70 => 'severity-70', 71 => 'severity-71', 72 => 'severity-72'];
        $this->serviceGroupRepository->names = [80 => 'group-80', 81 => 'group-81', 82 => 'group-82'];
        $this->serviceCategoryRepository->names = [90 => 'category-90', 91 => 'category-91', 92 => 'category-92'];
        foreach ([100 => 'linkDown', 101 => 'linkUp'] as $trapId => $trapName) {
            $this->trapRepository->traps[$trapId] = new Trap(
                new TrapId($trapId),
                new TrapName($trapName),
                new TrapVendor(new TrapVendorId(1), new TrapVendorName('Generic')),
            );
        }
    }

    protected function addTemplate(int $id, ?int $parentId = null, ?int $checkCommandId = null, ?int $eventHandlerCommandId = null): void
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

    protected function addCommand(
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
    protected function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }
}
