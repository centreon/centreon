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

use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;

/**
 * The notification settings; NoValue keeps the current value, null clears it.
 */
final readonly class HostTemplateNotificationsChange
{
    public function __construct(
        public NoValue|TriStateEnum $enabled = new NoValue(),
        public NoValue|int|null $interval = new NoValue(),
        public NoValue|TimePeriodId|null $periodId = new NoValue(),
        public NoValue|int|null $firstDelay = new NoValue(),
        public NoValue|int|null $recoveryDelay = new NoValue(),
        public NoValue|bool $contactAdditiveInheritance = new NoValue(),
        public NoValue|bool $contactGroupAdditiveInheritance = new NoValue(),
    ) {
    }

    public function applyTo(?Notifications $current): Notifications
    {
        $current ??= Notifications::default();

        return new Notifications(
            enabled: NoValue::resolve($this->enabled, $current->enabled),
            contactIds: $current->contactIds,
            contactGroupIds: $current->contactGroupIds,
            options: $current->options,
            interval: NoValue::resolve($this->interval, $current->interval),
            periodId: NoValue::resolve($this->periodId, $current->periodId),
            firstDelay: NoValue::resolve($this->firstDelay, $current->firstDelay),
            recoveryDelay: NoValue::resolve($this->recoveryDelay, $current->recoveryDelay),
            contactAdditiveInheritance: NoValue::resolve($this->contactAdditiveInheritance, $current->contactAdditiveInheritance),
            contactGroupAdditiveInheritance: NoValue::resolve($this->contactGroupAdditiveInheritance, $current->contactGroupAdditiveInheritance),
        );
    }
}
