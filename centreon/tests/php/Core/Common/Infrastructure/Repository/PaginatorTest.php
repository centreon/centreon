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

namespace Tests\Core\Common\Infrastructure\Repository;

use Core\Common\Infrastructure\Repository\Paginator;
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
            (object) ['name' => 'first'],
            (object) ['name' => 'second'],
            (object) ['name' => 'third'],
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

    /**
     * @return \Generator<array<mixed>>
     */
    public static function iteratorDataProvider(): \Generator
    {
        yield [
            1,
            3,
            [
                (object) ['name' => 'first'],
                (object) ['name' => 'second'],
                (object) ['name' => 'third'],
            ],
        ];

        yield [2, 3, []];

        yield [2, 2, [(object) ['name' => 'third']]];

        yield [1, 1, [(object) ['name' => 'first']]];

        yield [2, 1, [(object) ['name' => 'second']]];

        yield [3, 1, [(object) ['name' => 'third']]];

        yield [4, 1, []];
    }
}
