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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * NoValue keeps the current value, null clears it.
 */
final readonly class HostTemplateDataProcessingChange
{
    /**
     * @param NoValue|list<string> $eventHandlerArgs
     */
    public function __construct(
        public NoValue|TriStateEnum $checkFreshness = new NoValue(),
        public NoValue|TriStateEnum $flapDetectionEnabled = new NoValue(),
        public NoValue|TriStateEnum $eventHandlerEnabled = new NoValue(),
        public NoValue|int|null $acknowledgmentTimeout = new NoValue(),
        public NoValue|int|null $freshnessThreshold = new NoValue(),
        public NoValue|int|null $lowFlapThreshold = new NoValue(),
        public NoValue|int|null $highFlapThreshold = new NoValue(),
        public NoValue|CommandId|null $eventHandlerCommandId = new NoValue(),
        public NoValue|array $eventHandlerArgs = new NoValue(),
    ) {
    }

    /**
     * A template checked by a Centreon Monitoring Agent pushes its results: freshness is always
     * checked, with a fixed threshold, whatever was asked.
     */
    public function withCentreonMonitoringAgentFreshness(): self
    {
        return new self(
            checkFreshness: TriStateEnum::True,
            flapDetectionEnabled: $this->flapDetectionEnabled,
            eventHandlerEnabled: $this->eventHandlerEnabled,
            acknowledgmentTimeout: $this->acknowledgmentTimeout,
            freshnessThreshold: DataProcessing::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD,
            lowFlapThreshold: $this->lowFlapThreshold,
            highFlapThreshold: $this->highFlapThreshold,
            eventHandlerCommandId: $this->eventHandlerCommandId,
            eventHandlerArgs: $this->eventHandlerArgs,
        );
    }

    public function applyTo(DataProcessing $current): DataProcessing
    {
        return new DataProcessing(
            checkFreshness: NoValue::resolve($this->checkFreshness, $current->checkFreshness),
            flapDetectionEnabled: NoValue::resolve($this->flapDetectionEnabled, $current->flapDetectionEnabled),
            eventHandlerEnabled: NoValue::resolve($this->eventHandlerEnabled, $current->eventHandlerEnabled),
            acknowledgmentTimeout: NoValue::resolve($this->acknowledgmentTimeout, $current->acknowledgmentTimeout),
            freshnessThreshold: NoValue::resolve($this->freshnessThreshold, $current->freshnessThreshold),
            lowFlapThreshold: NoValue::resolve($this->lowFlapThreshold, $current->lowFlapThreshold),
            highFlapThreshold: NoValue::resolve($this->highFlapThreshold, $current->highFlapThreshold),
            eventHandlerCommandId: NoValue::resolve($this->eventHandlerCommandId, $current->eventHandlerCommandId),
            eventHandlerArgs: NoValue::resolve($this->eventHandlerArgs, $current->eventHandlerArgs),
        );
    }
}
