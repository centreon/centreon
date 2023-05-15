<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Notification\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;

class NotificationResource
{
    public const HOSTGROUP_RESOURCE_TYPE = 'hostgroup';
    public const SERVICEGROUP_RESOURCE_TYPE = 'servicegroup';

    /**
     * @param string $type
     * @param class-string<NotificationHostEvent|NotificationServiceEvent> $eventEnum
     * @param NotificationGenericObject[] $resources
     * @param array<NotificationHostEvent|NotificationServiceEvent> $events
     * @param NotificationServiceEvent[] $serviceEvents
     *
     * @throws AssertionException
     */
    public function __construct(
        private readonly string $type,
        private readonly string $eventEnum,
        private array $resources,
        private array $events,
        private array $serviceEvents = [],
    ) {
        $this->setEvents($events);
        $this->setServiceEvents($serviceEvents);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return NotificationGenericObject[]
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * @return int
     */
    public function getResourcesCount(): int
    {
        return count($this->resources);
    }

    /**
     * @return array<NotificationHostEvent|NotificationServiceEvent>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param array<NotificationHostEvent|NotificationServiceEvent> $events
     */
    public function setEvents(array $events): void
    {
        $this->events = [];
        foreach ($events as $event) {
            $this->addEvent($event);
        }
    }

    /**
     * @param NotificationHostEvent|NotificationServiceEvent $event
     *
     * @throws \ValueError
     */
    public function addEvent(NotificationHostEvent|NotificationServiceEvent $event): void
    {
        if ($event instanceof $this->eventEnum) {
            $this->events[] = $event;
        } else {
            throw new \ValueError("\"{$event->name}\" is not a valid backing value for enum {$this->eventEnum}");
        }
    }

    /**
     * Should only be used for Notification of type hostgroup.
     *
     * @return NotificationServiceEvent[]
     */
    public function getServiceEvents(): array
    {
        return $this->serviceEvents;
    }

    /**
     * Should only be used for Notification of type hostgroup.
     *
     * @param NotificationServiceEvent[] $serviceEvents
     */
    public function setServiceEvents(array $serviceEvents): void
    {
        $this->serviceEvents = [];
        foreach ($serviceEvents as $serviceEvent) {
            $this->addServiceEvent($serviceEvent);
        }
    }

    /**
     * Should only be used for Notification of type hostgroup.
     *
     * @param NotificationServiceEvent $serviceEvent
     *
     * @return void
     */
    public function addServiceEvent(NotificationServiceEvent $serviceEvent): void
    {
        $this->serviceEvents[] = $serviceEvent;
    }
}
