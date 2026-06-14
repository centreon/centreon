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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Provider configuration to discover models for, as entered in the form. The
 * token is the one typed in the form; when omitted and an id is given, the
 * stored token of that provider is used.
 */
final class DiscoverModelsInput
{
    public function __construct(
        #[ApiProperty(description: 'Type of the AI provider', openapiContext: ['example' => 'anthropic'])]
        #[Assert\Length(min: 1, max: 50)]
        public string $type,

        #[ApiProperty(description: 'API token to use; omit while editing to use the stored token')]
        #[Assert\Length(min: 1, max: 4096)]
        public ?string $apiToken = null,

        #[ApiProperty(description: 'Endpoint URL; only used for OpenAI-compatible and Bedrock providers')]
        #[Assert\Length(min: 1, max: 2048)]
        public ?string $endpointUrl = null,

        #[ApiProperty(description: 'Id of the provider being edited, to reuse its stored token')]
        public ?int $providerId = null,
    ) {
    }
}
