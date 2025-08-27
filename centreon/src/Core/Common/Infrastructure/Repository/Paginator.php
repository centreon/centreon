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

namespace Core\Common\Infrastructure\Repository;

use Core\Common\Domain\Repository\PaginatorInterface;
use Webmozart\Assert\Assert;

/**
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 */
final readonly class Paginator implements PaginatorInterface
{
    private int $offset;

    private int $limit;

    private int $lastPage;

    /**
     * @param \Traversable<T> $items
     */
    public function __construct(
        private \Traversable $items,
        private int $currentPage,
        private int $itemsPerPage,
        private int $totalItems,
    ) {
        Assert::greaterThanEq($totalItems, 0);
        Assert::positiveInteger($currentPage);
        Assert::positiveInteger($itemsPerPage);

        $this->offset = ($currentPage - 1) * $itemsPerPage;
        $this->limit = $itemsPerPage;
        $this->lastPage = (int) max(1, ceil($totalItems / $itemsPerPage));
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function getIterator(): \Traversable
    {
        if ($this->currentPage > $this->lastPage) {
            return new \EmptyIterator();
        }

        return new \LimitIterator(new \IteratorIterator($this->items), $this->offset, $this->limit);
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function itemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }
}
