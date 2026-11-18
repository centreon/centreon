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

namespace Tests\App\Shared\Domain\Repository;

use App\Shared\Domain\Repository\Pagination;
use PHPUnit\Framework\TestCase;

/**
 * UNIT-01 — pagination computation (page / limit / offset) and bounds.
 */
final class PaginationTest extends TestCase
{
    public function testOffsetForSecondPage(): void
    {
        $pagination = new Pagination(page: 2, itemsPerPage: 10);

        self::assertSame(2, $pagination->page);
        self::assertSame(10, $pagination->itemsPerPage);
        self::assertSame(10, $pagination->getOffset());
    }

    public function testOffsetOfFirstPageIsZero(): void
    {
        self::assertSame(0, (new Pagination(page: 1, itemsPerPage: 25))->getOffset());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function offsetProvider(): iterable
    {
        yield 'page 1 / limit 10' => [1, 10, 0];

        yield 'page 3 / limit 10' => [3, 10, 20];

        yield 'page 5 / limit 50' => [5, 50, 200];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('offsetProvider')]
    public function testOffsetComputation(int $page, int $itemsPerPage, int $expectedOffset): void
    {
        self::assertSame($expectedOffset, (new Pagination($page, $itemsPerPage))->getOffset());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function outOfBoundsProvider(): iterable
    {
        yield 'page below 1' => [0, 10];

        yield 'negative page' => [-1, 10];

        yield 'zero items per page' => [1, 0];

        yield 'negative items per page' => [1, -5];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('outOfBoundsProvider')]
    public function testRejectsOutOfBoundsValues(int $page, int $itemsPerPage): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Pagination($page, $itemsPerPage);
    }
}
