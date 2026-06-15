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

final class TranslateResourceFilterInput
{
    public function __construct(
        #[ApiProperty(
            description: 'Natural-language request: a description of resources to filter, '
                . 'or a question to analyse',
            openapiContext: ['example' => 'critical services not yet handled'],
        )]
        #[Assert\Length(min: 1, max: 2000)]
        public string $prompt,

        #[ApiProperty(
            description: 'The search expression currently applied to the listing, used to decide '
                . 'whether a question is about the displayed resources',
            openapiContext: ['example' => 'type:service status:critical'],
        )]
        #[Assert\Length(max: 2000)]
        public ?string $currentFilter = null,
    ) {
    }
}
