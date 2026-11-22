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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Repository\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;

/**
 * In-memory usage log: sums filter on provider, currency and timestamp like
 * the SQL query, so a wrong window or a missing filter shows in the tests.
 */
final class FakeAiProviderUsageRepository implements AiProviderUsageRepository
{
    /** @var list<UsageLogEntry> entries added through add() only */
    public array $added = [];

    public ?UsageLogEntry $lastAdded = null;

    /**
     * @param list<UsageLogEntry> $history entries logged before the test
     */
    public function __construct(
        private readonly array $history = [],
    ) {
    }

    /**
     * A logged call costing $cost, for seeding the history.
     */
    public static function spend(int $providerId, string $currency, float $cost, int $requestedAt): UsageLogEntry
    {
        return new UsageLogEntry(
            providerId: $providerId,
            providerName: 'provider ' . $providerId,
            feature: AiFeatureEnum::ResourceExplanation,
            contactId: null,
            model: 'a-model',
            tier: LlmModelTierEnum::Default,
            inputTokens: 100,
            outputTokens: 10,
            cost: $cost,
            currency: $currency,
            requestedAt: $requestedAt,
            durationMs: 50,
        );
    }

    public function add(UsageLogEntry $entry): void
    {
        $this->added[] = $entry;
        $this->lastAdded = $entry;
    }

    public function sumCostSince(int $providerId, string $currency, int $sinceTimestamp): float
    {
        $sum = 0.0;
        foreach ([...$this->history, ...$this->added] as $entry) {
            if ($entry->providerId === $providerId
                && $entry->currency === $currency
                && $entry->requestedAt >= $sinceTimestamp
            ) {
                $sum += $entry->cost ?? 0.0;
            }
        }

        return $sum;
    }
}
