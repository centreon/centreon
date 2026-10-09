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
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostCommandHandler;
use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ExclusiveNotificationOptionException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;

final class PatchHostCommandHandlerRelationsTest extends TestCase
{
    private const HOST_ID = 5;
    private const VIEWER_ID = 7;

    private FakeHostRepository $repository;

    private FakeHostGroupRepository $hostGroupRepository;

    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakeHostCategoryRepository $hostCategoryRepository;

    private FakeNotificationContactRepository $contactRepository;

    private FakeServiceRepository $serviceRepository;

    private FakeContactGroupRepository $contactGroupRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private EventBusSpy $eventBus;

    private PatchHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->hostGroupRepository = new FakeHostGroupRepository();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->hostCategoryRepository = new FakeHostCategoryRepository();
        $this->contactRepository = new FakeNotificationContactRepository();
        $this->serviceRepository = new FakeServiceRepository();
        $this->contactGroupRepository = new FakeContactGroupRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->eventBus = new EventBusSpy();
        $vault = new FakeVault();
        $commandRepository = new FakeCommandRepository();

        $this->handler = new PatchHostCommandHandler(
            $this->repository,
            $commandRepository,
            new HostReferencesChecker(
                new FakePollerRepository(),
                new FakeHostSeverityRepository(),
                new FakeTimezoneRepository(),
                new FakeTimePeriodRepository(),
                new FakeMediaRepository(),
                $commandRepository,
                $this->resourceAccessRepository,
            ),
            new HostRelationsUpdater(
                $this->hostGroupRepository,
                $this->hostTemplateRepository,
                $this->hostCategoryRepository,
                $this->repository,
                $this->contactRepository,
                $this->contactGroupRepository,
                $this->resourceAccessRepository,
            ),
            new HostTemplateServicesCleaner($this->hostTemplateRepository, $this->serviceRepository, $this->eventBus),
            $vault,
            new VaultCredentialWriter($vault),
            new AdditiveInheritanceModeApplier(new FakeOptionRepository()),
            $this->eventBus,
        );

