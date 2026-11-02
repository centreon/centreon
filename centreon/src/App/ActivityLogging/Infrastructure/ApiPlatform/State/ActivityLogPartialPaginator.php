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

namespace App\ActivityLogging\Infrastructure\ApiPlatform\State;

use ApiPlatform\State\Pagination\PartialPaginatorInterface;

/**
 * Lets API Platform render a page without "totalItems". Hydra adds a "next" link to every
 * full page, so a last page that happens to be full is followed by one empty page.
 *
 * @template T of object
 *
 * @implements PartialPaginatorInterface<T>
 * @implements \IteratorAggregate<int, T>
 */
final readonly class ActivityLogPartialPaginator implements PartialPaginatorInterface, \IteratorAggregate
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        private array $items,
        private int $currentPage,
        private int $itemsPerPage,
    ) {
    }

    public function getCurrentPage(): float
    {
        return (float) $this->currentPage;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
