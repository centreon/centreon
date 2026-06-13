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

namespace App\AiConfiguration\Domain\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;

/**
 * Looks up published token prices for a model, to help prefill the provider
 * configuration. Prices are in USD.
 */
interface ModelPriceCatalog
{
    /**
     * @param AiProviderTypeEnum $type used to resolve the catalog's provider-prefixed keys
     *
     * @return TierPricing|null the per-1M-token prices, or null when the model
     *                          is not found in the catalog
     */
    public function priceFor(string $model, AiProviderTypeEnum $type): ?TierPricing;
}
