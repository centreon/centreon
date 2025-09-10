<?php

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\InMemory;

use PHPUnit\Framework\TestCase;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;

/** @group wip */
final class InMemoryPaginatorTest extends TestCase
{
    public function testGetItemsPerPage(): void
    {
        $paginator = new InMemoryPaginator([], 0, 1, 10);
        $this->assertSame(10, $paginator->getItemsPerPage());
    }

    public function testGetCurrentPage(): void
    {
        $paginator = new InMemoryPaginator([], 0, 2, 10);
        $this->assertSame(2, $paginator->getCurrentPage());
    }

    public function testGetLastPage(): void
    {
        $paginator = new InMemoryPaginator([], 25, 1, 10);
        $this->assertSame(3, $paginator->getLastPage());
    }

    public function testGetTotalItems(): void
    {
        $paginator = new InMemoryPaginator([], 42, 1, 10);
        $this->assertSame(42, $paginator->getTotalItems());
    }

    public function testCountReturnsNumberOfItems(): void
    {
        $items = [(object)['id' => 1], (object)['id' => 2]];
        $paginator = new InMemoryPaginator($items, 2, 1, 10);
        $this->assertSame(2, $paginator->count());
    }

    public function testGetIteratorReturnsTraversable(): void
    {
        $items = [(object)['id' => 1], (object)['id' => 2]];
        $paginator = new InMemoryPaginator($items, 2, 1, 10);
        $iterator = $paginator->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);
        $this->assertSame($items, iterator_to_array($iterator));
    }

    public function testLastPageIsAtLeastOne(): void
    {
        $paginator = new InMemoryPaginator([], 0, 1, 10);
        $this->assertSame(1, $paginator->getLastPage());
    }
}
