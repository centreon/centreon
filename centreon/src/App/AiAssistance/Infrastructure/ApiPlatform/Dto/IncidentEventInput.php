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

final class IncidentEventInput
{
    public function __construct(
        #[ApiProperty(
            description: 'Date of the event, as displayed',
            openapiContext: ['example' => '2026-06-12T10:24:00+02:00'],
        )]
        #[Assert\Length(min: 1, max: 64)]
        public string $date,

        #[ApiProperty(
            description: 'Type of the event',
            openapiContext: ['example' => 'acknowledgement'],
        )]
        #[Assert\Length(min: 1, max: 64)]
        public string $type,

        #[ApiProperty(description: 'Content or output of the event')]
        #[Assert\Length(max: 2000)]
        public ?string $content = null,

        #[ApiProperty(description: 'Author of the event, when user-generated')]
        #[Assert\Length(max: 255)]
        public ?string $author = null,

        #[ApiProperty(
            description: 'Status of the resource after the event, for status changes',
            openapiContext: ['example' => 'CRITICAL'],
        )]
        #[Assert\Length(max: 50)]
        public ?string $status = null,
    ) {
    }
}
