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

use App\AiConfiguration\Domain\Llm\LlmUsage;
use Webmozart\Assert\Assert;

/**
 * Token prices of one model tier, expressed per million tokens in the
 * provider's currency.
 */
final readonly class TierPricing
{
    public function __construct(
        public float $inputPricePerMillion,
        public float $outputPricePerMillion,
    ) {
        Assert::greaterThanEq($inputPricePerMillion, 0, 'The input price can not be negative.');
        Assert::greaterThanEq($outputPricePerMillion, 0, 'The output price can not be negative.');
    }

    public function costOf(LlmUsage $usage): float
    {
        return $usage->inputTokens / 1_000_000 * $this->inputPricePerMillion
            + $usage->outputTokens / 1_000_000 * $this->outputPricePerMillion;
    }
}
