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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * What a partial update changes in a service's scheduling directives.
 *
 * - `NoValue`: the field is not provided, the stored value stays as it is;
 * - `null`: the value is cleared, the service then inherits it from its templates;
 * - a value: it replaces the stored one.
 *
 * The three on/off directives cannot be `null`: "use default" is a value of their own ({@see TriStateEnum::UseDefault}).
 */
final readonly class ServiceSchedulingOptionsChanges
{
    public function __construct(
        public NoValue|TimePeriodId|null $checkTimeperiodId = new NoValue(),
        public NoValue|int|null $maxCheckAttempts = new NoValue(),
        public NoValue|int|null $normalCheckInterval = new NoValue(),
        public NoValue|int|null $retryCheckInterval = new NoValue(),
        public NoValue|TriStateEnum $activeCheckEnabled = new NoValue(),
        public NoValue|TriStateEnum $passiveCheckEnabled = new NoValue(),
        public NoValue|TriStateEnum $isVolatile = new NoValue(),
    ) {
    }

    public function applyTo(ServiceSchedulingOptions $current): ServiceSchedulingOptions
    {
        return new ServiceSchedulingOptions(
            checkTimeperiodId: NoValue::resolve($this->checkTimeperiodId, $current->checkTimeperiodId),
            maxCheckAttempts: NoValue::resolve($this->maxCheckAttempts, $current->maxCheckAttempts),
            normalCheckInterval: NoValue::resolve($this->normalCheckInterval, $current->normalCheckInterval),
            retryCheckInterval: NoValue::resolve($this->retryCheckInterval, $current->retryCheckInterval),
            activeCheckEnabled: NoValue::resolve($this->activeCheckEnabled, $current->activeCheckEnabled),
            passiveCheckEnabled: NoValue::resolve($this->passiveCheckEnabled, $current->passiveCheckEnabled),
            isVolatile: NoValue::resolve($this->isVolatile, $current->isVolatile),
        );
    }
}
