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

namespace App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration;

final readonly class SchedulingOptions
{
    public const DEFAULT_SLEEP_TIME = 0.5;
    public const DEFAULT_INTER_CHECK_DELAY_METHOD = 's';
    public const DEFAULT_INTERLEAVE_FACTOR = 's';
    public const DEFAULT_MAX_CONCURRENT_CHECKS = 0;
    public const DEFAULT_MAX_CHECK_SPREAD = 15;
    public const DEFAULT_CHECK_RESULT_REAPER_FREQUENCY = 5;
    public const DEFAULT_CACHED_CHECK_HORIZON = 15;
    public const DEFAULT_SERVICE_CHECK_TIMEOUT = 60;
    public const DEFAULT_HOST_CHECK_TIMEOUT = 30;
    public const DEFAULT_EVENT_HANDLER_TIMEOUT = 30;
    public const DEFAULT_NOTIFICATION_TIMEOUT = 30;

    public function __construct(
        public float $sleepTime = self::DEFAULT_SLEEP_TIME,
        public string $hostInterCheckDelayMethod = self::DEFAULT_INTER_CHECK_DELAY_METHOD,
        public string $serviceInterCheckDelayMethod = self::DEFAULT_INTER_CHECK_DELAY_METHOD,
        public string $serviceInterleaveFactor = self::DEFAULT_INTERLEAVE_FACTOR,
        public int $maxConcurrentChecks = self::DEFAULT_MAX_CONCURRENT_CHECKS,
        public int $maxServiceCheckSpread = self::DEFAULT_MAX_CHECK_SPREAD,
        public int $maxHostCheckSpread = self::DEFAULT_MAX_CHECK_SPREAD,
        public int $checkResultReaperFrequency = self::DEFAULT_CHECK_RESULT_REAPER_FREQUENCY,
        public bool $autoRescheduleChecks = false,
        public int $cachedHostCheckHorizon = self::DEFAULT_CACHED_CHECK_HORIZON,
        public int $cachedServiceCheckHorizon = self::DEFAULT_CACHED_CHECK_HORIZON,
        public int $serviceCheckTimeout = self::DEFAULT_SERVICE_CHECK_TIMEOUT,
        public int $hostCheckTimeout = self::DEFAULT_HOST_CHECK_TIMEOUT,
        public int $eventHandlerTimeout = self::DEFAULT_EVENT_HANDLER_TIMEOUT,
        public int $notificationTimeout = self::DEFAULT_NOTIFICATION_TIMEOUT,
        public bool $enableEnvironmentMacros = false,
    ) {
    }
}
