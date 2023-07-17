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

namespace Core\Host\Application\Converter;

use Core\Host\Domain\Model\HostEvent;

/**
 * This class purpose is to allow convertion from the legacy host event enum to a string.
 */
class HostEventConverter
{
    public const MAX_BITFLAG = 0b11111;
    private const CASE_NONE_AS_STR = 'n';
    private const CASE_DOWN_AS_STR = 'd';
    private const CASE_UNREACHABLE_AS_STR = 'u';
    private const CASE_RECOVERY_AS_STR = 'r';
    private const CASE_FLAPPING_AS_STR = 'f';
    private const CASE_DOWNTIME_SCHEDULED_AS_STR = 's';
    private const CASE_NONE_AS_BIT = 0b00000;
    private const CASE_DOWN_AS_BIT = 0b00001;
    private const CASE_UNREACHABLE_AS_BIT = 0b00010;
    private const CASE_RECOVERY_AS_BIT = 0b00100;
    private const CASE_FLAPPING_AS_BIT = 0b01000;
    private const CASE_DOWNTIME_SCHEDULED_AS_BIT = 0b10000;

    /**
     * Convert an array of HostEvent to a string.
     * ex: [HostEvent::Down, HostEvent::Unreachable] => 'd,u'.
     *
     * @param HostEvent[] $events
     *
     * @return string
     */
    public static function toString(array $events): string
    {
        $eventsAsBitmask = [];
        foreach ($events as $event) {
            $eventsAsBitmask[] = match ($event) {
                HostEvent::None => self::CASE_NONE_AS_STR,
                HostEvent::Down => self::CASE_DOWN_AS_STR,
                HostEvent::Unreachable => self::CASE_UNREACHABLE_AS_STR,
                HostEvent::Recovery => self::CASE_RECOVERY_AS_STR,
                HostEvent::Flapping => self::CASE_FLAPPING_AS_STR,
                HostEvent::DowntimeScheduled => self::CASE_DOWNTIME_SCHEDULED_AS_STR,
            };
        }

        return implode(',', array_unique($eventsAsBitmask));
    }

    /**
     * Convert a string to an array of HostEvent.
     * ex: 'd,u' => [HostEvent::Down, HostEvent::Unreachable].
     *
     * @param string $events
     * @param string $legacyStr
     *
     * @return HostEvent[]
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
                self::CASE_NONE_AS_STR => HostEvent::None,
                self::CASE_DOWN_AS_STR => HostEvent::Down,
                self::CASE_UNREACHABLE_AS_STR => HostEvent::Unreachable,
                self::CASE_RECOVERY_AS_STR => HostEvent::Recovery,
                self::CASE_FLAPPING_AS_STR => HostEvent::Flapping,
                self::CASE_DOWNTIME_SCHEDULED_AS_STR => HostEvent::DowntimeScheduled,
            };
        }

        return $events;
    }

    /**
     * Convert a HostEvent into bitFlag.
     *
     * @param HostEvent $event
     *
     * @return int
     */
    public static function toBit(HostEvent $event): int
    {
        return match ($event) {
            HostEvent::None => self::CASE_NONE_AS_BIT,
            HostEvent::Down => self::CASE_DOWN_AS_BIT,
            HostEvent::Unreachable => self::CASE_UNREACHABLE_AS_BIT,
            HostEvent::Recovery => self::CASE_RECOVERY_AS_BIT,
            HostEvent::Flapping => self::CASE_FLAPPING_AS_BIT,
            HostEvent::DowntimeScheduled => self::CASE_DOWNTIME_SCHEDULED_AS_BIT,
        };
    }

    /**
     * Convert a bitFlag into an array of HostEvent.
     *
     * @param ?int $bitFlag
     *
     * @throws \Throwable
     *
     * @return HostEvent[]
     */
    public static function fromBitFlag(?int $bitFlag): array
    {
        if ($bitFlag > self::MAX_BITFLAG || $bitFlag < 0) {
            throw new \ValueError("\"{$bitFlag}\" is not a valid value for enum HostEvent");
        }

        if ($bitFlag === self::CASE_NONE_AS_BIT) {
            return [HostEvent::None];
        }

        $enums = [];
        foreach (HostEvent::cases() as $enum) {
            if ($bitFlag & self::toBit($enum)) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * Convert an array of HostEvent into a bitFlag
     * If the array contains HostEvent::None, an empty bitFlag will be returned
     * If the array is empty, null is returned.
     *
     * @param HostEvent[] $enums
     *
     * @return ?int
     */
    public static function toBitFlag(array $enums): ?int
    {
        if ($enums === []) {
            return null;
        }

        $bitFlag = 0;
        foreach ($enums as $event) {
            // Value 0 is not a bit, we consider it resets the bitFlag
            if (self::toBit($event) === 0) {
                return 0;
            }
            $bitFlag |= self::toBit($event);
        }

        return $bitFlag;
    }
}
