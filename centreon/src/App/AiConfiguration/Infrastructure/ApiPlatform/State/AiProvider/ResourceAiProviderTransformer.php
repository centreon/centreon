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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\AiProviderResource;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<AiProvider, AiProviderResource>
 */
final readonly class ResourceAiProviderTransformer implements TransformerInterface
{
    public function transform(mixed $from): AiProviderResource
    {
        return new AiProviderResource(
            id: $from->id()->value,
            name: $from->name->value,
            type: $from->type->value,
            endpointUrl: $from->endpointUrl->value,
            defaultModel: $from->defaultModel->value,
            fastModel: $from->fastModel?->value,
            description: $from->description?->value,
            isActivated: $from->isActivated,
            apiToken: null, // the token is write-only and never exposed
            currency: $from->currency?->value,
            priceDefaultInput: $from->pricing?->default->inputPricePerMillion,
            priceDefaultOutput: $from->pricing?->default->outputPricePerMillion,
            priceFastInput: $from->pricing?->fast?->inputPricePerMillion,
            priceFastOutput: $from->pricing?->fast?->outputPricePerMillion,
            limitDaily: $from->limit?->daily,
            limitMonthly: $from->limit?->monthly,
        );
    }
}
