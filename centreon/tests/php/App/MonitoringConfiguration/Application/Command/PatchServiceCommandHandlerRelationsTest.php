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
use App\MonitoringConfiguration\Application\Command\ListChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Event\ServiceMassChanged;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TrapNotFoundException;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The lists a partial update changes with three keys: replaced, added to, or removed from. The stored service has
 * service groups 80 and 81, categories 90 and 91, traps 100, contacts 31 and 32, contact groups 41 and 42, the
 * warning and critical notification options and the ok and warning stalking options. Contact group 43 exists too.
 */
final class PatchServiceCommandHandlerRelationsTest extends PatchServiceCommandHandlerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedEveryReference();
        $this->store(
            notifications: new ServiceNotifications(
                TriStateEnum::True,
                $this->notifications(contactIds: [31, 32])->contactIds,
                $this->notifications(contactGroupIds: [41, 42])->contactGroupIds,
                [ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Critical],
            ),
            dataProcessing: new ServiceDataProcessing(stalkingOptions: [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Warning]),
            serviceGroupIds: [80, 81],
            categoryIds: [90, 91],
            trapIds: [100],
        );
        $this->contactGroupRepository->names[43] = new ContactGroupName('group-43');
    }

    /**
     * @param \Closure(self): PatchServiceCommand $command
     * @param \Closure(Service): list<mixed> $read
     * @param list<mixed> $expected
     */
    #[DataProvider('listChangeProvider')]
    public function testEachListIsReplacedAddedToAndRemovedFrom(\Closure $command, \Closure $read, array $expected): void
    {
        $this->patch($command($this));

        self::assertSame($expected, $read($this->written()));
        self::assertCount(1, $this->eventBus->getDispatchedEvents(ServiceMassChanged::class));
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, \Closure(Service): list<mixed>, list<mixed>}>
     */
    public static function listChangeProvider(): iterable
    {
        $serviceGroups = static fn (Service $service): array => self::values($service->serviceGroupIds->toArray());

        yield 'service groups replaced' => [static fn (self $test): PatchServiceCommand => $test->command(serviceGroupIds: ListChange::replace([new ServiceGroupId(82)])), $serviceGroups, [82]];

        yield 'service groups added to' => [static fn (self $test): PatchServiceCommand => $test->command(serviceGroupIds: ListChange::add([new ServiceGroupId(82), new ServiceGroupId(80)])), $serviceGroups, [80, 81, 82]];

        yield 'service groups removed from' => [static fn (self $test): PatchServiceCommand => $test->command(serviceGroupIds: ListChange::remove([new ServiceGroupId(80), new ServiceGroupId(999)])), $serviceGroups, [81]];

        yield 'service groups emptied' => [static fn (self $test): PatchServiceCommand => $test->command(serviceGroupIds: ListChange::replace([])), $serviceGroups, []];

        $categories = static fn (Service $service): array => self::values($service->categoryIds->toArray());

        yield 'categories replaced' => [static fn (self $test): PatchServiceCommand => $test->command(categoryIds: ListChange::replace([new ServiceCategoryId(92)])), $categories, [92]];

        yield 'categories added to' => [static fn (self $test): PatchServiceCommand => $test->command(categoryIds: ListChange::add([new ServiceCategoryId(92)])), $categories, [90, 91, 92]];

        yield 'categories removed from' => [static fn (self $test): PatchServiceCommand => $test->command(categoryIds: ListChange::remove([new ServiceCategoryId(91)])), $categories, [90]];

        $traps = static fn (Service $service): array => self::values($service->trapIds->toArray());

        yield 'traps replaced' => [static fn (self $test): PatchServiceCommand => $test->command(trapIds: ListChange::replace([new TrapId(101)])), $traps, [101]];

        yield 'traps added to' => [static fn (self $test): PatchServiceCommand => $test->command(trapIds: ListChange::add([new TrapId(101)])), $traps, [100, 101]];

        yield 'traps removed from' => [static fn (self $test): PatchServiceCommand => $test->command(trapIds: ListChange::remove([new TrapId(100)])), $traps, []];

        $contacts = static fn (Service $service): array => self::values($service->notifications?->contactIds->toArray() ?? []);

        yield 'contacts replaced' => [static fn (self $test): PatchServiceCommand => $test->command(contactIds: ListChange::replace([new NotificationContactId(33)])), $contacts, [33]];

        yield 'contacts added to' => [static fn (self $test): PatchServiceCommand => $test->command(contactIds: ListChange::add([new NotificationContactId(33)])), $contacts, [31, 32, 33]];

        yield 'contacts removed from' => [static fn (self $test): PatchServiceCommand => $test->command(contactIds: ListChange::remove([new NotificationContactId(31)])), $contacts, [32]];

        $contactGroups = static fn (Service $service): array => self::values($service->notifications?->contactGroupIds->toArray() ?? []);

        yield 'contact groups replaced' => [static fn (self $test): PatchServiceCommand => $test->command(contactGroupIds: ListChange::replace([new ContactGroupId(41)])), $contactGroups, [41]];

        yield 'contact groups added to' => [static fn (self $test): PatchServiceCommand => $test->command(contactGroupIds: ListChange::add([new ContactGroupId(43)])), $contactGroups, [41, 42, 43]];

        yield 'contact groups removed from' => [static fn (self $test): PatchServiceCommand => $test->command(contactGroupIds: ListChange::remove([new ContactGroupId(42)])), $contactGroups, [41]];

        $options = static fn (Service $service): array => $service->notifications->options ?? [];

        yield 'notification options replaced' => [
            static fn (self $test): PatchServiceCommand => $test->command(notifications: new ServiceNotificationsChanges(options: self::notificationOptions(ListChangeModeEnum::Replace, ServiceNotificationOptionEnum::Recovery))),
            $options,
            [ServiceNotificationOptionEnum::Recovery],
        ];

        yield 'notification options added to' => [
            static fn (self $test): PatchServiceCommand => $test->command(notifications: new ServiceNotificationsChanges(options: self::notificationOptions(ListChangeModeEnum::Add, ServiceNotificationOptionEnum::Unknown))),
            $options,
            [ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Unknown, ServiceNotificationOptionEnum::Critical],
        ];

        yield 'notification options removed from' => [
            static fn (self $test): PatchServiceCommand => $test->command(notifications: new ServiceNotificationsChanges(options: self::notificationOptions(ListChangeModeEnum::Remove, ServiceNotificationOptionEnum::Warning))),
            $options,
            [ServiceNotificationOptionEnum::Critical],
        ];

        $stalking = static fn (Service $service): array => $service->dataProcessing->stalkingOptions;

        yield 'stalking options replaced' => [
            static fn (self $test): PatchServiceCommand => $test->command(dataProcessing: new ServiceDataProcessingChanges(stalkingOptions: self::stalkingOptions(ListChangeModeEnum::Replace, ServiceStalkingOptionEnum::Critical))),
            $stalking,
            [ServiceStalkingOptionEnum::Critical],
        ];

        yield 'stalking options added to' => [
            static fn (self $test): PatchServiceCommand => $test->command(dataProcessing: new ServiceDataProcessingChanges(stalkingOptions: self::stalkingOptions(ListChangeModeEnum::Add, ServiceStalkingOptionEnum::Unknown))),
            $stalking,
            [ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Warning, ServiceStalkingOptionEnum::Unknown],
        ];

        yield 'stalking options removed from' => [
            static fn (self $test): PatchServiceCommand => $test->command(dataProcessing: new ServiceDataProcessingChanges(stalkingOptions: self::stalkingOptions(ListChangeModeEnum::Remove, ServiceStalkingOptionEnum::Ok))),
            $stalking,
            [ServiceStalkingOptionEnum::Warning],
        ];
    }

    /**
     * A restricted actor's replacement keeps the links they cannot see, and a removal leaves them alone: they see
     * service group 80, category 90, contact 31 and contact group 41 only.
     *
     * @param \Closure(self, bool): PatchServiceCommand $command builds a replacement (true) or a removal (false) of
     *                                                           everything the list holds
     * @param \Closure(Service): list<int> $read
     */
    #[DataProvider('scopedListProvider')]
    public function testARestrictedActorKeepsWhatTheyCannotSee(\Closure $command, \Closure $read, int $hidden): void
    {
        $this->restrictTheActor();
        $stored = $this->serviceRepository->services[self::SERVICE_ID];

        foreach ([true, false] as $replace) {
            $this->serviceRepository->services[self::SERVICE_ID] = $stored;
            $this->serviceRepository->updatedServices = [];
            $this->serviceRepository->relationsReplacedServices = [];

            $this->patch($command($this, $replace));

            self::assertSame([$hidden], $read($this->written()));
        }
    }

    /**
     * @return iterable<string, array{\Closure(self, bool): PatchServiceCommand, \Closure(Service): list<int>, int}>
     */
    public static function scopedListProvider(): iterable
    {
        yield 'service groups' => [
            static fn (self $test, bool $replace): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, serviceGroupIds: $replace ? ListChange::replace([]) : ListChange::remove([new ServiceGroupId(80), new ServiceGroupId(81)])),
            static fn (Service $service): array => self::values($service->serviceGroupIds->toArray()),
            81,
        ];

        yield 'categories' => [
            static fn (self $test, bool $replace): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, categoryIds: $replace ? ListChange::replace([]) : ListChange::remove([new ServiceCategoryId(90), new ServiceCategoryId(91)])),
            static fn (Service $service): array => self::values($service->categoryIds->toArray()),
            91,
        ];

        yield 'contacts' => [
            static fn (self $test, bool $replace): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, contactIds: $replace ? ListChange::replace([]) : ListChange::remove([new NotificationContactId(31), new NotificationContactId(32)])),
            static fn (Service $service): array => self::values($service->notifications?->contactIds->toArray() ?? []),
            32,
        ];

        yield 'contact groups' => [
            static fn (self $test, bool $replace): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, contactGroupIds: $replace ? ListChange::replace([]) : ListChange::remove([new ContactGroupId(41), new ContactGroupId(42)])),
            static fn (Service $service): array => self::values($service->notifications?->contactGroupIds->toArray() ?? []),
            42,
        ];
    }

    /**
     * What a replacement or an addition brings must exist and, for a scoped list, be visible to the actor; a removal
     * is never checked.
     *
     * @param \Closure(self): PatchServiceCommand $command
     * @param class-string<AggregateNotFoundException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('refusedIdProvider')]
    public function testAnIdThatDoesNotExistOrIsHiddenIsRefused(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->restrictTheActor();

        try {
            $this->patch($command($this));
            self::fail('The id must be refused.');
        } catch (AggregateNotFoundException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, class-string, array<string, mixed>}>
     */
    public static function refusedIdProvider(): iterable
    {
        yield 'hidden service group added' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, serviceGroupIds: ListChange::add([new ServiceGroupId(82)])),
            ServiceGroupNotFoundException::class,
            ['serviceGroupIds' => [82]],
        ];

        yield 'hidden category in a replacement' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, categoryIds: ListChange::replace([new ServiceCategoryId(92)])),
            ServiceCategoryNotFoundException::class,
            ['categoryIds' => [92]],
        ];

        yield 'unknown trap added' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, trapIds: ListChange::add([new TrapId(999)])),
            TrapNotFoundException::class,
            ['trapIds' => [999]],
        ];

        yield 'hidden contact added' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, contactIds: ListChange::add([new NotificationContactId(33)])),
            NotificationContactNotFoundException::class,
            ['contactIds' => [33]],
        ];

        yield 'hidden contact group in a replacement' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, contactGroupIds: ListChange::replace([new ContactGroupId(42)])),
            ContactGroupNotFoundException::class,
            ['contactGroupIds' => [42]],
        ];
    }

    /**
     * Traps are not scoped: a restricted actor replaces them all.
     */
    public function testARestrictedActorReplacesEveryTrap(): void
    {
        $this->restrictTheActor();

        $this->patch($this->command(actorId: self::RESTRICTED_USER_ID, trapIds: ListChange::replace([])));

        self::assertSame([], self::values($this->written()->trapIds->toArray()));
    }

    /**
     * @return ListChange<ServiceNotificationOptionEnum>
     */
    private static function notificationOptions(ListChangeModeEnum $mode, ServiceNotificationOptionEnum ...$options): ListChange
    {
        return match ($mode) {
            ListChangeModeEnum::Replace => ListChange::replace(array_values($options)),
            ListChangeModeEnum::Add => ListChange::add(array_values($options)),
            ListChangeModeEnum::Remove => ListChange::remove(array_values($options)),
        };
    }

    /**
     * @return ListChange<ServiceStalkingOptionEnum>
     */
    private static function stalkingOptions(ListChangeModeEnum $mode, ServiceStalkingOptionEnum ...$options): ListChange
    {
        return match ($mode) {
            ListChangeModeEnum::Replace => ListChange::replace(array_values($options)),
            ListChangeModeEnum::Add => ListChange::add(array_values($options)),
            ListChangeModeEnum::Remove => ListChange::remove(array_values($options)),
        };
    }

    /**
     * @param array<AggregateRootId> $ids
     *
     * @return list<int>
     */
    private static function values(array $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids));
    }
}
