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

namespace Tests\App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;

final class PricingTest extends TestCase
{
    public function testCostOfTheDefaultTier(): void
    {
        $pricing = new Pricing(new TierPricing(3.0, 15.0));

        // 1M input * 3 + 0.5M output * 15 = 3 + 7.5
        $cost = $pricing->costOf(LlmModelTierEnum::Default, new LlmUsage(1_000_000, 500_000));

        self::assertEqualsWithDelta(10.5, $cost, 0.0001);
    }

    public function testFastTierUsesItsOwnPrice(): void
    {
        $pricing = new Pricing(new TierPricing(3.0, 15.0), new TierPricing(1.0, 5.0));

        $cost = $pricing->costOf(LlmModelTierEnum::Fast, new LlmUsage(1_000_000, 1_000_000));

        self::assertEqualsWithDelta(6.0, $cost, 0.0001);
    }

    public function testFastTierFallsBackToTheDefaultPrice(): void
    {
        $pricing = new Pricing(new TierPricing(3.0, 15.0));

        $cost = $pricing->costOf(LlmModelTierEnum::Fast, new LlmUsage(1_000_000, 0));

        self::assertEqualsWithDelta(3.0, $cost, 0.0001);
    }
}
