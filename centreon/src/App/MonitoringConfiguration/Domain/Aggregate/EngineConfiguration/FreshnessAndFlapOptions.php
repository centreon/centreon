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

final readonly class FreshnessAndFlapOptions
{
    public const DEFAULT_ADDITIONAL_FRESHNESS_LATENCY = 15;
    public const DEFAULT_LOW_FLAP_THRESHOLD = 25.0;
    public const DEFAULT_HIGH_FLAP_THRESHOLD = 50.0;

    public function __construct(
        public bool $checkServiceFreshness = true,
        public bool $checkHostFreshness = false,
        public int $additionalFreshnessLatency = self::DEFAULT_ADDITIONAL_FRESHNESS_LATENCY,
        public bool $enableFlapDetection = true,
        public float $lowServiceFlapThreshold = self::DEFAULT_LOW_FLAP_THRESHOLD,
        public float $highServiceFlapThreshold = self::DEFAULT_HIGH_FLAP_THRESHOLD,
        public float $lowHostFlapThreshold = self::DEFAULT_LOW_FLAP_THRESHOLD,
        public float $highHostFlapThreshold = self::DEFAULT_HIGH_FLAP_THRESHOLD,
    ) {
    }
}
