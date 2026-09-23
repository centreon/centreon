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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationsTest extends TestCase
{
    public function testItAcceptsEveryOptionalFieldLeftEmpty(): void
    {
        $notifications = $this->createNotifications();

        self::assertSame(TriStateEnum::UseDefault, $notifications->enabled);
        self::assertSame([], $notifications->options);
        self::assertNull($notifications->interval);
        self::assertNull($notifications->periodId);
        self::assertNull($notifications->firstDelay);
        self::assertNull($notifications->recoveryDelay);
        self::assertFalse($notifications->contactAdditiveInheritance);
        self::assertFalse($notifications->contactGroupAdditiveInheritance);
    }

    public function testItAcceptsAllFieldsFilled(): void
    {
        $periodId = new TimePeriodId(5);

        $notifications = new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(1)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(3)], ContactGroupId::class),
            options: [NotificationOptionEnum::Down, NotificationOptionEnum::Recovery],
            interval: 30,
            periodId: $periodId,
            firstDelay: 10,
            recoveryDelay: 20,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: true,
        );

        self::assertSame(TriStateEnum::True, $notifications->enabled);
        self::assertCount(1, $notifications->contactIds);
        self::assertCount(1, $notifications->contactGroupIds);
        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $notifications->options);
        self::assertSame(30, $notifications->interval);
        self::assertSame($periodId, $notifications->periodId);
        self::assertSame(10, $notifications->firstDelay);
        self::assertSame(20, $notifications->recoveryDelay);
        self::assertTrue($notifications->contactAdditiveInheritance);
        self::assertTrue($notifications->contactGroupAdditiveInheritance);
    }

    public function testItCollapsesDuplicatedOptions(): void
    {
        $notifications = $this->createNotifications(options: [
            NotificationOptionEnum::Down,
            NotificationOptionEnum::Recovery,
            NotificationOptionEnum::Down,
        ]);

        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $notifications->options);
    }

    public function testItAcceptsNoneAsTheOnlyOption(): void
    {
        $notifications = $this->createNotifications(options: [NotificationOptionEnum::None]);

        self::assertSame([NotificationOptionEnum::None], $notifications->options);
    }

    public function testItRejectsNoneCombinedWithAnotherOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot combine "none"');

        $this->createNotifications(options: [NotificationOptionEnum::None, NotificationOptionEnum::Down]);
    }

    /**
     * Legacy allows 0 for all three (`Assertion::min($value ?? 0, 0)`): it is a meaningful value,
     * not an empty one — a 0 interval means "notify once", a 0 delay means "notify immediately".
     */
    public function testItAcceptsZeroForEveryDelayAndInterval(): void
    {
        $notifications = $this->createNotifications(interval: 0, firstDelay: 0, recoveryDelay: 0);

        self::assertSame(0, $notifications->interval);
        self::assertSame(0, $notifications->firstDelay);
        self::assertSame(0, $notifications->recoveryDelay);
    }

    /**
     * @return array<string, array{interval: int|null, firstDelay: int|null, recoveryDelay: int|null}>
     */
    public static function negativeValueProvider(): array
    {
        return [
            'interval' => ['interval' => -1, 'firstDelay' => null, 'recoveryDelay' => null],
            'first delay' => ['interval' => null, 'firstDelay' => -1, 'recoveryDelay' => null],
            'recovery delay' => ['interval' => null, 'firstDelay' => null, 'recoveryDelay' => -1],
        ];
    }

    #[DataProvider('negativeValueProvider')]
    public function testItRejectsANegativeDelayOrInterval(?int $interval, ?int $firstDelay, ?int $recoveryDelay): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createNotifications(interval: $interval, firstDelay: $firstDelay, recoveryDelay: $recoveryDelay);
    }

    /**
     * @param list<NotificationOptionEnum> $options
     */
    private function createNotifications(
        array $options = [],
        ?int $interval = null,
        ?int $firstDelay = null,
        ?int $recoveryDelay = null,
    ): Notifications {
        return new Notifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: $options,
            interval: $interval,
            firstDelay: $firstDelay,
            recoveryDelay: $recoveryDelay,
        );
    }
}
