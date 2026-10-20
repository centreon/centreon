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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a host's data processing group (freshness, flap detection, event handler).
 *
 * Every property can mean three different things, which is why most types read
 * `NoValue|<type>|null`:
 * - `NoValue`: the caller did not provide the field, the stored value stays as it is;
 * - `null`: the caller provided the field empty, the value is cleared (back to "not set");
 * - a value: it replaces the stored one.
 *
 * A property typed without `null` cannot be cleared. That is the case of the three on/off
 * directives: "use default" is a value of their own (`TriStateEnum::UseDefault`, meaning "inherit
 * from the templates, then the engine"), not an absence. The event handler arguments are a list:
 * an empty list clears them.
 */
final readonly class DataProcessingChanges
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

    public function applyTo(DataProcessing $current): DataProcessing
    {
        return $current->with(
            checkFreshness: $this->checkFreshness,
            flapDetectionEnabled: $this->flapDetectionEnabled,
            eventHandlerEnabled: $this->eventHandlerEnabled,
            acknowledgmentTimeout: $this->acknowledgmentTimeout,
            freshnessThreshold: $this->freshnessThreshold,
            lowFlapThreshold: $this->lowFlapThreshold,
            highFlapThreshold: $this->highFlapThreshold,
            eventHandlerCommandId: $this->eventHandlerCommandId,
            eventHandlerArgs: $this->eventHandlerArgs,
        );
    }
}
