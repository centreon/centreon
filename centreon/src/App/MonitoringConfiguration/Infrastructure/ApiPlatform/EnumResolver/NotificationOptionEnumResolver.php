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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver;

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;

/**
 * Maps {@see NotificationOptionEnum} to and from the strings of the API contract.
 */
final readonly class NotificationOptionEnumResolver
{
    private const string DOWN_VALUE = 'down';
    private const string DOWNTIME_SCHEDULED_VALUE = 'downtime_scheduled';
    private const string FLAPPING_VALUE = 'flapping';
    private const string NONE_VALUE = 'none';
    private const string RECOVERY_VALUE = 'recovery';
    private const string UNREACHABLE_VALUE = 'unreachable';

    public static function toString(NotificationOptionEnum $option): string
    {
        return match ($option) {
            NotificationOptionEnum::Down => self::DOWN_VALUE,
            NotificationOptionEnum::DowntimeScheduled => self::DOWNTIME_SCHEDULED_VALUE,
            NotificationOptionEnum::Flapping => self::FLAPPING_VALUE,
            NotificationOptionEnum::None => self::NONE_VALUE,
            NotificationOptionEnum::Recovery => self::RECOVERY_VALUE,
            NotificationOptionEnum::Unreachable => self::UNREACHABLE_VALUE,
        };
    }

    /**
     * Only ever called on a value the `Assert\Choice` on the input DTO already accepted, which is
     * why an unknown string is a programming error rather than a client one.
     */
    public static function toDomain(string $option): NotificationOptionEnum
    {
        return match ($option) {
            self::DOWN_VALUE => NotificationOptionEnum::Down,
            self::DOWNTIME_SCHEDULED_VALUE => NotificationOptionEnum::DowntimeScheduled,
            self::FLAPPING_VALUE => NotificationOptionEnum::Flapping,
            self::NONE_VALUE => NotificationOptionEnum::None,
            self::RECOVERY_VALUE => NotificationOptionEnum::Recovery,
            self::UNREACHABLE_VALUE => NotificationOptionEnum::Unreachable,
            default => throw new \ValueError(sprintf('"%s" is not a valid notification option.', $option)),
        };
    }
}
