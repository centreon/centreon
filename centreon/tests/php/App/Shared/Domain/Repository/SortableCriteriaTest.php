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

use App\Shared\Domain\Repository\SortDirectionEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Domain\Repository\Double\FakeCriteria;

/**
 * UNIT-02 (direction validation) and UNIT-03 (immutability) for the sort capability.
 */
final class SortableCriteriaTest extends TestCase
{
    public function testAcceptsBothDirectionsAsStrings(): void
    {
        $criteria = (new FakeCriteria())
            ->withSort('name', 'ASC')
            ->withSort('id', 'DESC');

        self::assertSame(
            ['name' => SortDirectionEnum::ASC, 'id' => SortDirectionEnum::DESC],
            $criteria->getSort()
        );
    }

    public function testRejectsInvalidDirectionString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSort('name', 'sideways');
    }

    public function testWithSortReturnsANewInstance(): void
    {
        $original = new FakeCriteria();
        $sorted = $original->withSort('name', SortDirectionEnum::DESC);

        self::assertNotSame($original, $sorted);
        self::assertSame([], $original->getSort());
        self::assertSame(['name' => SortDirectionEnum::DESC], $sorted->getSort());
    }
}
