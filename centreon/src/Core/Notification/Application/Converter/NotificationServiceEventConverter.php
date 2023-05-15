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
    private const CASE_OK_AS_BIT = 0b0001;
    private const CASE_WARNING_AS_BIT = 0b0010;
    private const CASE_CRITICAL_AS_BIT = 0b0100;
    private const CASE_UNKNOWN_AS_BIT = 0b1000;

    private const MAX_BITFLAGS = 0b1111;

    private const CASE_OK_AS_STR = 'o';
    private const CASE_WARNING_AS_STR = 'w';
    private const CASE_CRITICAL_AS_STR = 'c';
    private const CASE_UNKNOWN_AS_STR = 'u';

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
        $eventsAsBitFlags = [];
        foreach ($events as $event) {
            $eventsAsBitFlags[] = match ($event) {
                NotificationServiceEvent::Ok => self::CASE_OK_AS_STR,
                NotificationServiceEvent::Warning => self::CASE_WARNING_AS_STR,
                NotificationServiceEvent::Critical => self::CASE_CRITICAL_AS_STR,
                NotificationServiceEvent::Unknown => self::CASE_UNKNOWN_AS_STR,
            };
        }

        return implode(',', array_unique($eventsAsBitFlags));
    }

    /**
     * Convert a string to an array of NotificationServiceEvent.
     * ex: 'd,u' => [NotificationServiceEvent::Down, NotificationServiceEvent::Unreachable]
     *
     * @param string $legacyStr
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
                default => throw new \LogicException('Should never occur, only for phpstan')
            };
        }

        return $events;
    }

    /**
     * Convert a NotificationServiceEvent into bitFlags.
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
     * @param int $bitFlags
     *
     * @throws \Throwable
     *
     * @return NotificationServiceEvent[]
     */
    public static function fromBitFlags(int $bitFlags): array
    {
        if ($bitFlags > self::MAX_BITFLAGS || $bitFlags < 0) {
            throw new \ValueError("\"{$bitFlags}\" is not a valid bitFlags for enum NotificationServiceEvent");
        }

        $enums = [];
        foreach (NotificationServiceEvent::cases() as $enum) {
            if ($bitFlags & self::toBit($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * Convert an array of NotificationServiceEvent into a bitFlags
     * If the array contains NotificationServiceEvent::None or is empty, an empty bitFlags will be returned
     *
     * @param NotificationServiceEvent[] $enums
     *
     * @return int
     */
    public static function toBitFlags(array $enums): int
    {
        if ($enums === []) {
            return 0;
        }

        $bitFlags = 0;
        foreach ($enums as $event) {
            // Value 0 is not a bit, we consider it resets the bitFlags
            if (self::toBit($event) === 0) {
                return 0;
            }
            $bitFlags |= self::toBit($event);
        }

        return $bitFlags;
    }
}
