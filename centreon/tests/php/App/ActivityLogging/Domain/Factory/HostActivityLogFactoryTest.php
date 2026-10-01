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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\HostActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

/**
 * The factory spells out the legacy storage formats itself (it cannot reuse the Dbal transformer
 * from Domain), so this is the only place a drift between the two would be caught.
 */
final class HostActivityLogFactoryTest extends TestCase
{
    public function testItLogsTheHostWithoutNotificationKeysWhenItHasNoNotifications(): void
    {
        $firedAt = new \DateTimeImmutable();

        $activityLog = (new HostActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: $this->host(notifications: null),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('server-01', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Host, $activityLog->target->type);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'host_name' => 'server-01',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
        ], $activityLog->details);
    }

    public function testItLogsEveryNotificationFieldInTheLegacyStorageFormat(): void
    {
        $notifications = new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(3), new NotificationContactId(5)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(7)], ContactGroupId::class),
            options: [
                NotificationOptionEnum::Down,
                NotificationOptionEnum::Unreachable,
                NotificationOptionEnum::Recovery,
                NotificationOptionEnum::Flapping,
                NotificationOptionEnum::DowntimeScheduled,
            ],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 10,
            recoveryDelay: 20,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: false,
        );

        $details = $this->createActivityLogDetails($notifications);

        self::assertSame([
            'host_name' => 'server-01',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_notifications_enabled' => '1',
            'host_notification_options' => 'd,u,r,f,s',
            'host_notification_interval' => '30',
            'timeperiod_tp_id2' => '2',
            'host_first_notification_delay' => '10',
            'host_recovery_notification_delay' => '20',
            'contact_additive_inheritance' => '1',
            'cg_additive_inheritance' => '0',
            'host_cs' => '3,5',
            'host_cgs' => '7',
        ], $details);
    }

    /**
     * The block a client left out: Default tri-state, and empty strings where legacy logs an
     * unset field.
     */
    public function testItLogsAnEmptyNotificationsBlock(): void
    {
        $details = $this->createActivityLogDetails(new Notifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
        ));

        self::assertSame('2', $details['host_notifications_enabled']);
        self::assertSame('', $details['host_notification_options']);
        self::assertSame('', $details['host_notification_interval']);
        self::assertSame('', $details['timeperiod_tp_id2']);
        self::assertSame('', $details['host_cs']);
        self::assertSame('', $details['host_cgs']);
    }

    public function testItLogsTheNoneOptionAsN(): void
    {
        $details = $this->createActivityLogDetails(new Notifications(
            enabled: TriStateEnum::False,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: [NotificationOptionEnum::None],
        ));

        self::assertSame('0', $details['host_notifications_enabled']);
        self::assertSame('n', $details['host_notification_options']);
    }

    /**
     * @return array<string, string>
     */
    private function createActivityLogDetails(Notifications $notifications): array
    {
        return (new HostActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: $this->host($notifications),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: new \DateTimeImmutable(),
        )->details;
    }

    private function host(?Notifications $notifications): Host
    {
        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            notifications: $notifications,
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setValue($host, new HostId(42));

        return $host;
    }
}
