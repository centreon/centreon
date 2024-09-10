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

class NotificationResource
{
    public const TYPE_HOST_GROUP = 'hostgroup';
    public const TYPE_SERVICE_GROUP = 'servicegroup';

    /**
     * @param self::TYPE_* $type
     * @param class-string<HostEvent|ServiceEvent> $eventEnum
     * @param ConfigurationResource[] $resources
     * @param array<HostEvent|ServiceEvent> $events
     * @param ServiceEvent[] $serviceEvents
     *
     * @throws \ValueError
     */
    public function __construct(
        private readonly string $type,
        private readonly string $eventEnum,
        private readonly array $resources,
        private array $events,
        private array $serviceEvents = [],
    ) {
        $this->setEvents($events);
        $this->setServiceEvents($serviceEvents);
    }

    /**
     * @return self::TYPE_*
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return ConfigurationResource[]
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
     * @return array<HostEvent>|array<ServiceEvent>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param HostEvent|ServiceEvent $event
     *
     * @throws \ValueError
     */
    public function addEvent(HostEvent|ServiceEvent $event): void
    {
        if ($event instanceof $this->eventEnum) {
            $this->events[] = $event;
        } else {
            throw new \ValueError("\"{$event->name}\" is not a valid backing value for enum {$this->eventEnum}");
        }
    }

    /**
     * Should only be used for Notification of type host group.
     *
     * @return ServiceEvent[]
     */
    public function getServiceEvents(): array
    {
        return $this->serviceEvents;
    }

    /**
     * Should only be used for Notification of type host group.
     *
     * @param ServiceEvent $serviceEvent
     */
    public function addServiceEvent(ServiceEvent $serviceEvent): void
    {
        $this->serviceEvents[] = $serviceEvent;
    }

    /**
     * @param array<HostEvent>|array<ServiceEvent> $events
     *
     * @throws \ValueError
     */
    private function setEvents(array $events): void
    {
        $this->events = [];
        foreach ($events as $event) {
            $this->addEvent($event);
        }
    }

    /**
     * Should only be used for Notification of type hostgroup.
     *
     * @param ServiceEvent[] $serviceEvents
     */
    private function setServiceEvents(array $serviceEvents): void
    {
        $this->serviceEvents = [];
        foreach ($serviceEvents as $serviceEvent) {
            $this->addServiceEvent($serviceEvent);
        }
    }
}
