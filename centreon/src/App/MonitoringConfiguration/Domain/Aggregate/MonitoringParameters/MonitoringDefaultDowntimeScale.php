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

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

use Webmozart\Assert\Assert;

final readonly class MonitoringDefaultDowntimeScale
{
    public const DEFAULT_DOWNTIME_SCALE_DAY = 'd';
    public const DEFAULT_DOWNTIME_SCALE_HOUR = 'h';
    public const DEFAULT_DOWNTIME_SCALE_MINUTE = 'm';
    public const DEFAULT_DOWNTIME_SCALE_SECOND = 's';
    public const ALLOWED_SCALES = [
        self::DEFAULT_DOWNTIME_SCALE_HOUR,
        self::DEFAULT_DOWNTIME_SCALE_MINUTE,
        self::DEFAULT_DOWNTIME_SCALE_SECOND,
    ];

    public function __construct(public string $value)
    {
        Assert::inArray($value, self::ALLOWED_SCALES);
    }
}
