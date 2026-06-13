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

namespace App\AiAssistance\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\AiAssistance\Infrastructure\ApiPlatform\State\GetAiAvailabilityProvider;

#[Get(
    shortName: 'AiAssistance',
    uriTemplate: '/ai/availability',
    provider: GetAiAvailabilityProvider::class,
    openapi: new Model\Operation(
        summary: 'Get the AI features availability',
        description: 'AI features are available when an AI provider is enabled and its spend limit '
            . 'is not reached. The frontend hides the AI features when no provider is enabled, and '
            . 'disables them when the budget is exhausted.',
    ),
)]
final readonly class AiAvailabilityResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Whether AI features are available',
            writable: false,
        )]
        public bool $isAvailable,

        #[ApiProperty(
            description: "Why AI features are unavailable: 'no_provider' (no enabled provider) or "
                . "'budget_exhausted' (the enabled provider reached its spend limit). Null when available.",
            writable: false,
            openapiContext: ['enum' => ['no_provider', 'budget_exhausted', null]],
        )]
        public ?string $reason = null,
    ) {
    }
}
