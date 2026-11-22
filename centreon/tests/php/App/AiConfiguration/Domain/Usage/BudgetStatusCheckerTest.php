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
use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;

final class BudgetStatusCheckerTest extends TestCase
{
    private const PROVIDER_ID = 1;
    private const NOW = '2026-03-15 12:00:00';
    private const TODAY = '2026-03-15 08:00:00';
    private const EARLIER_THIS_MONTH = '2026-03-02 10:00:00';
    private const LAST_MONTH = '2026-02-28 22:00:00';

    public function testNotExhaustedWithoutLimit(): void
    {
        $checker = $this->checker([$this->spend(1000.0, self::TODAY)]);

        self::assertFalse($checker->isExhausted($this->provider(limit: null)));
    }

    public function testNotExhaustedBelowLimit(): void
    {
        $checker = $this->checker([$this->spend(5.0, self::TODAY)]);

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0))));
    }

    public function testExhaustedAtLimit(): void
    {
        $checker = $this->checker([$this->spend(10.0, self::TODAY)]);

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0))));
    }

    public function testExhaustedAtMonthlyLimit(): void
    {
        $checker = $this->checker([$this->spend(100.0, self::EARLIER_THIS_MONTH)]);

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(monthly: 10.0))));
    }

    public function testExhaustedWhenTheMonthlyLimitIsReachedWhileTheDailyOneIsNot(): void
    {
        $checker = $this->checker([
            $this->spend(2.0, self::TODAY),
            $this->spend(48.0, self::EARLIER_THIS_MONTH),
        ]);

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0, monthly: 50.0))));
    }

    public function testExhaustedWhenTheDailyLimitIsReachedWhileTheMonthlyOneIsNot(): void
    {
        $checker = $this->checker([$this->spend(10.0, self::TODAY)]);

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0, monthly: 100.0))));
    }

    public function testSpendBeforeTodayDoesNotCountTowardsTheDailyLimit(): void
    {
        $checker = $this->checker([$this->spend(30.0, self::EARLIER_THIS_MONTH)]);

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0, monthly: 100.0))));
    }

    public function testSpendOfLastMonthDoesNotCountTowardsTheMonthlyLimit(): void
    {
        $checker = $this->checker([$this->spend(500.0, self::LAST_MONTH)]);

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(monthly: 100.0))));
    }

    public function testSpendOfAnotherProviderIsNotCounted(): void
    {
        $checker = $this->checker([
            FakeAiProviderUsageRepository::spend(2, 'USD', 500.0, $this->timestamp(self::TODAY)),
        ]);

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0, monthly: 50.0))));
    }

    public function testSpendInAnotherCurrencyIsNotCounted(): void
    {
        $checker = $this->checker([
            FakeAiProviderUsageRepository::spend(self::PROVIDER_ID, 'EUR', 500.0, $this->timestamp(self::TODAY)),
        ]);

        self::assertFalse($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0, monthly: 50.0))));
    }

    public function testTheWindowsAreInUtc(): void
    {
        // 00:30 on March 1st in Paris is still February 28th in UTC: the spend of
        // earlier that UTC day and month still counts
        $checker = new BudgetStatusChecker(
            new FakeAiProviderUsageRepository([$this->spend(10.0, '2026-02-28 22:00:00')]),
            new MockClock('2026-03-01 00:30:00', 'Europe/Paris'),
        );

        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(daily: 10.0))));
        self::assertTrue($checker->isExhausted($this->provider(limit: new SpendLimit(monthly: 10.0))));
    }

    /**
     * @param list<UsageLogEntry> $history
     */
    private function checker(array $history): BudgetStatusChecker
    {
        return new BudgetStatusChecker(
            new FakeAiProviderUsageRepository($history),
            new MockClock(self::NOW, 'UTC'),
        );
    }

    private function spend(float $cost, string $utcDateTime): UsageLogEntry
    {
        return FakeAiProviderUsageRepository::spend(self::PROVIDER_ID, 'USD', $cost, $this->timestamp($utcDateTime));
    }

    private function timestamp(string $utcDateTime): int
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))->getTimestamp();
    }

    private function provider(?SpendLimit $limit): AiProvider
    {
        return new AiProvider(
            id: new AiProviderId(self::PROVIDER_ID),
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
            billing: new Billing(
                currency: new Currency('USD'),
                pricing: new Pricing(new TierPricing(3.0, 15.0)),
                limit: $limit,
            ),
        );
    }
}
