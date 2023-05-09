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

use Core\Notification\Domain\Model\NotificationServiceEvent;

class NotificationServiceEventConverter
{
    public const CASE_OK_AS_BIT = 0b0001;
    public const CASE_WARNING_AS_BIT = 0b0010;
    public const CASE_CRITICAL_AS_BIT = 0b0100;
    public const CASE_UNKNOWN_AS_BIT = 0b1000;

    public const MAX_BITMASK = 0b1111;

    public const CASE_OK_AS_STR = 'o';
    public const CASE_WARNING_AS_STR = 'w';
    public const CASE_CRITICAL_AS_STR = 'c';
    public const CASE_UNKNOWN_AS_STR = 'u';

    /**
     * Convert an array of NotificationServiceEvent to a string.
     * ex: [NotificationServiceEvent::Ok, NotificationServiceEvent::Unknown] => 'o,u'
     *
     * @param NotificationServiceEvent[] $events
     *
     * @return string
     */
    public static function toString(array $events): string
    {
        $eventsAsBitmask = [];
        foreach ($events as $event) {
            $eventsAsBitmask[] = match ($event) {
                NotificationServiceEvent::Ok => self::CASE_OK_AS_STR,
                NotificationServiceEvent::Warning => self::CASE_WARNING_AS_STR,
                NotificationServiceEvent::Critical => self::CASE_CRITICAL_AS_STR,
                NotificationServiceEvent::Unknown => self::CASE_UNKNOWN_AS_STR,
            };
        }

        return implode(',', array_unique($eventsAsBitmask));
    }

    /**
     * Convert a string to an array of NotificationServiceEvent.
     * ex: 'd,u' => [NotificationServiceEvent::Down, NotificationServiceEvent::Unreachable]
     *
     * @param string $events
     *
     * @return NotificationServiceEvent[]
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
                self::CASE_OK_AS_STR => NotificationServiceEvent::Ok,
                self::CASE_WARNING_AS_STR => NotificationServiceEvent::Warning,
                self::CASE_CRITICAL_AS_STR => NotificationServiceEvent::Critical,
                self::CASE_UNKNOWN_AS_STR => NotificationServiceEvent::Unknown,
            };
        }

        return $events;
    }

    /**
     * Convert a NotificationServiceEvent into bitmask.
     *
     * @param NotificationServiceEvent $event
     *
     * @return int
     */
    public static function toBit(NotificationServiceEvent $event): int
    {
        return match ($event) {
            NotificationServiceEvent::Ok => self::CASE_OK_AS_BIT,
            NotificationServiceEvent::Warning => self::CASE_WARNING_AS_BIT,
            NotificationServiceEvent::Critical => self::CASE_CRITICAL_AS_BIT,
            NotificationServiceEvent::Unknown => self::CASE_UNKNOWN_AS_BIT,
        };
    }




    /**
     * Convert a bitmask into an array of NotificationServiceEvent.
     *
     * @param int $bitmask
     *
     * @throws \Throwable
     *
     * @return NotificationServiceEvent[]
     */
    public static function fromBitmask(int $bitmask): array
    {
        if ($bitmask > self::MAX_BITMASK || $bitmask < 0) {
            throw new \ValueError("\"{$bitmask}\" is not a valid bitmask for enum NotificationServiceEvent");
        }

        $enums = [];
        foreach (NotificationServiceEvent::cases() as $enum) {
            if ($bitmask & self::toBit($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * Convert an array of NotificationServiceEvent into a bitmask
     * If the array contains NotificationServiceEvent::None, an empty bitmask will be returned
     * If the array is empty, a full bitmask will be returned.
     *
     * @param NotificationServiceEvent[] $enums
     *
     * @return int
     */
    public static function toBitmask(array $enums): int
    {
        if ($enums === []) {
            return self::MAX_BITMASK;
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