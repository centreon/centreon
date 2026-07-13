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

namespace App\Monitoring\Infrastructure\Dbal\Notification;

use App\Monitoring\Domain\Aggregate\Notification\HostEventEnum;
use App\Monitoring\Domain\Aggregate\Notification\Message\Message;
use App\Monitoring\Domain\Aggregate\Notification\Message\MessageChannelEnum;
use App\Monitoring\Domain\Aggregate\Notification\Message\MessageId;
use App\Monitoring\Domain\Aggregate\Notification\Message\MessageSubject;
use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\Notification\NotificationName;
use App\Monitoring\Domain\Aggregate\Notification\ServiceEventEnum;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriod;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Infrastructure\Dbal\TimePeriod\DbalTimePeriodTransformer;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalNotificationRepository
 * @implements TransformerInterface<RowTypeAlias, Notification>
 */
final readonly class DbalNotificationTransformer implements TransformerInterface
{
    private const HOST_UP_AS_BIT = 0b001;
    private const HOST_DOWN_AS_BIT = 0b010;
    private const HOST_UNREACHABLE_AS_BIT = 0b100;
    private const HOST_MAX_BITFLAGS = 0b111;
    private const SERVICE_OK_AS_BIT = 0b0001;
    private const SERVICE_WARNING_AS_BIT = 0b0010;
    private const SERVICE_CRITICAL_AS_BIT = 0b0100;
    private const SERVICE_UNKNOWN_AS_BIT = 0b1000;
    private const SERVICE_MAX_BITFLAGS = 0b1111;

    /**
     * @param TransformerInterface<RowTypeAlias, TimePeriod> $timePeriodTransformer
     */
    public function __construct(
        #[Autowire(service: DbalTimePeriodTransformer::class)]
        private TransformerInterface $timePeriodTransformer,
    ) {
    }

    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): Notification
    {
        $notification = $from[0];
        $messages = array_map(fn (array $data): Message => new Message(
            new MessageId($data['message_id']),
            $this->getChannelFromData($data['message_channel']),
            new MessageSubject($data['message_subject']),
            $data['message_message'],
            $data['message_formatted_message'],
        ), $from);
        return new Notification(
            id: new NotificationId($notification['notification_id']),
            name: new NotificationName($notification['notification_name']),
            isActivated: (bool) $notification['notification_is_activated'],
            timePeriod: new TimePeriodId($notification['notification_timeperiod_id']),
            userIds: [],
            messages: $messages,
            userGroupIds: [],
            hostGroupEvents: $this->getHostEnumFromBits($notification['notification_hostgroup_events']),
            serviceGroupEvents: $this->getServiceEnumFromBits($notification['notification_servicegroup_events']),
            serviceEvents: $this->getServiceEnumFromBits($notification['notification_service_events']),
            hostGroups: [],
            serviceGroups: [],
        );
    }

    private function getChannelFromData(string $channel): MessageChannelEnum
    {
        return match ($channel) {
            'Sms' => MessageChannelEnum::Sms,
            'Slack' => MessageChannelEnum::Slack,
            default => MessageChannelEnum::Email,
        };
    }

    /**
     * @param int $bitFlags
     *
     * @return list<HostEventEnum>
     */
    public function getHostEnumFromBits(int $bitFlags): array
    {
        if ($bitFlags > self::HOST_MAX_BITFLAGS || $bitFlags < 0) {
            throw new \ValueError("\"{$bitFlags}\" is not a valid bit flag for enum NotificationHostEvent");
        }

        $enums = [];
        foreach (HostEventEnum::cases() as $enum) {
            if ($bitFlags & self::getBitFromHostEnum($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * @param int $bitFlags
     *
     * @return list<ServiceEventEnum>
     */
    public function getServiceEnumFromBits(int $bitFlags): array
    {
        if ($bitFlags > self::SERVICE_MAX_BITFLAGS || $bitFlags < 0) {
            throw new \ValueError("\"{$bitFlags}\" is not a valid bit flag for enum NotificationServiceEvent");
        }

        $enums = [];
        foreach (ServiceEventEnum::cases() as $enum) {
            if ($bitFlags & self::getBitFromServiceEnum($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    private function getBitFromHostEnum(HostEventEnum $event): int
    {
        return match ($event) {
            HostEventEnum::Up => self::HOST_UP_AS_BIT,
            HostEventEnum::Down => self::HOST_DOWN_AS_BIT,
            HostEventEnum::Unreachable => self::HOST_UNREACHABLE_AS_BIT,
            default => throw new \LogicException(
                sprintf("Event '%s' not taking into account", $event->name)
            ),
        };
    }

    private function getBitFromServiceEnum(ServiceEventEnum $event): int
    {
        return match ($event) {
            ServiceEventEnum::Ok => self::SERVICE_OK_AS_BIT,
            ServiceEventEnum::Warning => self::SERVICE_WARNING_AS_BIT,
            ServiceEventEnum::Critical => self::SERVICE_CRITICAL_AS_BIT,
            ServiceEventEnum::Unknown => self::SERVICE_UNKNOWN_AS_BIT,
            default => throw new \LogicException(
                sprintf("Event '%s' not taking into account", $event->name)
            ),
        };
    }
}
