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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Webmozart\Assert\Assert;

final readonly class SchedulingOptions
{
    public function __construct(
        public ?TimePeriodId $checkTimeperiodId = null,
        public ?int $maxCheckAttempts = null,
        public ?int $normalCheckInterval = null,
        public ?int $retryCheckInterval = null,
        public TriStateEnum $activeCheckEnabled = TriStateEnum::UseDefault,
        public TriStateEnum $passiveCheckEnabled = TriStateEnum::UseDefault,
    ) {
        if ($maxCheckAttempts !== null) {
            Assert::greaterThanEq($maxCheckAttempts, 1, 'SchedulingOptions::maxCheckAttempts expected to be >= 1, got %s.');
        }
        if ($normalCheckInterval !== null) {
            Assert::greaterThanEq($normalCheckInterval, 1, 'SchedulingOptions::normalCheckInterval expected to be >= 1, got %s.');
        }
        if ($retryCheckInterval !== null) {
            Assert::greaterThanEq($retryCheckInterval, 1, 'SchedulingOptions::retryCheckInterval expected to be >= 1, got %s.');
        }
    }
}
