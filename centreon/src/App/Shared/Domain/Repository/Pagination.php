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
 * Immutable pagination request: a 1-based page and a strictly positive page size.
 *
 * The zero-based {@see self::getOffset()} is what a repository feeds to a
 * SQL {@code LIMIT ... OFFSET ...} clause.
 */
final readonly class Pagination
{
    /**
     * @throws \InvalidArgumentException when $page or $itemsPerPage is not a positive integer
     */
    public function __construct(
        public int $page,
        public int $itemsPerPage,
    ) {
        Assert::positiveInteger($page, 'Page must be a positive integer, got %s.');
        Assert::positiveInteger($itemsPerPage, 'Items per page must be a positive integer, got %s.');
    }

    /**
     * Zero-based index of the first item of the requested page.
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->itemsPerPage;
    }
}
