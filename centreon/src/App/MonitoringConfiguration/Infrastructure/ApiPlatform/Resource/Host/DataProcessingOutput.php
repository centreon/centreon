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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use App\Shared\Domain\TriStateEnum;

/**
 * The `data_processing` sub-object of the Host output. On a Cloud platform the on-premise-only
 * members (acknowledgment timeout, flap detection + thresholds, event handler args) are left null.
 */
final readonly class DataProcessingOutput
{
    /**
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        public TriStateEnum $checkFreshness,
        public ?int $freshnessThreshold,
        public TriStateEnum $eventHandlerEnabled,
        public ?HostEventHandlerCommandOutput $eventHandler,
        public ?int $acknowledgmentTimeout = null,
        public ?TriStateEnum $flapDetectionEnabled = null,
        public ?int $lowFlapThreshold = null,
        public ?int $highFlapThreshold = null,
        public array $eventHandlerArgs = [],
    ) {
    }
}
