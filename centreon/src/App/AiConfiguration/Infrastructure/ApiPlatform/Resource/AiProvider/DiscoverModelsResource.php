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
use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\DiscoverModelsInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\DiscoverModelsProcessor;

#[Post(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers/discover-models',
    processor: DiscoverModelsProcessor::class,
    input: DiscoverModelsInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'List the models a provider exposes, before saving it',
        description: 'Query the provider for the models it exposes, using the configuration entered '
            . 'in the form (type, endpoint and token), without saving it. The typed token is used; '
            . 'when omitted while editing, the stored token of the given provider is used. Same '
            . 'trust level as the test-connection endpoint.',
        responses: [
            403 => new Model\Response('You are not allowed to configure AI providers'),
            422 => new Model\Response('An endpoint URL is required for this provider type'),
            502 => new Model\Response('The provider could not be reached'),
        ],
    ),
    security: "is_granted('" . AiProviderActionEnum::Update->value . "')",
    securityMessage: 'You are not allowed to configure AI providers',
)]
final readonly class DiscoverModelsResource
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        #[ApiProperty(
            description: 'Model identifiers the provider exposes',
            writable: false,
            openapiContext: ['example' => ['claude-haiku-4-5', 'claude-sonnet-4-6']],
        )]
        public array $models,
    ) {
    }
}
