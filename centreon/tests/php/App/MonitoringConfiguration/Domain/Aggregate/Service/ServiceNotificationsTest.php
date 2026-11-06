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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceNotificationsTest extends TestCase
{
    public function testItAcceptsEveryOptionalFieldLeftEmpty(): void
    {
        $notifications = $this->createNotifications();

        self::assertSame([], $notifications->options);
        self::assertNull($notifications->interval);
        self::assertNull($notifications->periodId);
        self::assertNull($notifications->firstDelay);
        self::assertNull($notifications->recoveryDelay);
        self::assertFalse($notifications->contactAdditiveInheritance);
        self::assertFalse($notifications->contactGroupAdditiveInheritance);
        self::assertFalse($notifications->useOnlyContactsFromHost);
    }

    public function testItHoldsItsFields(): void
    {
        $notifications = new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(3)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(7)], ContactGroupId::class),
            options: [ServiceNotificationOptionEnum::Critical],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 5,
            recoveryDelay: 10,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: true,
            useOnlyContactsFromHost: true,
        );

        self::assertSame(TriStateEnum::True, $notifications->enabled);
        self::assertSame(30, $notifications->interval);
        self::assertSame(2, $notifications->periodId?->value);
        self::assertSame(5, $notifications->firstDelay);
        self::assertSame(10, $notifications->recoveryDelay);
        self::assertTrue($notifications->contactAdditiveInheritance);
        self::assertTrue($notifications->contactGroupAdditiveInheritance);
        self::assertTrue($notifications->useOnlyContactsFromHost);
    }

    public function testItCollapsesDuplicatedOptions(): void
    {
        $notifications = $this->createNotifications(options: [
            ServiceNotificationOptionEnum::Warning,
            ServiceNotificationOptionEnum::Recovery,
            ServiceNotificationOptionEnum::Warning,
        ]);

        self::assertSame([ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Recovery], $notifications->options);
    }

    public function testItCollapsesDuplicatedContactAndContactGroupIds(): void
    {
        $notifications = new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection(
                [new NotificationContactId(3), new NotificationContactId(5), new NotificationContactId(3)],
                NotificationContactId::class,
            ),
            contactGroupIds: new Collection(
                [new ContactGroupId(7), new ContactGroupId(7)],
                ContactGroupId::class,
            ),
        );

        self::assertEquals(
            [new NotificationContactId(3), new NotificationContactId(5)],
            $notifications->contactIds->toArray(),
        );
        self::assertEquals([new ContactGroupId(7)], $notifications->contactGroupIds->toArray());
    }

    /**
     * Legacy stores the options in the order w,u,c,r,f,s whatever was ticked: the column must not get an order
     * legacy cannot write.
     */
    public function testItNormalisesTheOptionOrder(): void
    {
        $notifications = $this->createNotifications(options: [
            ServiceNotificationOptionEnum::DowntimeScheduled,
            ServiceNotificationOptionEnum::Flapping,
            ServiceNotificationOptionEnum::Recovery,
            ServiceNotificationOptionEnum::Critical,
            ServiceNotificationOptionEnum::Unknown,
            ServiceNotificationOptionEnum::Warning,
        ]);

        self::assertSame(
            [
                ServiceNotificationOptionEnum::Warning,
                ServiceNotificationOptionEnum::Unknown,
                ServiceNotificationOptionEnum::Critical,
                ServiceNotificationOptionEnum::Recovery,
                ServiceNotificationOptionEnum::Flapping,
                ServiceNotificationOptionEnum::DowntimeScheduled,
            ],
            $notifications->options,
        );
    }

    public function testItAcceptsNoneAsTheOnlyOption(): void
    {
        $notifications = $this->createNotifications(options: [ServiceNotificationOptionEnum::None]);

        self::assertSame([ServiceNotificationOptionEnum::None], $notifications->options);
    }

    public function testItRejectsNoneCombinedWithAnotherOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createNotifications(options: [ServiceNotificationOptionEnum::None, ServiceNotificationOptionEnum::Critical]);
    }

    public function testItAcceptsZeroForEveryDelayAndInterval(): void
    {
        $notifications = new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            interval: 0,
            firstDelay: 0,
            recoveryDelay: 0,
        );

        self::assertSame(0, $notifications->interval);
        self::assertSame(0, $notifications->firstDelay);
        self::assertSame(0, $notifications->recoveryDelay);
    }

    /**
     * @return iterable<string, array{?int, ?int, ?int}>
     */
    public static function negativeValueProvider(): iterable
    {
        yield 'interval' => [-1, null, null];

        yield 'first delay' => [null, -1, null];

        yield 'recovery delay' => [null, null, -1];
    }

    #[DataProvider('negativeValueProvider')]
    public function testItRejectsANegativeDelayOrInterval(?int $interval, ?int $firstDelay, ?int $recoveryDelay): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            interval: $interval,
            firstDelay: $firstDelay,
            recoveryDelay: $recoveryDelay,
        );
    }

    public function testWithContactsReplacesTheContactsAndTheContactGroupsAndKeepsTheRest(): void
    {
        $notifications = new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(3), new NotificationContactId(4)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(7)], ContactGroupId::class),
            options: [ServiceNotificationOptionEnum::Critical],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 5,
            recoveryDelay: 10,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: true,
            useOnlyContactsFromHost: true,
        );

        $scoped = $notifications->withContacts(
            new Collection([new NotificationContactId(4)], NotificationContactId::class),
            new Collection([], ContactGroupId::class),
        );

        self::assertEquals([new NotificationContactId(4)], $scoped->contactIds->toArray());
        self::assertSame([], $scoped->contactGroupIds->toArray());
        self::assertSame(TriStateEnum::True, $scoped->enabled);
        self::assertSame([ServiceNotificationOptionEnum::Critical], $scoped->options);
        self::assertSame(30, $scoped->interval);
        self::assertSame(2, $scoped->periodId?->value);
        self::assertSame(5, $scoped->firstDelay);
        self::assertSame(10, $scoped->recoveryDelay);
        self::assertTrue($scoped->contactAdditiveInheritance);
        self::assertTrue($scoped->contactGroupAdditiveInheritance);
        self::assertTrue($scoped->useOnlyContactsFromHost);
    }

    public function testWithoutAdditiveInheritanceKeepsEveryOtherField(): void
    {
        $notifications = $this->createFilledNotifications(useOnlyContactsFromHost: false);

        self::assertEquals(
            $this->createFilledNotifications(useOnlyContactsFromHost: false, additiveInheritance: false),
            $notifications->withoutAdditiveInheritance(),
        );
    }

    /**
     * A restricted actor clears only the contacts and contact groups they can see: the hidden ones stay linked.
     * Both additive-inheritance flags are switched off whatever they were, and every other field is kept.
     */
    public function testWithHostContactsOnlyClearsTheVisibleContactsAndKeepsTheHiddenOnes(): void
    {
        $notifications = $this->createFilledNotifications(useOnlyContactsFromHost: false);

        $cleared = $notifications->withHostContactsOnly(
            AccessScope::restrictedTo(NotificationContactId::class, [new NotificationContactId(3)]),
            AccessScope::restrictedTo(ContactGroupId::class, [new ContactGroupId(7), new ContactGroupId(8)]),
        );

        self::assertSame([4], array_map(static fn (NotificationContactId $id): int => $id->value, $cleared->contactIds->toArray()));
        self::assertSame([], $cleared->contactGroupIds->toArray());
        self::assertEquals(
            $this->createFilledNotifications(useOnlyContactsFromHost: true, additiveInheritance: false)
                ->withContacts($cleared->contactIds, $cleared->contactGroupIds),
            $cleared,
        );
    }

    /**
     * On create every submitted contact is visible to its author: they are all cleared.
     */
    public function testWithHostContactsOnlyClearsEveryContactForAnUnrestrictedScope(): void
    {
        $cleared = $this->createFilledNotifications(useOnlyContactsFromHost: true)->withHostContactsOnly(
            AccessScope::unrestricted(NotificationContactId::class),
            AccessScope::unrestricted(ContactGroupId::class),
        );

        self::assertSame([], $cleared->contactIds->toArray());
        self::assertSame([], $cleared->contactGroupIds->toArray());
        self::assertFalse($cleared->contactAdditiveInheritance);
        self::assertFalse($cleared->contactGroupAdditiveInheritance);
        self::assertTrue($cleared->useOnlyContactsFromHost);
    }

    private function createFilledNotifications(bool $useOnlyContactsFromHost, bool $additiveInheritance = true): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(3), new NotificationContactId(4)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(7), new ContactGroupId(8)], ContactGroupId::class),
            options: [ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::Recovery],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 5,
            recoveryDelay: 10,
            contactAdditiveInheritance: $additiveInheritance,
            contactGroupAdditiveInheritance: $additiveInheritance,
            useOnlyContactsFromHost: $useOnlyContactsFromHost,
        );
    }

    /**
     * @param list<ServiceNotificationOptionEnum> $options
     */
    private function createNotifications(array $options = []): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: $options,
        );
    }
}
