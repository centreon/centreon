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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostNotificationsTransformer;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

/**
 * A name lookup only misses a reference deleted between validation and the response: the
 * reference is then dropped from the output rather than failing the whole response.
 */
final class HostNotificationsTransformerTest extends TestCase
{
    public function testItResolvesEveryReferenceName(): void
    {
        $output = $this->transform(
            contactNames: [1 => new NotificationContactName('alice')],
            contactGroupNames: [2 => new ContactGroupName('admins')],
            timePeriodNames: [3 => new TimePeriodName('24x7')],
        );

        self::assertCount(1, $output->contacts);
        self::assertSame(1, $output->contacts[0]->id);
        self::assertSame('alice', $output->contacts[0]->name);
        self::assertCount(1, $output->contactGroups);
        self::assertSame(2, $output->contactGroups[0]->id);
        self::assertSame('admins', $output->contactGroups[0]->name);
        self::assertNotNull($output->timeperiod);
        self::assertSame(3, $output->timeperiod->id);
        self::assertSame('24x7', $output->timeperiod->name);
    }

    public function testItDropsAContactWhoseNameIsNotFound(): void
    {
        $output = $this->transform(
            contactNames: [],
            contactGroupNames: [2 => new ContactGroupName('admins')],
            timePeriodNames: [3 => new TimePeriodName('24x7')],
        );

        self::assertSame([], $output->contacts);
        self::assertCount(1, $output->contactGroups);
        self::assertNotNull($output->timeperiod);
    }

    public function testItDropsAContactGroupWhoseNameIsNotFound(): void
    {
        $output = $this->transform(
            contactNames: [1 => new NotificationContactName('alice')],
            contactGroupNames: [],
            timePeriodNames: [3 => new TimePeriodName('24x7')],
        );

        self::assertCount(1, $output->contacts);
        self::assertSame([], $output->contactGroups);
        self::assertNotNull($output->timeperiod);
    }

    public function testItDropsATimePeriodWhoseNameIsNotFound(): void
    {
        $output = $this->transform(
            contactNames: [1 => new NotificationContactName('alice')],
            contactGroupNames: [2 => new ContactGroupName('admins')],
            timePeriodNames: [],
        );

        self::assertCount(1, $output->contacts);
        self::assertCount(1, $output->contactGroups);
        self::assertNull($output->timeperiod);
    }

    /**
     * Transforms a block referencing contact 1, contact group 2 and time period 3.
     *
     * @param array<int, NotificationContactName> $contactNames
     * @param array<int, ContactGroupName> $contactGroupNames
     * @param array<int, TimePeriodName> $timePeriodNames
     */
    private function transform(array $contactNames, array $contactGroupNames, array $timePeriodNames): HostNotificationsOutput
    {
        $contactRepository = $this->createStub(NotificationContactRepository::class);
        $contactRepository->method('findNamesByIds')
            ->willReturn(new Collection($contactNames, NotificationContactName::class));
        $contactGroupRepository = $this->createStub(ContactGroupRepository::class);
        $contactGroupRepository->method('findNamesByIds')
            ->willReturn(new Collection($contactGroupNames, ContactGroupName::class));
        $timePeriodRepository = $this->createStub(TimePeriodRepository::class);
        $timePeriodRepository->method('findNamesByIds')
            ->willReturn(new Collection($timePeriodNames, TimePeriodName::class));

        $transformer = new HostNotificationsTransformer($contactRepository, $contactGroupRepository, $timePeriodRepository);

        $output = $transformer->transform(new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(1)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(2)], ContactGroupId::class),
            periodId: new TimePeriodId(3),
        ));
        self::assertInstanceOf(HostNotificationsOutput::class, $output);

        return $output;
    }
}
