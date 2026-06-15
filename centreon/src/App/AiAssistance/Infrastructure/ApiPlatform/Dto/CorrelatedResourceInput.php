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
 * One resource of an alert-correlation request, as displayed in the listing.
 */
final class CorrelatedResourceInput
{
    public function __construct(
        #[ApiProperty(openapiContext: ['example' => 'Ping'])]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[ApiProperty(openapiContext: ['example' => 'service'])]
        #[Assert\Length(min: 1, max: 50)]
        public string $resourceType,

        #[ApiProperty(openapiContext: ['example' => 'CRITICAL'])]
        #[Assert\Length(min: 1, max: 50)]
        public string $status,

        #[ApiProperty(description: 'Name of the parent host, for services')]
        #[Assert\Length(max: 255)]
        public ?string $parentName = null,

        #[ApiProperty(description: 'Latest check output')]
        #[Assert\Length(max: 10000)]
        public ?string $information = null,

        #[ApiProperty(openapiContext: ['example' => '2h 31m'])]
        #[Assert\Length(max: 255)]
        public ?string $duration = null,

        #[ApiProperty(openapiContext: ['example' => '3/3 (Hard)'])]
        #[Assert\Length(max: 50)]
        public ?string $tries = null,

        #[ApiProperty(description: 'Whether the resource is acknowledged')]
        public bool $isAcknowledged = false,

        #[ApiProperty(description: 'Whether the resource is in downtime')]
        public bool $isInDowntime = false,
    ) {
    }
}
