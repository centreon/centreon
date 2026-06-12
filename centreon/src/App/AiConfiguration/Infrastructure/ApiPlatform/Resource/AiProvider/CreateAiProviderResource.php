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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\CreateAiProviderProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers',
    processor: CreateAiProviderProcessor::class,
    openapi: new Model\Operation(
        summary: 'Create an AI provider configuration',
        description: 'Create an AI provider configuration. The first configuration is automatically '
            . 'enabled, and at most one provider is enabled at a time: creating an enabled provider '
            . 'disables the currently enabled one. The endpoint URL is only used for OpenAI-compatible '
            . 'providers, where it is required.',
        responses: [
            409 => new Model\Response('An AI provider configuration with this name already exists'),
            403 => new Model\Response('You are not allowed to create AI provider configurations'),
            422 => new Model\Response('An endpoint URL is required for this provider type'),
        ],
    ),
    securityPostValidation: "is_granted('" . AiProviderActionEnum::Create->value . "', object)",
    securityPostValidationMessage: 'You are not allowed to create AI provider configurations',
)]
final class CreateAiProviderResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Name of the AI provider configuration',
            openapiContext: ['example' => 'Anthropic production'],
        )]
        #[Assert\Length(min: 1, max: 255)]
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
        #[Assert\Choice(
            choices: [
                AiProviderTypeEnum::Anthropic->value,
                AiProviderTypeEnum::OpenAi->value,
                AiProviderTypeEnum::Mistral->value,
                AiProviderTypeEnum::OpenAiCompatible->value,
            ]
        )]
        public string $type,

        #[ApiProperty(
            description: 'API token used to authenticate against the AI provider. '
                . 'This field is write-only: it is never returned by the API.',
            openapiContext: ['example' => 'sk-...'],
        )]
        #[Assert\Length(min: 1, max: 4096)]
        public string $apiToken,

        #[ApiProperty(
            description: 'Model used by AI features for this provider',
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        #[Assert\Length(min: 1, max: 255)]
        public string $defaultModel,

        #[ApiProperty(
            description: 'Base URL of the AI provider API. Only used for OpenAI-compatible providers, '
                . 'where it is required; for the other provider types the official endpoint is '
                . 'enforced and any provided value is ignored.',
            openapiContext: ['example' => 'https://api.anthropic.com'],
        )]
        #[Assert\Length(min: 1, max: 2048)]
        #[Assert\Url(requireTld: false)]
        public ?string $endpointUrl = null,

        #[ApiProperty(
            description: 'Model used by AI features requesting the fast tier. '
                . 'Defaults to the default model when not set.',
            openapiContext: ['example' => 'claude-haiku-4-5'],
        )]
        #[Assert\Length(min: 1, max: 255)]
        public ?string $fastModel = null,

        #[ApiProperty(
            description: 'Description of the AI provider configuration',
        )]
        #[Assert\Length(min: 1, max: 65535)]
        public ?string $description = null,

        #[ApiProperty(
            description: 'Whether this provider is enabled on creation. At most one provider is '
                . 'enabled at a time: enabling this one disables the currently enabled one. The '
                . 'first created configuration is always enabled.',
        )]
        public bool $isActivated = false,
    ) {
    }
}
