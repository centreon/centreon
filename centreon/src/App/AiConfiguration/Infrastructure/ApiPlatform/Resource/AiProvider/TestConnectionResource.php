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
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\TestConnectionInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\TestConnectionProcessor;

#[Post(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers/test-connection',
    processor: TestConnectionProcessor::class,
    input: TestConnectionInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Test an AI provider configuration before saving it',
        description: 'Send a minimal completion request to verify the configuration entered in the '
            . 'form (endpoint, token and model), without saving it. The typed token is used; when '
            . 'omitted while editing, the stored token of the given provider is used. The outcome '
            . 'is reported in the response body. The provider still bills this minimal request, '
            . 'but it is not metered against the configured spend limit.',
        responses: [
            403 => new Model\Response('You are not allowed to test AI provider configurations'),
            422 => new Model\Response('An endpoint URL is required for this provider type'),
        ],
    ),
    security: "is_granted('" . AiProviderActionEnum::Update->value . "')",
    securityMessage: 'You are not allowed to test AI provider configurations',
)]
final readonly class TestConnectionResource
{
    public function __construct(
        #[ApiProperty(description: 'Whether the test request succeeded', writable: false)]
        public bool $success,

        #[ApiProperty(description: 'Error message when the test failed', writable: false)]
        public ?string $message,

        #[ApiProperty(
            description: 'Model that answered the test request',
            writable: false,
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        public ?string $model,
    ) {
    }
}
