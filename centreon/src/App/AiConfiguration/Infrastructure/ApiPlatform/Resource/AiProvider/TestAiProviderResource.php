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
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\TestAiProviderInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\TestAiProviderProcessor;

#[Post(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers/{id}/test',
    processor: TestAiProviderProcessor::class,
    input: TestAiProviderInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Test an AI provider configuration',
        description: 'Send a minimal completion request to the provider to verify the endpoint, '
            . 'the API token and the model. The outcome of the test is reported in the response '
            . 'body: a failing provider does not make the request itself fail.',
        responses: [
            404 => new Model\Response('AI provider configuration not found'),
            403 => new Model\Response('You are not allowed to test AI provider configurations'),
        ],
    ),
    securityPostValidation: "is_granted('" . AiProviderActionEnum::Update->value . "', object)",
    securityPostValidationMessage: 'You are not allowed to test AI provider configurations',
)]
final readonly class TestAiProviderResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Whether the test request succeeded',
            writable: false,
        )]
        public bool $success,

        #[ApiProperty(
            description: 'Error message when the test failed',
            writable: false,
        )]
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
