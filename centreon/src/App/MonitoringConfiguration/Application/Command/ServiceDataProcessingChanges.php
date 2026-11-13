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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a service's freshness, flap detection, event handler, obsession, retention and
 * stalking directives.
 *
 * - `NoValue`: the field is not provided, the stored value stays as it is;
 * - `null`: the value is cleared, the service then inherits it;
 * - a value: it replaces the stored one.
 *
 * The on/off directives cannot be `null`: "use default" is a value of their own ({@see TriStateEnum::UseDefault}).
 * The event handler arguments are a list: an empty one clears them. Removing the event handler keeps them.
 */
final readonly class ServiceDataProcessingChanges
{
    /**
     * @param NoValue|list<string> $eventHandlerArgs
     * @param NoValue|ListChange<ServiceStalkingOptionEnum> $stalkingOptions
     */
    public function __construct(
        public NoValue|TriStateEnum $checkFreshness = new NoValue(),
        public NoValue|TriStateEnum $flapDetectionEnabled = new NoValue(),
        public NoValue|TriStateEnum $eventHandlerEnabled = new NoValue(),
        public NoValue|TriStateEnum $obsessOverService = new NoValue(),
        public NoValue|TriStateEnum $retainStatusInformation = new NoValue(),
        public NoValue|TriStateEnum $retainNonStatusInformation = new NoValue(),
        public NoValue|int|null $acknowledgmentTimeout = new NoValue(),
        public NoValue|int|null $freshnessThreshold = new NoValue(),
        public NoValue|int|null $lowFlapThreshold = new NoValue(),
        public NoValue|int|null $highFlapThreshold = new NoValue(),
        public NoValue|CommandId|null $eventHandlerCommandId = new NoValue(),
        #[Sensitive]
        public NoValue|array $eventHandlerArgs = new NoValue(),
        public NoValue|ListChange $stalkingOptions = new NoValue(),
    ) {
    }

    public function applyTo(ServiceDataProcessing $current): ServiceDataProcessing
    {
        return new ServiceDataProcessing(
            checkFreshness: NoValue::resolve($this->checkFreshness, $current->checkFreshness),
            flapDetectionEnabled: NoValue::resolve($this->flapDetectionEnabled, $current->flapDetectionEnabled),
            eventHandlerEnabled: NoValue::resolve($this->eventHandlerEnabled, $current->eventHandlerEnabled),
            obsessOverService: NoValue::resolve($this->obsessOverService, $current->obsessOverService),
            retainStatusInformation: NoValue::resolve($this->retainStatusInformation, $current->retainStatusInformation),
            retainNonStatusInformation: NoValue::resolve($this->retainNonStatusInformation, $current->retainNonStatusInformation),
            acknowledgmentTimeout: NoValue::resolve($this->acknowledgmentTimeout, $current->acknowledgmentTimeout),
            freshnessThreshold: NoValue::resolve($this->freshnessThreshold, $current->freshnessThreshold),
            lowFlapThreshold: NoValue::resolve($this->lowFlapThreshold, $current->lowFlapThreshold),
            highFlapThreshold: NoValue::resolve($this->highFlapThreshold, $current->highFlapThreshold),
            eventHandlerCommandId: NoValue::resolve($this->eventHandlerCommandId, $current->eventHandlerCommandId),
            eventHandlerArgs: NoValue::resolve($this->eventHandlerArgs, $current->eventHandlerArgs),
            stalkingOptions: $this->stalkingOptions instanceof ListChange
                ? $this->stalkingOptions->applyTo($current->stalkingOptions)
                : $current->stalkingOptions,
        );
    }
}