        foreach ([1, 2, 3] as $id) {
            $this->hostGroupRepository->hostGroups[$id] = new HostGroup(new HostGroupId($id), new HostGroupName('group-' . $id));
            $this->hostTemplateRepository->hostTemplates[$id] = new HostTemplate(new HostTemplateId($id), new HostTemplateName('template-' . $id), new Collection([], HostMacro::class));
            $this->hostCategoryRepository->hostCategories[$id] = new HostCategory(new HostCategoryId($id), new HostCategoryName('category-' . $id));
            $this->contactRepository->names[$id] = new NotificationContactName('contact-' . $id);
            $this->contactGroupRepository->names[$id] = new ContactGroupName('contact-group-' . $id);
        }
    }

    public function testItReplacesTheHostGroups(): void
    {
        $this->seedHost(hostGroupIds: [1]);

        $result = ($this->handler)($this->command(hostGroupIds: ListChange::replace([new HostGroupId(2), new HostGroupId(3)])));

        self::assertSame([2, 3], $this->ids($result->hostGroupIds));
        self::assertCount(1, $this->repository->relationsReplacedHosts);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostMassChanged::class, 1));
    }

    public function testItAddsAndRemovesHostGroupsWithoutTouchingTheOthers(): void
    {
        $this->seedHost(hostGroupIds: [1, 2]);

        $added = ($this->handler)($this->command(hostGroupIds: ListChange::add([new HostGroupId(3)])));
        $removed = ($this->handler)($this->command(hostGroupIds: ListChange::remove([new HostGroupId(1)])));

        self::assertSame([1, 2, 3], $this->ids($added->hostGroupIds));
        self::assertSame([2, 3], $this->ids($removed->hostGroupIds));
    }

    public function testClearingTheHostGroupsIsAChange(): void
    {
        $this->seedHost(hostGroupIds: [1]);

        $result = ($this->handler)($this->command(hostGroupIds: ListChange::replace([])));

        self::assertSame([], $this->ids($result->hostGroupIds));
    }

    public function testAnUnchangedListWritesNothing(): void
    {
        $this->seedHost(hostGroupIds: [1, 2]);

        ($this->handler)($this->command(hostGroupIds: ListChange::add([new HostGroupId(2)])));

        self::assertSame([], $this->repository->updatedHosts);
        self::assertSame([], $this->repository->relationsReplacedHosts);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostMassChanged::class));
    }

    public function testAChangeOfAnotherFieldDoesNotRewriteTheRelations(): void
    {
        $this->seedHost(hostGroupIds: [1]);

        ($this->handler)($this->command(alias: new HostAlias('front')));

        self::assertCount(1, $this->repository->updatedHosts);
        self::assertSame([], $this->repository->relationsReplacedHosts);
    }

    public function testAReplacementKeepsTheHostGroupsTheViewerCannotSee(): void
    {
        $this->seedHost(hostGroupIds: [1, 2]);
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(2), new HostGroupId(3)], HostGroupId::class);

        $result = ($this->handler)($this->command(hostGroupIds: ListChange::replace([new HostGroupId(3)]), viewerId: new UserId(self::VIEWER_ID)));

        self::assertSame([3, 1], $this->ids($result->hostGroupIds));
    }

    public function testARemovalLeavesTheHostGroupsTheViewerCannotSee(): void
    {
        $this->seedHost(hostGroupIds: [1, 2]);
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(2)], HostGroupId::class);

        $result = ($this->handler)($this->command(hostGroupIds: ListChange::remove([new HostGroupId(1), new HostGroupId(2)]), viewerId: new UserId(self::VIEWER_ID)));

        self::assertSame([1], $this->ids($result->hostGroupIds));
    }

    public function testAHostGroupTheViewerCannotSeeCannotBeAdded(): void
    {
        $this->seedHost();
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(2)], HostGroupId::class);

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)($this->command(hostGroupIds: ListChange::add([new HostGroupId(1)]), viewerId: new UserId(self::VIEWER_ID)));
    }

    public function testAnUnknownHostGroupIsRejected(): void
    {
        $this->seedHost();

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)($this->command(hostGroupIds: ListChange::replace([new HostGroupId(99)])));
    }

    public function testTemplatesAreAddedAfterTheExistingOnes(): void
    {
        $this->seedHost(templateIds: [2]);

        $result = ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(1)])));

        self::assertSame([2, 1], $this->ids($result->templateIds));
    }

    public function testAnUnknownTemplateIsRejected(): void
    {
        $this->seedHost();

        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(99)])));
    }

    public function testCategoriesAreReplacedAndAnInaccessibleOneIsRejected(): void
    {
        $this->seedHost(categoryIds: [1]);

        $result = ($this->handler)($this->command(categoryIds: ListChange::replace([new HostCategoryId(2)])));
        self::assertSame([2], $this->ids($result->categoryIds));

        $this->resourceAccessRepository->accessibleHostCategoryIds = new Collection([new HostCategoryId(3)], HostCategoryId::class);
        $this->expectException(HostCategoryNotFoundException::class);

        ($this->handler)($this->command(categoryIds: ListChange::add([new HostCategoryId(1)]), viewerId: new UserId(self::VIEWER_ID)));
    }

    public function testParentsAndChildrenAreChanged(): void
    {
        $this->seedHost();
        $this->seedOtherHost(6);
        $this->seedOtherHost(7);

        $result = ($this->handler)($this->command(
            parentHostIds: ListChange::replace([new HostId(6)]),
            childHostIds: ListChange::add([new HostId(7)]),
        ));

        self::assertSame([6], $this->ids($result->parentHostIds));
        self::assertSame([7], $this->ids($result->childHostIds));
    }

    public function testAHostTheViewerCannotSeeCannotBeLinked(): void
    {
        $this->seedHost();
        $this->seedOtherHost(6);
        $this->repository->accessibleHostIds = [self::HOST_ID];

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command(parentHostIds: ListChange::add([new HostId(6)]), viewerId: new UserId(self::VIEWER_ID)));
    }

    public function testAReplacementKeepsTheLinksToHostsTheViewerCannotSee(): void
    {
        $this->seedHost(parentIds: [6], childIds: [8]);
        $this->seedOtherHost(6);
        $this->seedOtherHost(7);
        $this->seedOtherHost(8);
        $this->repository->accessibleHostIds = [self::HOST_ID, 7];

        $result = ($this->handler)($this->command(
            parentHostIds: ListChange::replace([new HostId(7)]),
            childHostIds: ListChange::remove([new HostId(8)]),
            viewerId: new UserId(self::VIEWER_ID),
        ));

        self::assertSame([7, 6], $this->ids($result->parentHostIds));
        self::assertSame([8], $this->ids($result->childHostIds));
    }

    public function testAnUnknownParentIsRejected(): void
    {
        $this->seedHost();

        $this->expectException(HostNotFoundException::class);

        ($this->handler)($this->command(parentHostIds: ListChange::add([new HostId(99)])));
    }

    public function testAHostCannotBeItsOwnParent(): void
    {
        $this->seedHost();

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(parentHostIds: ListChange::add([new HostId(self::HOST_ID)])));
    }

    public function testAHostCannotBeBothAParentAndAChild(): void
    {
        $this->seedHost();
        $this->seedOtherHost(6);

        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(
            parentHostIds: ListChange::add([new HostId(6)]),
            childHostIds: ListChange::add([new HostId(6)]),
        ));
    }

    public function testALoopThroughTheRestOfTheGraphIsRejected(): void
    {
        $this->seedHost();
        $this->seedOtherHost(6);
        $this->seedOtherHost(7, parentIds: [6]);

        // 7 is a child of 6: making 7 a child of this host while 6 is its parent closes a loop.
        $this->expectException(CircularHostRelationException::class);

        ($this->handler)($this->command(
            parentHostIds: ListChange::add([new HostId(7)]),
            childHostIds: ListChange::add([new HostId(6)]),
        ));
    }

    public function testTheContactsAndTheContactGroupsAreChangedIndependently(): void
    {
        $this->seedHost(contactIds: [1], contactGroupIds: [1]);

        $result = ($this->handler)($this->command(
            contactIds: ListChange::add([new NotificationContactId(2)]),
            contactGroupIds: ListChange::replace([new ContactGroupId(3)]),
        ));

        self::assertSame([1, 2], $this->contactIdsOf($result));
        self::assertSame([3], $this->contactGroupIdsOf($result));
        self::assertCount(1, $this->repository->relationsReplacedHosts);
    }

    public function testAReplacementKeepsTheContactsTheViewerCannotSee(): void
    {
        $this->seedHost(contactIds: [1, 2]);
        $this->resourceAccessRepository->accessibleContactIds = new Collection([new NotificationContactId(2), new NotificationContactId(3)], NotificationContactId::class);

        $result = ($this->handler)($this->command(contactIds: ListChange::replace([new NotificationContactId(3)]), viewerId: new UserId(self::VIEWER_ID)));

        self::assertSame([3, 1], $this->contactIdsOf($result));
    }

    public function testAnUnknownContactAndAnUnknownContactGroupAreRejected(): void
    {
        $this->seedHost();

        try {
            ($this->handler)($this->command(contactIds: ListChange::add([new NotificationContactId(99)])));
            self::fail('The contact should have been rejected.');
        } catch (NotificationContactNotFoundException) {
        }

        $this->expectException(ContactGroupNotFoundException::class);

        ($this->handler)($this->command(contactGroupIds: ListChange::add([new ContactGroupId(99)])));
    }

    public function testNoneCannotJoinTheOptionsTheHostAlreadyHas(): void
    {
        $this->seedHost(options: [NotificationOptionEnum::Down]);
        /** @var ListChange<NotificationOptionEnum> $add */
        $add = ListChange::add([NotificationOptionEnum::None]);

        $this->expectException(ExclusiveNotificationOptionException::class);

        ($this->handler)($this->command(notifications: new NotificationsChanges(options: $add)));
    }

    public function testNotificationOptionsAreAddedToAndRemovedFromTheCurrentOnes(): void
    {
        $this->seedHost(options: [NotificationOptionEnum::Down]);
        /** @var ListChange<NotificationOptionEnum> $add */
        $add = ListChange::add([NotificationOptionEnum::Flapping]);
        /** @var ListChange<NotificationOptionEnum> $remove */
        $remove = ListChange::remove([NotificationOptionEnum::Down]);

        $added = ($this->handler)($this->command(notifications: new NotificationsChanges(options: $add)));
        $removed = ($this->handler)($this->command(notifications: new NotificationsChanges(options: $remove)));

        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Flapping], $this->optionsOf($added));
        self::assertSame([NotificationOptionEnum::Flapping], $this->optionsOf($removed));
    }

    public function testRemovingATemplateDeletesTheServicesOnlyItProvides(): void
    {
        $this->seedHost(templateIds: [1, 2]);
        $this->hostTemplateRepository->serviceTemplateIdsByTemplate = [1 => [10], 2 => [20]];
        $fromFirst = $this->seedService('ping', serviceTemplateId: 10);
        $fromSecond = $this->seedService('disk', serviceTemplateId: 20);

        ($this->handler)($this->command(templateIds: ListChange::remove([new HostTemplateId(1)])));

        self::assertArrayNotHasKey($fromFirst->id()->value, $this->serviceRepository->services);
        self::assertArrayHasKey($fromSecond->id()->value, $this->serviceRepository->services);
        self::assertTrue($this->eventBus->shouldHaveDispatched(ServiceDeleted::class, 1));
        self::assertTrue($this->eventBus->shouldHaveDispatched(ServiceVaultPurgeRequested::class, 1));
    }

    public function testAServiceAKeptTemplateAlsoProvidesIsKept(): void
    {
        $this->seedHost(templateIds: [1, 2]);
        $this->hostTemplateRepository->serviceTemplateIdsByTemplate = [1 => [10], 2 => [10]];
        $service = $this->seedService('ping', serviceTemplateId: 10);

        ($this->handler)($this->command(templateIds: ListChange::remove([new HostTemplateId(1)])));

        self::assertArrayHasKey($service->id()->value, $this->serviceRepository->services);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(ServiceDeleted::class));
    }

    public function testReplacingTheTemplatesCleansTheServicesOfTheOnesLeft(): void
    {
        $this->seedHost(templateIds: [1]);
        $this->hostTemplateRepository->serviceTemplateIdsByTemplate = [1 => [10], 2 => [20]];
        $service = $this->seedService('ping', serviceTemplateId: 10);

        ($this->handler)($this->command(templateIds: ListChange::replace([new HostTemplateId(2)])));

        self::assertArrayNotHasKey($service->id()->value, $this->serviceRepository->services);
    }

    public function testAddingATemplateDeletesNothing(): void
    {
        $this->seedHost(templateIds: [1]);
        $this->hostTemplateRepository->serviceTemplateIdsByTemplate = [1 => [10], 2 => [20]];
        $service = $this->seedService('ping', serviceTemplateId: 10);

        ($this->handler)($this->command(templateIds: ListChange::add([new HostTemplateId(2)])));

        self::assertArrayHasKey($service->id()->value, $this->serviceRepository->services);
    }

    public function testTheServicesOfTheTemplatesCanBeCreatedOnceTheUpdateIsCommitted(): void
    {
        $this->seedHost(templateIds: [1]);

        ($this->handler)($this->command(alias: new HostAlias('front'), deployServicesFromTemplates: true));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostServicesDeploymentRequested::class, 1));
    }

    public function testNothingIsDeployedUnlessAskedOrWithoutTemplates(): void
    {
        $this->seedHost(templateIds: [1]);
        ($this->handler)($this->command(alias: new HostAlias('front')));

        $this->seedHost();
        ($this->handler)($this->command(alias: new HostAlias('back'), deployServicesFromTemplates: true));

        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostServicesDeploymentRequested::class));
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function ids(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }

    /**
     * @param NoValue|ListChange<HostGroupId> $hostGroupIds
     * @param NoValue|ListChange<HostTemplateId> $templateIds
     * @param NoValue|ListChange<HostCategoryId> $categoryIds
     * @param NoValue|ListChange<HostId> $parentHostIds
     * @param NoValue|ListChange<HostId> $childHostIds
     * @param NoValue|ListChange<NotificationContactId> $contactIds
     * @param NoValue|ListChange<ContactGroupId> $contactGroupIds
     */
    private function command(
        NoValue|ListChange $hostGroupIds = new NoValue(),
        NoValue|ListChange $templateIds = new NoValue(),
        NoValue|ListChange $categoryIds = new NoValue(),
        NoValue|ListChange $parentHostIds = new NoValue(),
        NoValue|ListChange $childHostIds = new NoValue(),
        NoValue|ListChange $contactIds = new NoValue(),
        NoValue|ListChange $contactGroupIds = new NoValue(),
        NoValue|NotificationsChanges $notifications = new NoValue(),
        NoValue|HostAlias $alias = new NoValue(),
        bool $deployServicesFromTemplates = false,
        ?UserId $viewerId = null,
    ): PatchHostCommand {
        return new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            updatedBy: 1,
            alias: $alias,
            notifications: $notifications,
            templateIds: $templateIds,
            hostGroupIds: $hostGroupIds,
            categoryIds: $categoryIds,
            parentHostIds: $parentHostIds,
            childHostIds: $childHostIds,
            contactIds: $contactIds,
            contactGroupIds: $contactGroupIds,
            deployServicesFromTemplates: $deployServicesFromTemplates,
            viewerId: $viewerId,
        );
    }

    /**
     * @return list<int>
     */
    private function contactIdsOf(Host $host): array
    {
        \assert($host->notifications instanceof Notifications);

        return $this->ids($host->notifications->contactIds);
    }

    /**
     * @return list<int>
     */
    private function contactGroupIdsOf(Host $host): array
    {
        \assert($host->notifications instanceof Notifications);

        return $this->ids($host->notifications->contactGroupIds);
    }

    /**
     * @return list<NotificationOptionEnum>
     */
    private function optionsOf(Host $host): array
    {
        \assert($host->notifications instanceof Notifications);

        return $host->notifications->options;
    }

    /**
     * @param list<int> $hostGroupIds
     * @param list<int> $templateIds
     * @param list<int> $categoryIds
     * @param list<int> $contactIds
     * @param list<int> $contactGroupIds
     * @param list<NotificationOptionEnum> $options
     * @param list<int> $parentIds
     * @param list<int> $childIds
     */
    private function seedHost(
        array $hostGroupIds = [],
        array $templateIds = [],
        array $categoryIds = [],
        array $contactIds = [],
        array $contactGroupIds = [],
        array $options = [],
        array $parentIds = [],
        array $childIds = [],
    ): Host {
        return $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $templateIds), HostTemplateId::class),
            hostGroupIds: new Collection(array_map(static fn (int $id): HostGroupId => new HostGroupId($id), $hostGroupIds), HostGroupId::class),
            categoryIds: new Collection(array_map(static fn (int $id): HostCategoryId => new HostCategoryId($id), $categoryIds), HostCategoryId::class),
            parentHostIds: new Collection(array_map(static fn (int $id): HostId => new HostId($id), $parentIds), HostId::class),
            childHostIds: new Collection(array_map(static fn (int $id): HostId => new HostId($id), $childIds), HostId::class),
            notifications: new Notifications(
                enabled: TriStateEnum::UseDefault,
                contactIds: new Collection(array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $contactIds), NotificationContactId::class),
                contactGroupIds: new Collection(array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), $contactGroupIds), ContactGroupId::class),
                options: $options,
            ),
        ), self::HOST_ID);
    }

    private function seedService(string $name, int $serviceTemplateId): Service
    {
        $service = new Service(null, new ServiceName($name), new HostId(self::HOST_ID));
        $this->serviceRepository->add($service);
        $this->serviceRepository->serviceTemplateIdOf[$service->id()->value] = $serviceTemplateId;

        return $service;
    }

    /**
     * @param list<int> $parentIds
     */
    private function seedOtherHost(int $id, array $parentIds = []): void
    {
        $this->repository->seed(new Host(
            id: null,
            name: new HostName('server-' . $id),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: new Collection(array_map(static fn (int $parentId): HostId => new HostId($parentId), $parentIds), HostId::class),
        ), $id);
    }
}
