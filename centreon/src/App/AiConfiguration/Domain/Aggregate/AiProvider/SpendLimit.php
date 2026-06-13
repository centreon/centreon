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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use Webmozart\Assert\Assert;

/**
 * Optional daily and monthly spend caps, in the provider's currency.
 */
final readonly class SpendLimit
{
    public function __construct(
        public ?float $daily = null,
        public ?float $monthly = null,
    ) {
        if ($daily !== null) {
            Assert::greaterThan($daily, 0, 'The daily limit must be greater than zero.');
        }
        if ($monthly !== null) {
            Assert::greaterThan($monthly, 0, 'The monthly limit must be greater than zero.');
        }
    }

    public function hasAnyLimit(): bool
    {
        return $this->daily !== null || $this->monthly !== null;
    }
}
