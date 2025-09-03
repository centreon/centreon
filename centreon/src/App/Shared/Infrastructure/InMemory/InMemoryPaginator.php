<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\InMemory;

use App\Shared\Domain\Repository\Paginator;
use Webmozart\Assert\Assert;

/**
 * @template T of object
 *
 * @implements Paginator<T>
 */
final readonly class InMemoryPaginator implements Paginator
{
    private int $offset;
    private int $limit;
    private int $lastPage;

    /**
     * @param array<T> $items
     */
    public function __construct(
        private array $items,
        private int $totalItems,
        private int $currentPage,
        private int $itemsPerPage,
    ) {
        Assert::greaterThanEq($totalItems, 0);
        Assert::positiveInteger($currentPage);
        Assert::positiveInteger($itemsPerPage);

        $this->offset = ($currentPage - 1) * $itemsPerPage;
        $this->limit = $itemsPerPage;
        $this->lastPage = (int) max(1, ceil($totalItems / $itemsPerPage));
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function count(): int
    {
        return count($this->getIterator());
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
