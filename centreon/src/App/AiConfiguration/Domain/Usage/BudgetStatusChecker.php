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

namespace App\AiConfiguration\Domain\Usage;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use Psr\Clock\ClockInterface;

/**
 * Tells whether a provider has reached one of its spend limits in the current
 * daily or monthly window. The window sum is currency-scoped, so a provider
 * currency change cleanly leaves out the rows logged in another currency.
 */
final readonly class BudgetStatusChecker
{
    public function __construct(
        private AiProviderUsageRepository $usageRepository,
        private ClockInterface $clock,
    ) {
    }

    public function isExhausted(AiProvider $aiProvider): bool
    {
        $limit = $aiProvider->limit;
        $currency = $aiProvider->currency;

        if ($currency === null || $limit === null || ! $limit->hasAnyLimit()) {
            return false;
        }

        $id = $aiProvider->id();

        $now = $this->clock->now();

        if ($limit->daily !== null) {
            $dayStart = UsageWindow::dayStart($now);
            if ($this->usageRepository->sumCostSince($id->value, $currency->value, $dayStart) >= $limit->daily) {
                return true;
            }
        }

        if ($limit->monthly !== null) {
            $monthStart = UsageWindow::monthStart($now);
            if ($this->usageRepository->sumCostSince($id->value, $currency->value, $monthStart) >= $limit->monthly) {
                return true;
            }
        }

        return false;
    }
}
