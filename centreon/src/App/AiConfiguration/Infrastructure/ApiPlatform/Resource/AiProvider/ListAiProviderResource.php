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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\ListAiProvidersProvider;

#[GetCollection(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers',
    provider: ListAiProvidersProvider::class,
    openapi: new Model\Operation(
        summary: 'List the AI provider configurations',
        responses: [
            403 => new Model\Response('You are not allowed to access AI provider configurations'),
        ],
        parameters: [
            new Model\Parameter(
                name: 'name',
                in: 'query',
                description: 'Filter by name, e.g. ?name[lk]=anthropic (partial match) or ?name[eq]=Anthropic production (exact match)',
                required: false,
                schema: ['type' => 'object'],
            ),
            new Model\Parameter(
                name: 'type',
                in: 'query',
                description: 'Filter by provider type. Multiple values can be provided using type[]=anthropic&type[]=mistral',
                required: false,
                schema: [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => [
                            AiProviderTypeEnum::Anthropic->value,
                            AiProviderTypeEnum::OpenAi->value,
                            AiProviderTypeEnum::Mistral->value,
                            AiProviderTypeEnum::OpenAiCompatible->value,
                        ],
                    ],
                ],
            ),
            new Model\Parameter(
                name: 'is_activated',
                in: 'query',
                description: 'Filter by enabled state',
                required: false,
                schema: ['type' => 'boolean'],
            ),
            new Model\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Sort by field and direction, e.g. ?sort[name]=ASC',
                required: false,
                schema: ['type' => 'object'],
            ),
        ],
    ),
)]
final class ListAiProviderResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        #[ApiProperty(
            description: 'Name of the AI provider configuration',
            openapiContext: ['example' => 'Anthropic production'],
        )]
        public string $name,

        #[ApiProperty(
            description: 'Type of the AI provider',
            openapiContext: [
                'example' => AiProviderTypeEnum::Anthropic->value,
                'enum' => [
                    AiProviderTypeEnum::Anthropic->value,
                    AiProviderTypeEnum::OpenAi->value,
                    AiProviderTypeEnum::Mistral->value,
                    AiProviderTypeEnum::OpenAiCompatible->value,
                ],
            ],
        )]
        public string $type,

        #[ApiProperty(
            description: 'Base URL of the AI provider API',
            openapiContext: ['example' => 'https://api.anthropic.com'],
        )]
        public string $endpointUrl,

        #[ApiProperty(
            description: 'Model used by AI features for this provider',
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        public string $defaultModel,

        #[ApiProperty(
            description: 'Model used by AI features requesting the fast tier',
            openapiContext: ['example' => 'claude-haiku-4-5'],
        )]
        public ?string $fastModel,

        #[ApiProperty(
            description: 'Description of the AI provider configuration',
        )]
        public ?string $description,

        #[ApiProperty(
            description: 'Whether this provider is enabled (the enabled provider is the one used by AI features)',
        )]
        public bool $isActivated,

        #[ApiProperty(description: 'ISO 4217 currency of the prices and spend limits')]
        public ?string $currency = null,

        #[ApiProperty(description: 'Daily spend limit in the configured currency')]
        public ?float $limitDaily = null,

        #[ApiProperty(description: 'Monthly spend limit in the configured currency')]
        public ?float $limitMonthly = null,

        #[ApiProperty(description: 'Spend in the current day window, in the configured currency')]
        public ?float $usedDaily = null,

        #[ApiProperty(description: 'Spend in the current month window, in the configured currency')]
        public ?float $usedMonthly = null,
    ) {
    }
}
