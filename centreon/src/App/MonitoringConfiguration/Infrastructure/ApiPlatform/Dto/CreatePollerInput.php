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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingPollerToken;
use App\MonitoringConfiguration\Infrastructure\Validator\UniquePollerName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePollerInput
{
    public function __construct(
        #[Assert\Length(min: PollerName::MIN_LENGTH, max: PollerName::MAX_LENGTH)]
        #[UniquePollerName]
        public string $name,

        #[Assert\Choice(choices: [PollerTypeEnum::VM->value, PollerTypeEnum::Docker->value])]
        public string $pollerType,

        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: PollerAddress::MIN_LENGTH, max: PollerAddress::MAX_LENGTH)]
        public string $address,

        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: 1, max: 255)]
        #[ExistingPollerToken]
        public string $pollerTokenName,
    ) {
    }
}
