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

interface AiProviderUsageRepository
{
    public function add(UsageLogEntry $entry): void;

    /**
     * Sum the cost of a provider's usage in a currency since a timestamp.
     *
     * The currency filter is what keeps a provider currency change from mixing
     * units: only rows in the given currency are summed.
     */
    public function sumCostSince(int $providerId, string $currency, int $sinceTimestamp): float;

    /**
     * Delete usage rows older than a timestamp. Returns the number of deleted
     * rows.
     */
    public function deleteOlderThan(int $timestamp): int;
}
