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

namespace Tests\App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderRepository;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderUsageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalAiProviderUsageRepositoryTest extends KernelTestCase
{
    private DbalAiProviderUsageRepository $repository;

    private DbalAiProviderRepository $providerRepository;

    protected function setUp(): void
    {
        /** @var DbalAiProviderUsageRepository $repository */
        $repository = self::getContainer()->get(DbalAiProviderUsageRepository::class);
        $this->repository = $repository;

        /** @var DbalAiProviderRepository $providerRepository */
        $providerRepository = self::getContainer()->get(DbalAiProviderRepository::class);
        $this->providerRepository = $providerRepository;
    }

    public function testAddAndSumIsScopedByProviderCurrencyAndWindow(): void
    {
        $providerId = $this->createProvider();

        // in window, right currency
        $this->repository->add($this->entry($providerId, cost: 1.5, currency: 'USD', requestedAt: 1000));
        $this->repository->add($this->entry($providerId, cost: 2.0, currency: 'USD', requestedAt: 1500));
        // before the window
        $this->repository->add($this->entry($providerId, cost: 5.0, currency: 'USD', requestedAt: 500));
        // other currency
        $this->repository->add($this->entry($providerId, cost: 9.0, currency: 'EUR', requestedAt: 1500));

        self::assertEqualsWithDelta(3.5, $this->repository->sumCostSince($providerId, 'USD', 1000), 0.0001);
        self::assertEqualsWithDelta(9.0, $this->repository->sumCostSince($providerId, 'EUR', 1000), 0.0001);
    }

    public function testSumIgnoresNullCost(): void
    {
        $providerId = $this->createProvider();

        $this->repository->add($this->entry($providerId, cost: null, currency: null, requestedAt: 1500));

        self::assertEqualsWithDelta(0.0, $this->repository->sumCostSince($providerId, 'USD', 1000), 0.0001);
    }

    public function testDeleteOlderThan(): void
    {
        $providerId = $this->createProvider();

        $this->repository->add($this->entry($providerId, cost: 1.0, currency: 'USD', requestedAt: 500));
        $this->repository->add($this->entry($providerId, cost: 1.0, currency: 'USD', requestedAt: 1500));

        $deleted = $this->repository->deleteOlderThan(1000);

        self::assertSame(1, $deleted);
        self::assertEqualsWithDelta(1.0, $this->repository->sumCostSince($providerId, 'USD', 0), 0.0001);
    }

    private function createProvider(): int
    {
        $provider = new AiProvider(
            id: null,
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new ApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
        );
        $this->providerRepository->add($provider);

        return $provider->id()->value;
    }

    private function entry(int $providerId, ?float $cost, ?string $currency, int $requestedAt): UsageLogEntry
    {
        return new UsageLogEntry(
            providerId: $providerId,
            providerName: 'Anthropic production',
            feature: AiFeatureEnum::ResourceExplanation,
            contactId: null,
            model: 'claude-sonnet-4-6',
            tier: LlmModelTierEnum::Default,
            inputTokens: 100,
            outputTokens: 50,
            cost: $cost,
            currency: $currency,
            requestedAt: $requestedAt,
        );
    }
}
