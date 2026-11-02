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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Infrastructure\Dbal\ServiceNotificationColumnsTransformer;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceNotificationColumnsTransformerTest extends TestCase
{
    public function testItWritesEachSettingInItsStorageFormat(): void
    {
        $notifications = new ServiceNotifications(
            enabled: TriStateEnum::False,
            contactIds: new Collection([new NotificationContactId(3)], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: [ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::Warning],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 5,
            recoveryDelay: 10,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: false,
            useOnlyContactsFromHost: true,
        );

        self::assertSame(
            [
                'notificationsEnabled' => '0',
                'notificationOptions' => 'w,c',
                'notificationInterval' => 30,
                'notificationPeriodId' => 2,
                'firstNotificationDelay' => 5,
                'recoveryNotificationDelay' => 10,
                'contactAdditiveInheritance' => true,
                'contactGroupAdditiveInheritance' => false,
                'useOnlyContactsFromHost' => '1',
            ],
            (new ServiceNotificationColumnsTransformer())->transform($notifications),
        );
    }

    /**
     * Without notifications block, what legacy writes for a form left empty.
     */
    public function testItWritesTheLegacyDefaultsWithoutANotificationsBlock(): void
    {
        self::assertSame(
            [
                'notificationsEnabled' => '2',
                'notificationOptions' => null,
                'notificationInterval' => null,
                'notificationPeriodId' => null,
                'firstNotificationDelay' => null,
                'recoveryNotificationDelay' => null,
                'contactAdditiveInheritance' => false,
                'contactGroupAdditiveInheritance' => false,
                'useOnlyContactsFromHost' => '0',
            ],
            (new ServiceNotificationColumnsTransformer())->transform(null),
        );
    }

    /**
     * @param list<ServiceNotificationOptionEnum> $options
     */
    #[DataProvider('optionsProvider')]
    public function testOptions(array $options, ?string $expected): void
    {
        self::assertSame($expected, ServiceNotificationColumnsTransformer::options($options));
    }

    /**
     * Each option to its engine letter; an empty list is NULL, distinct from the "none" option.
     *
     * @return iterable<string, array{list<ServiceNotificationOptionEnum>, ?string}>
     */
    public static function optionsProvider(): iterable
    {
        yield 'no option' => [[], null];

        yield 'warning' => [[ServiceNotificationOptionEnum::Warning], 'w'];

        yield 'unknown' => [[ServiceNotificationOptionEnum::Unknown], 'u'];

        yield 'critical' => [[ServiceNotificationOptionEnum::Critical], 'c'];

        yield 'recovery' => [[ServiceNotificationOptionEnum::Recovery], 'r'];

        yield 'flapping' => [[ServiceNotificationOptionEnum::Flapping], 'f'];

        yield 'downtime scheduled' => [[ServiceNotificationOptionEnum::DowntimeScheduled], 's'];

        yield 'none alone' => [[ServiceNotificationOptionEnum::None], 'n'];

        yield 'every option' => [
            [
                ServiceNotificationOptionEnum::Warning,
                ServiceNotificationOptionEnum::Unknown,
                ServiceNotificationOptionEnum::Critical,
                ServiceNotificationOptionEnum::Recovery,
                ServiceNotificationOptionEnum::Flapping,
                ServiceNotificationOptionEnum::DowntimeScheduled,
            ],
            'w,u,c,r,f,s',
        ];
    }
}
