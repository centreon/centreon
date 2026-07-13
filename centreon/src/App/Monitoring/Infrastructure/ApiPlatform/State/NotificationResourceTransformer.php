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

namespace App\Monitoring\Infrastructure\ApiPlatform\State;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\HostEventEnum;
use App\Monitoring\Domain\Aggregate\Notification\Message\Message;
use App\Monitoring\Domain\Aggregate\Notification\Message\MessageChannelEnum;
use App\Monitoring\Domain\Aggregate\Notification\ServiceEventEnum;
use App\Monitoring\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\Monitoring\Domain\Aggregate\User\User;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroup;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\HostGroupDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\MessageDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\NotificationResource;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\ResourceDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\ServiceGroupDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\TimePeriodDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\UserDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\UserGroupDto;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<NotificationResourceInput, NotificationResource>
 */
final readonly class NotificationResourceTransformer implements TransformerInterface
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
     * @param NotificationResourceInput $from
     */
    public function transform(mixed $from): NotificationResource
    {
        $notification = $from->notification;

        return new NotificationResource(
            $notification->id()->value, $notification->name->value, new TimePeriodDto(
                $notification->timePeriod->value, 'test',
            ), $notification->isActivated, array_map(
                fn(Message $message): MessageDto => new MessageDto(
                    $this->translateChannelToString($message->channel), $message->subject->value, $message->message, $message->formattedMessage,
                ),
                $from->notification->messages,
            ), array_map(
                static fn(User $user): UserDto => new UserDto(
                    $user->id()->value, $user->name->value,
                ),
                $from->users,
            ), array_map(
                static fn(UserGroup $userGroup): UserGroupDto => new UserGroupDto(
                    $userGroup->id()->value, $userGroup->name->value,
                ),
                $from->userGroups,
            ),
            $this->createResourcesDto($from),
        );
    }

    /**
     * @return list<ResourceDto>
     */
    private function createResourcesDto(NotificationResourceInput $notification): array
    {
        $resources = [];
        $resource = new ResourceDto(type: 'hostgroup', events: 0, ids: []);
        if ($notification->hostGroups !== []) {
            $resource->events = $this->getBitsFromHostNotificationEvents($notification->notification->hostGroupEvents);
            $resource->ids = array_map(
                fn(HostGroup $hostGroup): HostGroupDto => new HostGroupDto($hostGroup->id()->value, $hostGroup->name->value),
                $notification->hostGroups
            );
            if ($notification->notification->serviceEvents !== []) {
                $resource->extra = [
                    'event_services' => $this->getBitsFromServiceNotificationEvents($notification->notification->serviceEvents),
                ];
            }
        }
        $resources[] = $resource;

        $resource = new ResourceDto(type: 'servicegroup', events: 0, ids: []);
        if ($notification->serviceGroups !== []) {
            $resource->events = $this->getBitsFromServiceNotificationEvents($notification->notification->serviceGroupEvents);
            $resource->ids = array_map(
                fn(ServiceGroup $serviceGroup): ServiceGroupDto => new ServiceGroupDto($serviceGroup->id()->value, $serviceGroup->name->value),
                $notification->serviceGroups
            );
        }
        $resources[] = $resource;
        return $resources;
    }

    private function translateChannelToString(MessageChannelEnum $channel): string
    {
        return match ($channel) {
            MessageChannelEnum::Sms => 'Sms',
            MessageChannelEnum::Slack => 'Slack',
            default => 'Email',
        };
    }

    /**
     * @param list<HostEventEnum> $events
     *
     * @return int
     */
    private function getBitsFromHostNotificationEvents(array $events): int
    {
        $bitMask = 0b0;
        foreach ($events as $event) {
            $bitMask |= match ($event) {
                HostEventEnum::Up => self::HOST_UP_AS_BIT,
                HostEventEnum::Down => self::HOST_DOWN_AS_BIT,
                HostEventEnum::Unreachable => self::HOST_UNREACHABLE_AS_BIT,
                default => throw new \LogicException(
                    sprintf("Event '%s' not taking into account", $event->name)
                ),
            };
        }

        return $bitMask;
    }

    /**
     * @param list<ServiceEventEnum> $events
     *
     * @return int
     */
    private function getBitsFromServiceNotificationEvents(array $events): int
    {
        $bitMask = 0b0;
        foreach ($events as $event) {
            $bitMask |= match ($event) {
                ServiceEventEnum::Ok => self::SERVICE_OK_AS_BIT,
                ServiceEventEnum::Warning => self::SERVICE_WARNING_AS_BIT,
                ServiceEventEnum::Critical => self::SERVICE_CRITICAL_AS_BIT,
                ServiceEventEnum::Unknown => self::SERVICE_UNKNOWN_AS_BIT,
                default => throw new \LogicException(
                    sprintf("Event '%s' not taking into account", $event->name)
                ),
            };
        }

        return $bitMask;
    }
}
