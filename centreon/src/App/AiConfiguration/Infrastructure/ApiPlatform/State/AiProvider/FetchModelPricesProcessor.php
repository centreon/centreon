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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AiConfiguration\Domain\Llm\ModelPriceCatalog;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\ModelPricesInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\ModelPricesResource;

/**
 * @implements ProcessorInterface<ModelPricesInput, ModelPricesResource>
 */
final readonly class FetchModelPricesProcessor implements ProcessorInterface
{
    public function __construct(
        private ModelPriceCatalog $catalog,
        private AiFeatureAvailability $availability,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ModelPricesResource
    {
        $this->availability->assertAvailable();

        $default = $this->catalog->priceFor($data->defaultModel);
        $fast = $data->fastModel !== null ? $this->catalog->priceFor($data->fastModel) : null;

        return new ModelPricesResource(
            defaultModelInput: $default?->inputPricePerMillion,
            defaultModelOutput: $default?->outputPricePerMillion,
            fastModelInput: $fast?->inputPricePerMillion,
            fastModelOutput: $fast?->outputPricePerMillion,
        );
    }
}
