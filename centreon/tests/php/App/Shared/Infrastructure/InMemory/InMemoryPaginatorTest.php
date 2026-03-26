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

namespace Tests\App\Shared\Infrastructure\InMemory;

use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use PHPUnit\Framework\TestCase;

final class InMemoryPaginatorTest extends TestCase
{
    public function testGetItemsPerPage(): void
    {
        $paginator = new InMemoryPaginator(new Collection([], \stdClass::class), 0, 1, 10);
        $this->assertSame(10, $paginator->getItemsPerPage());
    }

    public function testGetCurrentPage(): void
    {
        $paginator = new InMemoryPaginator(new Collection([], \stdClass::class), 0, 2, 10);
        $this->assertSame(2, $paginator->getCurrentPage());
    }

    public function testGetLastPage(): void
    {
        $paginator = new InMemoryPaginator(new Collection([], \stdClass::class), 25, 1, 10);
        $this->assertSame(3, $paginator->getLastPage());
    }

    public function testGetTotalItems(): void
    {
        $paginator = new InMemoryPaginator(new Collection([], \stdClass::class), 42, 1, 10);
        $this->assertSame(42, $paginator->getTotalItems());
    }

    public function testCountReturnsNumberOfItems(): void
    {
        $items = new Collection([(object) ['id' => 1], (object) ['id' => 2]], \stdClass::class);
        $paginator = new InMemoryPaginator($items, 2, 1, 10);
        $this->assertSame(2, $paginator->count());
    }

    public function testGetIteratorReturnsTraversable(): void
    {
        $items = new Collection([(object) ['id' => 1], (object) ['id' => 2]], \stdClass::class);
        $paginator = new InMemoryPaginator($items, 2, 1, 10);
        $iterator = $paginator->getIterator();
        $this->assertSame(iterator_to_array($items), iterator_to_array($iterator));
    }

    public function testLastPageIsAtLeastOne(): void
    {
        $paginator = new InMemoryPaginator(new Collection([], \stdClass::class), 0, 1, 10);
        $this->assertSame(1, $paginator->getLastPage());
    }
}
