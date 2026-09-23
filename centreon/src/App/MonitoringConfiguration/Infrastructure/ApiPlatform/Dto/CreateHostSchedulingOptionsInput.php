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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto;

use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimePeriod;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHostSchedulingOptionsInput
{
    public function __construct(
        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimePeriod()])]
        public ?int $checkTimeperiodId = null,

        #[Assert\GreaterThanOrEqual(1)]
        public ?int $maxCheckAttempts = null,

        #[Assert\GreaterThanOrEqual(1)]
        public ?int $normalCheckInterval = null,

        #[Assert\GreaterThanOrEqual(1)]
        public ?int $retryCheckInterval = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'active_check_enabled is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $activeCheckEnabled = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'passive_check_enabled is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $passiveCheckEnabled = null,
    ) {
    }
}
