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

namespace App\MonitoringConfiguration\Domain\Model;

use Webmozart\Assert\Assert;

/**
 * The number of items a listing page holds when the client does not ask for one.
 */
final readonly class ListingPageSize
{
    private const DEFAULT = 30;

    public function __construct(
        public int $value,
    ) {
        Assert::positiveInteger($value);
    }

    /**
     * Reads a page size stored as text (the `maxViewConfiguration` option): decimal digits only once trimmed,
     * leading zeros allowed (`050` is 50), with no maximum. Anything else falls back to the default of 30:
     * no value, an empty or blank one, zero, any other character (a sign, a decimal point, a suffix), or a
     * number beyond the largest integer. Compared as text, so an oversized value never overflows.
     */
    public static function fromOptionValue(?string $optionValue): self
    {
        $digits = trim($optionValue ?? '');
        if (preg_match('/^\d+$/', $digits) !== 1) {
            return new self(self::DEFAULT);
        }

        $digits = ltrim($digits, '0');
        if ($digits === '' || self::exceedsTheLargestInteger($digits)) {
            return new self(self::DEFAULT);
        }

        return new self((int) $digits);
    }

    /**
     * @param non-empty-string $digits decimal digits without leading zeros
     */
    private static function exceedsTheLargestInteger(string $digits): bool
    {
        $largest = (string) PHP_INT_MAX;

        return mb_strlen($digits) > mb_strlen($largest)
            || (mb_strlen($digits) === mb_strlen($largest) && strcmp($digits, $largest) > 0);
    }
}
