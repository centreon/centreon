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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;

/**
 * Maps {@see ServiceStalkingOptionEnum} to the strings of the API contract.
 */
final readonly class ServiceStalkingOptionEnumResolver
{
    /**
     * Listed for the OpenAPI schema, which an attribute cannot compute from the enum cases.
     */
    public const array API_VALUES = [
        self::OK_VALUE,
        self::WARNING_VALUE,
        self::UNKNOWN_VALUE,
        self::CRITICAL_VALUE,
    ];
    private const string OK_VALUE = 'ok';
    private const string WARNING_VALUE = 'warning';
    private const string UNKNOWN_VALUE = 'unknown';
    private const string CRITICAL_VALUE = 'critical';

    public static function toString(ServiceStalkingOptionEnum $option): string
    {
        return match ($option) {
            ServiceStalkingOptionEnum::Ok => self::OK_VALUE,
            ServiceStalkingOptionEnum::Warning => self::WARNING_VALUE,
            ServiceStalkingOptionEnum::Unknown => self::UNKNOWN_VALUE,
            ServiceStalkingOptionEnum::Critical => self::CRITICAL_VALUE,
        };
    }

    /**
     * @throws \ValueError for a string the API does not accept, which the input validation refuses first
     */
    public static function toDomain(string $option): ServiceStalkingOptionEnum
    {
        return match ($option) {
            self::OK_VALUE => ServiceStalkingOptionEnum::Ok,
            self::WARNING_VALUE => ServiceStalkingOptionEnum::Warning,
            self::UNKNOWN_VALUE => ServiceStalkingOptionEnum::Unknown,
            self::CRITICAL_VALUE => ServiceStalkingOptionEnum::Critical,
            default => throw new \ValueError(sprintf('"%s" is not a valid stalking option.', $option)),
        };
    }
}
