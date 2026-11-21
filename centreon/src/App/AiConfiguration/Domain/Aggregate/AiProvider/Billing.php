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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;

/**
 * What the calls to a provider cost, and how much may be spent. Prices and
 * limits are expressed in the currency, so both require it; a limit is a cost
 * cap, so it requires the prices to compute that cost.
 */
final readonly class Billing
{
    /**
     * @throws InvalidAiProviderConfigurationException when the fields do not go together
     */
    public function __construct(
        public ?Currency $currency = null,
        public ?Pricing $pricing = null,
        public ?SpendLimit $limit = null,
    ) {
        $hasLimit = $limit instanceof SpendLimit && $limit->hasAnyLimit();
        if ($hasLimit && ! $pricing instanceof Pricing) {
            throw InvalidAiProviderConfigurationException::limitRequiresPrices();
        }

        if (($hasLimit || $pricing instanceof Pricing) && ! $currency instanceof Currency) {
            throw InvalidAiProviderConfigurationException::currencyRequired();
        }
    }

    /**
     * @throws InvalidAiProviderConfigurationException when the fields do not go together
     */
    public static function fromTierPrices(
        ?Currency $currency,
        ?TierPricing $defaultPrice,
        ?TierPricing $fastPrice,
        ?SpendLimit $limit,
    ): self {
        // the fast tier price only refines the default one
        if ($fastPrice instanceof TierPricing && ! $defaultPrice instanceof TierPricing) {
            throw InvalidAiProviderConfigurationException::fastPriceRequiresDefaultPrice();
        }

        return new self(
            currency: $currency,
            pricing: $defaultPrice instanceof TierPricing ? new Pricing($defaultPrice, $fastPrice) : null,
            limit: $limit,
        );
    }

    /**
     * @return float|null the cost in the currency, or null when no prices are set
     */
    public function costOf(LlmModelTierEnum $tier, LlmUsage $usage): ?float
    {
        return $this->pricing?->costOf($tier, $usage);
    }
}
