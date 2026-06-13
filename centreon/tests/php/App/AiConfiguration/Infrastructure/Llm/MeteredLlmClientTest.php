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

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Infrastructure\Llm\MeteredLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;

final class MeteredLlmClientTest extends TestCase
{
    public function testRecordsUsageWithCostAfterASuccessfulCall(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(pricing: new Pricing(new TierPricing(3.0, 15.0)), currency: new Currency('USD')),
            $usageRepository,
            new FakeLlmClient(new LlmResponse('hi', 'claude-sonnet-4-6', new LlmUsage(1_000_000, 1_000_000))),
        );

        $client->complete($this->request());

        $entry = $usageRepository->lastAdded;
        self::assertNotNull($entry);
        self::assertSame(AiFeatureEnum::ResourceExplanation, $entry->feature);
        self::assertSame('claude-sonnet-4-6', $entry->model);
        self::assertSame(1_000_000, $entry->inputTokens);
        self::assertSame('USD', $entry->currency);
        self::assertEqualsWithDelta(18.0, $entry->cost, 0.0001);
        self::assertNull($entry->contactId);
    }

    public function testRecordsWithoutCostWhenNoPricing(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered($this->provider(), $usageRepository, new FakeLlmClient());

        $client->complete($this->request());

        self::assertNull($usageRepository->lastAdded?->cost);
        self::assertNull($usageRepository->lastAdded?->currency);
    }

    public function testRefusesAndDoesNotRecordWhenBudgetExhausted(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository(windowSum: 50.0);
        $inner = new FakeLlmClient();
        $client = $this->metered(
            $this->provider(currency: new Currency('USD'), limit: new SpendLimit(daily: 10.0)),
            $usageRepository,
            $inner,
        );

        try {
            $client->complete($this->request());
            self::fail('Expected a budget exception');
        } catch (LlmConfigurationException) {
            // expected
        }

        self::assertNull($inner->lastRequest, 'the inner client must not be called when over budget');
        self::assertSame([], $usageRepository->added);
    }

    private function metered(
        AiProvider $provider,
        FakeAiProviderUsageRepository $usageRepository,
        FakeLlmClient $inner,
    ): MeteredLlmClient {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        return new MeteredLlmClient(
            $inner,
            $provider,
            $usageRepository,
            new BudgetStatusChecker($usageRepository, new MockClock()),
            $security,
            new MockClock(),
        );
    }

    private function request(): LlmRequest
    {
        return new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'hi')],
            feature: AiFeatureEnum::ResourceExplanation,
        );
    }

    private function provider(
        ?Pricing $pricing = null,
        ?Currency $currency = null,
        ?SpendLimit $limit = null,
    ): AiProvider {
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
            pricing: $pricing,
            limit: $limit,
        );
    }
}
