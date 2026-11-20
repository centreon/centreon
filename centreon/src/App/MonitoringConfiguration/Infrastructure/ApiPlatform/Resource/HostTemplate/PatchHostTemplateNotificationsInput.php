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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimePeriod;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PatchHostTemplateNotificationsInput
{
    public function __construct(
        #[ApiProperty(description: 'null means "use_default".')]
        #[Assert\Choice(choices: [TriStateEnum::False->value, TriStateEnum::True->value, TriStateEnum::UseDefault->value])]
        public ?string $enabled = null,

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $interval = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingTimePeriod()])]
        public ?int $timeperiodId = null,

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $firstDelay = null,

        #[Assert\Range(min: 0, max: HostTemplate::MAX_INTEGER)]
        public ?int $recoveryDelay = null,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        #[NotNullWhenProvided]
        public ?bool $contactAdditiveInheritance = null,

        #[ApiProperty(description: "Ignored unless the platform's inheritance mode enables additive inheritance.")]
        #[NotNullWhenProvided]
        public ?bool $contactGroupAdditiveInheritance = null,
    ) {
    }
}
