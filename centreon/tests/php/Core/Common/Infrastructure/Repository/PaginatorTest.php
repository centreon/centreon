<?php

namespace Tests\Core\Common\Infrastructure\Repository;

use Core\Common\Infrastructure\Repository\Paginator;
use Generator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    /**
     * @dataProvider lastPageDataProvider
     */
    public function testLastPage(int $lastPage, int $itemsPerPage): void
    {
        $items = [
            new \stdClass(),
            new \stdClass(),
            new \stdClass(),
        ];

        $paginator = new Paginator(
            items: new \ArrayIterator($items),
            currentPage: 1,
            itemsPerPage: $itemsPerPage,
            totalItems: count($items),
        );

        static::assertSame($lastPage, $paginator->lastPage());
    }

    /**
     * @return \Generator<array{int,int}>
     */
    public static function lastPageDataProvider(): \Generator
    {
        yield [3, 1];
        yield [2, 2];
        yield [1, 3];
    }

    /**
     * @param array<object> $pageItems
     *
     * @dataProvider iteratorDataProvider
     */
    public function testIterator(int $currentPage, int $itemsPerPage, array $pageItems): void
    {
        $items = [
            (object)['name' => 'first'],
            (object)['name' => 'second'],
            (object)['name' => 'third'],
        ];

        $paginator = new Paginator(
            items: new \ArrayIterator($items),
            currentPage: $currentPage,
            itemsPerPage: $itemsPerPage,
            totalItems: count($items),
        );

        static::assertSame(count($pageItems), $paginator->count());

        $i = 0;
        foreach ($paginator as $item) {
            static::assertEquals($pageItems[$i], $item);
            $i++;
        }
    }

    public static function iteratorDataProvider(): \Generator
    {
        yield [
            1,
            3,
            [
                (object)['name' => 'first'],
                (object)['name' => 'second'],
                (object)['name' => 'third'],
            ],
        ];

        yield [2, 3, []];

        yield [2, 2, [(object)['name' => 'third']]];

        yield [1, 1, [(object)['name' => 'first']]];

        yield [2, 1, [(object)['name' => 'second']]];

        yield [3, 1, [(object)['name' => 'third']]];

        yield [4, 1, []];
    }
}
