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

use App\AiConfiguration\Domain\Usage\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;

final class FakeAiProviderUsageRepository implements AiProviderUsageRepository
{
    /** @var list<UsageLogEntry> */
    public array $added = [];

    public ?UsageLogEntry $lastAdded = null;

    public function __construct(
        private float $windowSum = 0.0,
    ) {
    }

    public function add(UsageLogEntry $entry): void
    {
        $this->added[] = $entry;
        $this->lastAdded = $entry;
    }

    public function sumCostSince(int $providerId, string $currency, int $sinceTimestamp): float
    {
        return $this->windowSum;
    }

    public function deleteOlderThan(int $timestamp): int
    {
        return 0;
    }
}
