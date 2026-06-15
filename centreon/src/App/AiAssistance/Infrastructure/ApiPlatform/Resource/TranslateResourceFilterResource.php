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
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\TranslateResourceFilterInput;
use App\AiAssistance\Infrastructure\ApiPlatform\State\TranslateResourceFilterProcessor;

#[Post(
    shortName: 'AiAssistance',
    uriTemplate: '/ai/resource-filter',
    processor: TranslateResourceFilterProcessor::class,
    input: TranslateResourceFilterInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Route a natural-language request on the Resources Status page',
        description: 'Classify a natural-language request as a "filter" (a description to turn into '
            . 'a search expression) or an "explain" (a question to analyse), and resolve the '
            . 'resource set an explain is about (the displayed set, or a derived filter). Produced '
            . 'by the default AI provider.',
        responses: [
            409 => new Model\Response('No default AI provider is configured'),
            502 => new Model\Response('The AI provider could not be reached or rejected the request'),
        ],
    ),
)]
final readonly class TranslateResourceFilterResource
{
    public function __construct(
        #[ApiProperty(
            description: 'What the user wants: "filter" (list a set) or "explain" (analyse)',
            writable: false,
            openapiContext: ['example' => 'filter'],
        )]
        public string $intent,

        #[ApiProperty(
            description: 'Search expression in the Resources Status query language. For a filter '
                . 'intent, the filter to apply (empty when none could be derived); for an explain '
                . 'intent with scope "query", the set to explain',
            writable: false,
            openapiContext: ['example' => 'type:service status:critical state:unhandled'],
        )]
        public string $query,

        #[ApiProperty(
            description: 'For an explain intent: "current" (the displayed set) or "query". Null for '
                . 'a filter intent',
            writable: false,
            openapiContext: ['example' => 'current'],
        )]
        public ?string $scope = null,
    ) {
    }
}
