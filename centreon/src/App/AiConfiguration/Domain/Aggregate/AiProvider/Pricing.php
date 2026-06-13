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

use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;

/**
 * Per-tier token prices. The fast tier falls back to the default tier price
 * when it has none of its own, mirroring the model-tier fallback.
 */
final readonly class Pricing
{
    public function __construct(
        public TierPricing $default,
        public ?TierPricing $fast = null,
    ) {
    }

    public function forTier(LlmModelTierEnum $tier): TierPricing
    {
        return $tier === LlmModelTierEnum::Fast
            ? ($this->fast ?? $this->default)
            : $this->default;
    }

    public function costOf(LlmModelTierEnum $tier, LlmUsage $usage): float
    {
        return $this->forTier($tier)->costOf($usage);
    }
}
