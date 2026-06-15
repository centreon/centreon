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
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\CorrelateAlertsInput;
use App\AiAssistance\Infrastructure\ApiPlatform\State\CorrelateAlertsProcessor;

#[Post(
    shortName: 'AiAssistance',
    uriTemplate: '/ai/alert-correlation',
    processor: CorrelateAlertsProcessor::class,
    input: CorrelateAlertsInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Correlate several alerts to surface a likely common cause',
        description: 'Analyze a set of non-OK resources selected in the listing and produce a '
            . 'Markdown analysis (likely root cause, grouping, suggested next steps) instead of '
            . 'treating each alert in isolation. The facts of the selected resources are sent to '
            . 'the default AI provider.',
        responses: [
            409 => new Model\Response('No default AI provider is configured'),
            502 => new Model\Response('The AI provider could not be reached or rejected the request'),
        ],
    ),
)]
final readonly class CorrelateAlertsResource
{
    public function __construct(
        #[ApiProperty(description: 'Markdown analysis of the selected alerts', writable: false)]
        public string $analysis,

        #[ApiProperty(
            description: 'Model that produced the analysis',
            writable: false,
            openapiContext: ['example' => 'claude-sonnet-4-6'],
        )]
        public string $model,
    ) {
    }
}
