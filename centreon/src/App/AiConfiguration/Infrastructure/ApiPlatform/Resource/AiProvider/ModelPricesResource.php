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
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\ModelPricesInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider\FetchModelPricesProcessor;

#[Post(
    shortName: 'AiProvider',
    uriTemplate: '/administration/ai-providers/model-prices',
    processor: FetchModelPricesProcessor::class,
    input: ModelPricesInput::class,
    status: 200,
    openapi: new Model\Operation(
        summary: 'Look up published model prices to prefill the form',
        description: 'Fetch the per-1M-token prices of the given models from the LiteLLM community '
            . 'price catalog (downloaded from github.com through the platform proxy). Prices are in '
            . 'USD; a model that is not found returns null prices. This is a convenience to prefill '
            . 'the form: the values stay editable.',
        responses: [
            403 => new Model\Response('You are not allowed to configure AI providers'),
            502 => new Model\Response('The price catalog could not be fetched'),
        ],
    ),
    security: "is_granted('" . AiProviderActionEnum::Update->value . "')",
    securityMessage: 'You are not allowed to configure AI providers',
)]
final readonly class ModelPricesResource
{
    public function __construct(
        #[ApiProperty(description: 'Default model price per 1M input tokens (USD), null when not found', writable: false)]
        public ?float $defaultModelInput,

        #[ApiProperty(description: 'Default model price per 1M output tokens (USD), null when not found', writable: false)]
        public ?float $defaultModelOutput,

        #[ApiProperty(description: 'Fast model price per 1M input tokens (USD), null when not found', writable: false)]
        public ?float $fastModelInput,

        #[ApiProperty(description: 'Fast model price per 1M output tokens (USD), null when not found', writable: false)]
        public ?float $fastModelOutput,
    ) {
    }
}
