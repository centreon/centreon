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
 * Computes a provider's spend in the current daily and monthly windows, in its
 * currency. Used by the listing to show usage; the budget cap reuses the same
 * currency-scoped window sums via the repository.
 */
final readonly class UsageSummaryProvider
{
    public function __construct(
        private AiProviderUsageRepository $usageRepository,
        private ClockInterface $clock,
    ) {
    }

    public function forProvider(AiProvider $aiProvider): UsageSummary
    {
        $currency = $aiProvider->currency;
        $id = $aiProvider->id();

        if ($currency === null) {
            return new UsageSummary();
        }

        $now = $this->clock->now();

        return new UsageSummary(
            usedDaily: $this->usageRepository->sumCostSince(
                $id->value,
                $currency->value,
                UsageWindow::dayStart($now)
            ),
            usedMonthly: $this->usageRepository->sumCostSince(
                $id->value,
                $currency->value,
                UsageWindow::monthStart($now)
            ),
        );
    }
}
