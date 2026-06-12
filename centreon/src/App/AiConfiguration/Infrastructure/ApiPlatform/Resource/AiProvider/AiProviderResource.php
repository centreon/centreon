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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\DeleteAiProviderProcessor;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\FindAiProviderProvider;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\UpdateAiProviderProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AiProvider',
    operations: [
        new Get(
            uriTemplate: '/administration/ai-providers/{id}',
            provider: FindAiProviderProvider::class,
            openapi: new Model\Operation(
                summary: 'Get an AI provider configuration',
                responses: [
                    404 => new Model\Response('AI provider configuration not found'),
                    403 => new Model\Response('You are not allowed to access AI provider configurations'),
                ],
            ),
            securityPostValidation: "is_granted('" . AiProviderActionEnum::Read->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to access AI provider configurations',
        ),
        new Patch(
            uriTemplate: '/administration/ai-providers/{id}',
            provider: FindAiProviderProvider::class,
            processor: UpdateAiProviderProcessor::class,
            openapi: new Model\Operation(
                summary: 'Update an AI provider configuration',
                description: 'Update an AI provider configuration. The provider type can not be changed. '
                    . 'Omitting the api_token field keeps the current token.',
                responses: [
                    404 => new Model\Response('AI provider configuration not found'),
                    403 => new Model\Response('You are not allowed to update AI provider configurations'),
                    409 => new Model\Response('An AI provider configuration with this name already exists'),
                    422 => new Model\Response('The default AI provider configuration can not be unset'),
                ],
            ),
            securityPostValidation: "is_granted('" . AiProviderActionEnum::Update->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to update AI provider configurations',
        ),
        new Delete(
            uriTemplate: '/administration/ai-providers/{id}',
            provider: FindAiProviderProvider::class,
            processor: DeleteAiProviderProcessor::class,
            openapi: new Model\Operation(
                summary: 'Delete an AI provider configuration',
                responses: [
                    404 => new Model\Response('AI provider configuration not found'),
                    403 => new Model\Response('You are not allowed to delete AI provider configurations'),
                    409 => new Model\Response('The default AI provider configuration can not be deleted while other configurations exist'),
                ],
            ),
            securityPostValidation: "is_granted('" . AiProviderActionEnum::Delete->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to delete AI provider configurations',
        ),
    ],
)]
final class AiProviderResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        #[ApiProperty(
            description: 'Name of the AI provider configuration',
            openapiContext: ['example' => 'Anthropic production'],
        )]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[ApiProperty(
            description: 'Type of the AI provider. The type can not be changed after creation.',
            writable: false,
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
        #[Assert\Length(min: 1, max: 2048)]
        #[Assert\Url(requireTld: false)]
        public string $endpointUrl,

        #[ApiProperty(
            description: 'Default model used by AI features for this provider',
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        #[Assert\Length(min: 1, max: 255)]
        public ?string $defaultModel,

        #[ApiProperty(
            description: 'Description of the AI provider configuration',
        )]
        #[Assert\Length(min: 1, max: 65535)]
        public ?string $description,

        #[ApiProperty(
            description: 'Whether this configuration is the default one used by AI features. '
                . 'The default flag can only be moved by setting it on another configuration.',
        )]
        public bool $isDefault,

        #[ApiProperty(
            description: 'API token used to authenticate against the AI provider. '
                . 'This field is write-only: it is never returned by the API. '
                . 'Omit it to keep the current token.',
            readable: false,
            openapiContext: ['example' => 'sk-...'],
        )]
        #[Assert\Length(min: 1, max: 4096)]
        public ?string $apiToken = null,
    ) {
    }
}
