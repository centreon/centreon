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

namespace App\AiAssistance\Infrastructure\ApiPlatform\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One metric of a performance graph, summarised over the displayed window.
 */
final class GraphMetricInput
{
    public function __construct(
        #[ApiProperty(openapiContext: ['example' => 'Round-Trip-Time Average (ms)'])]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[ApiProperty(openapiContext: ['example' => 'ms'])]
        #[Assert\Length(max: 50)]
        public ?string $unit = null,

        #[ApiProperty(description: 'Minimum value over the displayed window')]
        public ?float $minimum = null,

        #[ApiProperty(description: 'Maximum value over the displayed window')]
        public ?float $maximum = null,

        #[ApiProperty(description: 'Average value over the displayed window')]
        public ?float $average = null,

        #[ApiProperty(description: 'Most recent value in the displayed window')]
        public ?float $current = null,
    ) {
    }
}
