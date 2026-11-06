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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceNotificationsTransformer;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;

/**
 * A name lookup only misses a reference deleted since it was linked: the reference is then dropped from the output
 * rather than failing the whole response.
 */
final class ServiceNotificationsTransformerTest extends TestCase
{
    private FakeNotificationContactRepository $contacts;

    private FakeContactGroupRepository $contactGroups;

    private FakeTimePeriodRepository $timePeriods;

    protected function setUp(): void
    {
        $this->contacts = new FakeNotificationContactRepository();
        $this->contactGroups = new FakeContactGroupRepository();
        $this->timePeriods = new FakeTimePeriodRepository();
    }

    public function testNoBlockGivesNoOutput(): void
    {
        self::assertNull($this->transformer()->transform(null));
    }

    public function testItResolvesEveryReferenceAndKeepsTheSettings(): void
    {
        $this->contacts->names[1] = new NotificationContactName('alice');
        $this->contactGroups->names[2] = new ContactGroupName('admins');
        $this->timePeriods->names[3] = '24x7';

        $output = $this->transformer()->transform($this->notifications(
            options: [ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Critical],
        ));

        self::assertNotNull($output);
        self::assertSame('use_default', $output->enabled);
        self::assertSame([[1, 'alice']], array_map(static fn (object $contact): array => [$contact->id, $contact->name], $output->contacts));
        self::assertSame([[2, 'admins']], array_map(static fn (object $group): array => [$group->id, $group->name], $output->contactGroups));
        self::assertSame([3, '24x7'], [$output->timeperiod?->id, $output->timeperiod?->name]);
        self::assertSame(['warning', 'critical'], $output->options);
        self::assertSame([30, 5, 10], [$output->interval, $output->firstDelay, $output->recoveryDelay]);
        self::assertSame([true, false, true], [$output->useOnlyContactsFromHost, $output->contactAdditiveInheritance, $output->contactGroupAdditiveInheritance]);
    }

    public function testAReferenceWhoseNameIsNotFoundIsDropped(): void
    {
        $output = $this->transformer()->transform($this->notifications());

        self::assertNotNull($output);
        self::assertSame([], $output->contacts);
        self::assertSame([], $output->contactGroups);
        self::assertNull($output->timeperiod);
    }

    private function transformer(): ServiceNotificationsTransformer
    {
        return new ServiceNotificationsTransformer($this->contacts, $this->contactGroups, $this->timePeriods);
    }

    /**
     * @param list<ServiceNotificationOptionEnum> $options
     */
    private function notifications(array $options = []): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([new NotificationContactId(1)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(2)], ContactGroupId::class),
            options: $options,
            interval: 30,
            periodId: new TimePeriodId(3),
            firstDelay: 5,
            recoveryDelay: 10,
            contactAdditiveInheritance: false,
            contactGroupAdditiveInheritance: true,
            useOnlyContactsFromHost: true,
        );
    }
}
