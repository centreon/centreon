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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\AiAssistance\Domain\Security\AiAssistancePermissionEnum;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\ExplainGraphInput;
use App\AiAssistance\Infrastructure\ApiPlatform\State\ExplainGraphProcessor;

#[Post(
    security: "is_granted('" . AiAssistancePermissionEnum::CanUse->value . "')",
    shortName: 'AiAssistance',
    uriTemplate: '/ai/graph-explanation',
    processor: ExplainGraphProcessor::class,
    input: ExplainGraphInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Explain a performance graph',
        description: 'Produce a plain-text explanation of a performance graph (what the metrics '
            . 'show over the displayed window, whether it looks healthy, where it is heading, '
            . 'suggested next steps) from the metric summary displayed in the details panel. The '
            . 'explanation is produced by the enabled AI provider: the provided figures are sent '
            . 'to it.',
        responses: [
            403 => new Model\Response('The user has no access to the Resources Status page'),
            409 => new Model\Response('No AI provider is enabled, or its spend limit is reached'),
            502 => new Model\Response('The AI provider could not be reached or rejected the request, or its API token could not be read from the vault'),
        ],
    ),
)]
final readonly class ExplainGraphResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Plain-text explanation of the performance graph',
            writable: false,
        )]
        public string $explanation,

        #[ApiProperty(
            description: 'Model that produced the explanation',
            writable: false,
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        public string $model,
    ) {
    }
}
