<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Tests\App\Shared\Double;

use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Event\EventInterface;

final class EventBusSpy implements EventBus
{
    /** @var list<EventInterface> */
    private array $events = [];

    public function fire(EventInterface $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param class-string<EventInterface> $eventClass
     */
    public function shouldHaveDispatched(string $eventClass, int|null $times = null): bool
    {
        $filteredEvents = array_filter($this->events, static fn ($event): bool => $event::class === $eventClass);
        if ($times === null) {
            return $filteredEvents !== [];
        }

        return \count($filteredEvents) === $times;
    }

    /**
     * @param class-string<EventInterface> $eventClass
     */
    public function shouldNotHaveDispatched(string $eventClass): bool
    {
        return ! $this->shouldHaveDispatched($eventClass);
    }
}
