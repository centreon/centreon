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
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;

final class BudgetStatusCheckerTest extends TestCase
{
    public function testNotExhaustedWithoutLimit(): void
    {
        $checker = new BudgetStatusChecker(new FakeAiProviderUsageRepository(windowSum: 1000.0), new MockClock());

        self::assertFalse($checker->isExhausted($this->provider(limit: null)));
    }

    public function testNotExhaustedBelowLimit(): void
    {
        $checker = new BudgetStatusChecker(new FakeAiProviderUsageRepository(windowSum: 5.0), new MockClock());

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0))));
    }

    public function testExhaustedAtLimit(): void
    {
        $checker = new BudgetStatusChecker(new FakeAiProviderUsageRepository(windowSum: 10.0), new MockClock());

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0))));
    }

    public function testNotExhaustedWithoutCurrency(): void
    {
        $checker = new BudgetStatusChecker(new FakeAiProviderUsageRepository(windowSum: 100.0), new MockClock());

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(monthly: 10.0), currency: null)));
    }

    private function provider(?SpendLimit $limit, ?Currency $currency = new Currency('USD')): AiProvider
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
            pricing: null,
            limit: $limit,
        );
    }
}
