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

namespace Tests\App\AiConfiguration\Domain\Usage;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Usage\UsageSummaryProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;

final class UsageSummaryProviderTest extends TestCase
{
    public function testReturnsTheCurrencyScopedSpend(): void
    {
        $provider = new UsageSummaryProvider(
            new FakeAiProviderUsageRepository(windowSum: 12.5),
            new MockClock()
        );

        $summary = $provider->forProvider($this->aiProvider(new Currency('USD')));

        self::assertEqualsWithDelta(12.5, $summary->usedDaily, 0.0001);
        self::assertEqualsWithDelta(12.5, $summary->usedMonthly, 0.0001);
    }

    public function testNoUsageWithoutCurrency(): void
    {
        $provider = new UsageSummaryProvider(
            new FakeAiProviderUsageRepository(windowSum: 99.0),
            new MockClock()
        );

        $summary = $provider->forProvider($this->aiProvider(null));

        self::assertNull($summary->usedDaily);
        self::assertNull($summary->usedMonthly);
    }

    private function aiProvider(?Currency $currency): AiProvider
    {
        return new AiProvider(
            id: new AiProviderId(1),
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new ApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
            currency: $currency,
        );
    }
}
