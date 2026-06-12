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
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\SummarizeIncidentInput;
use App\AiAssistance\Infrastructure\ApiPlatform\State\SummarizeIncidentProcessor;

#[Post(
    shortName: 'AiAssistance',
    uriTemplate: '/ai/incident-summary',
    processor: SummarizeIncidentProcessor::class,
    input: SummarizeIncidentInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Summarize an incident from the resource timeline',
        description: 'Produce a plain-text incident summary (overview, timeline of significant '
            . 'moments, current state) from the current state of a resource and its recent '
            . 'timeline events, suitable for a ticket or a shift handover. The summary is '
            . 'produced by the default AI provider: the provided data is sent to it.',
        responses: [
            409 => new Model\Response('No default AI provider is configured'),
            502 => new Model\Response('The AI provider could not be reached or rejected the request'),
        ],
    ),
)]
final readonly class SummarizeIncidentResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Plain-text incident summary',
            writable: false,
        )]
        public string $summary,

        #[ApiProperty(
            description: 'Model that produced the summary',
            writable: false,
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        public string $model,
    ) {
    }
}
