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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;

/**
 * Maps {@see HostTemplateNotificationOptionEnum} to and from the strings of the API contract.
 */
final readonly class HostTemplateNotificationOptionEnumResolver
{
    /**
     * Listed for the OpenAPI schema, which an attribute cannot compute from the enum cases.
     */
    public const array API_VALUES = [
        self::DOWN_VALUE,
        self::UNREACHABLE_VALUE,
        self::RECOVERY_VALUE,
        self::FLAPPING_VALUE,
        self::DOWNTIME_SCHEDULED_VALUE,
        self::NONE_VALUE,
    ];
    private const string DOWN_VALUE = 'down';
    private const string DOWNTIME_SCHEDULED_VALUE = 'downtime_scheduled';
    private const string FLAPPING_VALUE = 'flapping';
    private const string NONE_VALUE = 'none';
    private const string RECOVERY_VALUE = 'recovery';
    private const string UNREACHABLE_VALUE = 'unreachable';

    public static function toString(HostTemplateNotificationOptionEnum $option): string
    {
        return match ($option) {
            HostTemplateNotificationOptionEnum::Down => self::DOWN_VALUE,
            HostTemplateNotificationOptionEnum::DowntimeScheduled => self::DOWNTIME_SCHEDULED_VALUE,
            HostTemplateNotificationOptionEnum::Flapping => self::FLAPPING_VALUE,
            HostTemplateNotificationOptionEnum::None => self::NONE_VALUE,
            HostTemplateNotificationOptionEnum::Recovery => self::RECOVERY_VALUE,
            HostTemplateNotificationOptionEnum::Unreachable => self::UNREACHABLE_VALUE,
        };
    }

    /**
     * Only called on a value the input's `Assert\Choice` accepted: an unknown string is a programming error.
     */
    public static function toDomain(string $option): HostTemplateNotificationOptionEnum
    {
        return match ($option) {
            self::DOWN_VALUE => HostTemplateNotificationOptionEnum::Down,
            self::DOWNTIME_SCHEDULED_VALUE => HostTemplateNotificationOptionEnum::DowntimeScheduled,
            self::FLAPPING_VALUE => HostTemplateNotificationOptionEnum::Flapping,
            self::NONE_VALUE => HostTemplateNotificationOptionEnum::None,
            self::RECOVERY_VALUE => HostTemplateNotificationOptionEnum::Recovery,
            self::UNREACHABLE_VALUE => HostTemplateNotificationOptionEnum::Unreachable,
            default => throw new \ValueError(sprintf('"%s" is not a valid notification option.', $option)),
        };
    }
}
