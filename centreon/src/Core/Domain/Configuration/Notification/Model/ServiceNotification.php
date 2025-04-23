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

namespace Core\Domain\Configuration\Notification\Model;

use Core\Domain\Configuration\Notification\Exception\NotificationException;
use Core\Domain\Configuration\TimePeriod\Model\TimePeriod;

class ServiceNotification implements NotificationInterface
{
    public const EVENT_SERVICE_RECOVERY = 'RECOVERY';
    public const EVENT_SERVICE_SCHEDULED_DOWNTIME = 'SCHEDULED_DOWNTIME';
    public const EVENT_SERVICE_FLAPPING = 'FLAPPING';
    public const EVENT_SERVICE_WARNING = 'WARNING';
    public const EVENT_SERVICE_UNKNOWN = 'UNKNOWN';
    public const EVENT_SERVICE_CRITICAL = 'CRITICAL';
    public const SERVICE_EVENTS = [
        self::EVENT_SERVICE_RECOVERY,
        self::EVENT_SERVICE_SCHEDULED_DOWNTIME,
        self::EVENT_SERVICE_FLAPPING,
        self::EVENT_SERVICE_WARNING,
        self::EVENT_SERVICE_UNKNOWN,
        self::EVENT_SERVICE_CRITICAL,
    ];

    /** @var string[] */
    private $events = [];

    /**
     * @param TimePeriod $timePeriod
     */
    public function __construct(
        private TimePeriod $timePeriod,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @inheritDoc
     */
    public function getTimePeriod(): TimePeriod
    {
        return $this->timePeriod;
    }

    /**
     * @param string $event
     *
     * @throws NotificationException
     *
     * @return self
     */
    public function addEvent(string $event): self
    {
        if (in_array($event, self::SERVICE_EVENTS, true) === false) {
            throw NotificationException::badEvent($event);
        }

        $this->events[] = $event;

        return $this;
    }
}
