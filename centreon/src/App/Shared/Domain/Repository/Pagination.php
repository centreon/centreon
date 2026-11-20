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

namespace App\Shared\Domain\Repository;

use Webmozart\Assert\Assert;

/**
 * Immutable pagination request: a 1-based page and a page size of at most
 * {@see self::MAX_ITEMS_PER_PAGE} items.
 *
 * The zero-based {@see self::getOffset()} is what a repository feeds to a
 * SQL {@code LIMIT ... OFFSET ...} clause.
 */
final readonly class Pagination
{
    /** Matches the largest page size offered by the UI listings. */
    public const MAX_ITEMS_PER_PAGE = 100;

    /**
     * @throws \InvalidArgumentException when $page is not a positive integer, $itemsPerPage is out of
     *                                   [1, MAX_ITEMS_PER_PAGE], or the offset would overflow an int
     */
    public function __construct(
        public int $page,
        public int $itemsPerPage,
    ) {
        Assert::positiveInteger($page, 'Page must be a positive integer, got %s.');
        Assert::range($itemsPerPage, 1, self::MAX_ITEMS_PER_PAGE, 'Items per page must be between %2$s and %3$s, got %s.');
        Assert::lessThanEq($page - 1, intdiv(PHP_INT_MAX, $itemsPerPage), sprintf('Page %d is too large.', $page));
    }

    /**
     * Zero-based index of the first item of the requested page.
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->itemsPerPage;
    }
}
