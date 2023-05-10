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

namespace Core\Notification\Application\Converter;

use Core\Notification\Domain\Model\NotificationHostEvent;

class NotificationHostEventConverter
{
    public const CASE_UP_AS_BIT = 0b001;
    public const CASE_DOWN_AS_BIT = 0b010;
    public const CASE_UNREACHABLE_AS_BIT = 0b100;

    public const MAX_BITMASK = 0b111;

    public const CASE_UP_AS_STR = 'o';
    public const CASE_DOWN_AS_STR = 'd';
    public const CASE_UNREACHABLE_AS_STR = 'u';

    /**
     * Convert an array of NotificationHostEvent to a string.
     * ex: [NotificationHostEvent::Down, NotificationHostEvent::Unreachable] => 'd,u'
     *
     * @param NotificationHostEvent[] $events
     *
     * @return string
     */
    public static function toString(array $events): string
    {
        $eventsAsBitmask = [];
        foreach ($events as $event) {
            $eventsAsBitmask[] = match ($event) {
                NotificationHostEvent::Up => self::CASE_UP_AS_STR,
                NotificationHostEvent::Down => self::CASE_DOWN_AS_STR,
                NotificationHostEvent::Unreachable => self::CASE_UNREACHABLE_AS_STR,
            };
        }

        return implode(',', array_unique($eventsAsBitmask));
    }

    /**
     * Convert a string to an array of NotificationHostEvent.
     * ex: 'd,u' => [NotificationHostEvent::Down, NotificationHostEvent::Unreachable]
     *
     * @param string $legacyStr
     *
     * @return NotificationHostEvent[]
     */
    public static function fromString(string $legacyStr): array
    {
        if ($legacyStr === '') {
            return [];
        }

        $legacyValues = explode(',', $legacyStr);
        $legacyValues = array_unique(array_map(trim(...), $legacyValues));
        $events = [];
        foreach ($legacyValues as $value) {
            $events[] = match ($value) {
                self::CASE_UP_AS_STR => NotificationHostEvent::Up,
                self::CASE_DOWN_AS_STR => NotificationHostEvent::Down,
                self::CASE_UNREACHABLE_AS_STR => NotificationHostEvent::Unreachable,
            };
        }

        return $events;
    }

    /**
     * Convert a NotificationHostEvent into bitmask.
     *
     * @param NotificationHostEvent $event
     *
     * @return int
     */
    public static function toBit(NotificationHostEvent $event): int
    {
        return match ($event) {
            NotificationHostEvent::Up => self::CASE_UP_AS_BIT,
            NotificationHostEvent::Down => self::CASE_DOWN_AS_BIT,
            NotificationHostEvent::Unreachable => self::CASE_UNREACHABLE_AS_BIT,
        };
    }




    /**
     * Convert a bitmask into an array of NotificationHostEvent.
     *
     * @param int $bitmask
     *
     * @throws \Throwable
     *
     * @return NotificationHostEvent[]
     */
    public static function fromBitmask(int $bitmask): array
    {
        if ($bitmask > self::MAX_BITMASK || $bitmask < 0) {
            throw new \ValueError("\"{$bitmask}\" is not a valid bitmask for enum NotificationHostEvent");
        }

        $enums = [];
        foreach (NotificationHostEvent::cases() as $enum) {
            if ($bitmask & self::toBit($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * Convert an array of NotificationHostEvent into a bitmask
     * If the array contains NotificationHostEvent::None or is empty, an empty bitmask will be returned
     *
     * @param NotificationHostEvent[] $enums
     *
     * @return int
     */
    public static function toBitmask(array $enums): int
    {
        if ($enums === []) {
            return 0;
        }

        $bitmask = 0;
        foreach ($enums as $event) {
            // Value 0 is not a bit, we consider it resets the bitmask
            if (self::toBit($event) === 0) {
                return 0;
            }
            $bitmask |= self::toBit($event);
        }

        return $bitmask;
    }
}
