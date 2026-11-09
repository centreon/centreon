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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;

/**
 * Maps {@see ServiceNotificationOptionEnum} to the strings of the API contract.
 */
final readonly class ServiceNotificationOptionEnumResolver
{
    /**
     * Listed for the OpenAPI schema, which an attribute cannot compute from the enum cases.
     */
    public const array API_VALUES = [
        self::WARNING_VALUE,
        self::UNKNOWN_VALUE,
        self::CRITICAL_VALUE,
        self::RECOVERY_VALUE,
        self::FLAPPING_VALUE,
        self::DOWNTIME_SCHEDULED_VALUE,
        self::NONE_VALUE,
    ];
    private const string WARNING_VALUE = 'warning';
    private const string UNKNOWN_VALUE = 'unknown';
    private const string CRITICAL_VALUE = 'critical';
    private const string RECOVERY_VALUE = 'recovery';
    private const string FLAPPING_VALUE = 'flapping';
    private const string DOWNTIME_SCHEDULED_VALUE = 'downtime_scheduled';
    private const string NONE_VALUE = 'none';

    public static function toString(ServiceNotificationOptionEnum $option): string
    {
        return match ($option) {
            ServiceNotificationOptionEnum::Warning => self::WARNING_VALUE,
            ServiceNotificationOptionEnum::Unknown => self::UNKNOWN_VALUE,
            ServiceNotificationOptionEnum::Critical => self::CRITICAL_VALUE,
            ServiceNotificationOptionEnum::Recovery => self::RECOVERY_VALUE,
            ServiceNotificationOptionEnum::Flapping => self::FLAPPING_VALUE,
            ServiceNotificationOptionEnum::DowntimeScheduled => self::DOWNTIME_SCHEDULED_VALUE,
            ServiceNotificationOptionEnum::None => self::NONE_VALUE,
        };
    }
}
