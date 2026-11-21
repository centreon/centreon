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

use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;

final class BillingTest extends TestCase
{
    public function testNoBillingAtAll(): void
    {
        $billing = new Billing();

        self::assertNull($billing->costOf(LlmModelTierEnum::Default, new LlmUsage(1_000_000, 1_000_000)));
    }

    public function testPricesAndLimitInACurrency(): void
    {
        $billing = new Billing(
            new Currency('usd'),
            new Pricing(new TierPricing(3.0, 15.0)),
            new SpendLimit(daily: 10.0),
        );

        self::assertSame('USD', $billing->currency?->value);
        self::assertEqualsWithDelta(18.0, $billing->costOf(LlmModelTierEnum::Default, new LlmUsage(1_000_000, 1_000_000)), 0.0001);
    }

    public function testPricesRequireACurrency(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);
        $this->expectExceptionMessage('A currency is required when prices or spend limits are set.');

        new Billing(pricing: new Pricing(new TierPricing(3.0, 15.0)));
    }

    public function testALimitRequiresACurrency(): void
    {
        // without a currency, the cap would silently never apply
        $this->expectException(InvalidAiProviderConfigurationException::class);
        $this->expectExceptionMessage('A currency is required when prices or spend limits are set.');

        new Billing(pricing: new Pricing(new TierPricing(3.0, 15.0)), limit: new SpendLimit(monthly: 50.0));
    }

    public function testALimitRequiresPrices(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);
        $this->expectExceptionMessage('A spend limit requires the default tier prices to be set.');

        new Billing(currency: new Currency('USD'), limit: new SpendLimit(daily: 10.0));
    }

    public function testAFastPriceRequiresADefaultPrice(): void
    {
        $this->expectException(InvalidAiProviderConfigurationException::class);
        $this->expectExceptionMessage('The default tier prices are required when a fast tier price is set.');

        Billing::fromTierPrices(new Currency('USD'), null, new TierPricing(1.0, 5.0), null);
    }

    public function testFromTierPrices(): void
    {
        $billing = Billing::fromTierPrices(new Currency('EUR'), new TierPricing(3.0, 15.0), new TierPricing(1.0, 5.0), null);

        self::assertSame(1.0, $billing->pricing?->fast?->inputPricePerMillion);
        self::assertNull($billing->limit);
    }
}
