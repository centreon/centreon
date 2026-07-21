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

use App\Shared\Domain\Repository\SearchOperatorEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Domain\Repository\Double\FakeCriteria;

/**
 * UNIT-02 (allowed / rejected operators) and UNIT-03 (immutability) for the search capability.
 */
final class SearchableCriteriaTest extends TestCase
{
    public function testAllowedOperatorsAreAccepted(): void
    {
        $criteria = (new FakeCriteria())
            ->withSearch('name', SearchOperatorEnum::EQUAL, 'Ping')
            ->withSearch('name', 'lk', 'Ba%');

        $conditions = $criteria->getSearch();

        self::assertCount(2, $conditions);
        self::assertSame(SearchOperatorEnum::EQUAL, $conditions[0]->operator);
        self::assertSame('Ping', $conditions[0]->value);
        self::assertSame(SearchOperatorEnum::LIKE, $conditions[1]->operator);
    }

    public function testRejectsOperatorNotAllowedForListing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // GREATER_THAN is a valid operator but not allowed by FakeCriteria.
        (new FakeCriteria())->withSearch('name', SearchOperatorEnum::GREATER_THAN, '10');
    }

    public function testRejectsUnknownOperatorString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSearch('name', 'unknown', 'value');
    }

    public function testListOperatorAcceptsListOfValues(): void
    {
        $condition = (new FakeCriteria())
            ->withSearch('id', SearchOperatorEnum::IN, ['1', '2', '3'])
            ->getSearch()[0];

        self::assertSame(SearchOperatorEnum::IN, $condition->operator);
        self::assertSame(['1', '2', '3'], $condition->value);
    }

    public function testNotInListOperatorAcceptsListOfValues(): void
    {
        $condition = (new FakeCriteria())
            ->withSearch('id', SearchOperatorEnum::NOT_IN, ['1', '2'])
            ->getSearch()[0];

        self::assertSame(SearchOperatorEnum::NOT_IN, $condition->operator);
        self::assertSame(['1', '2'], $condition->value);
    }

    public function testListOperatorRejectsScalarValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSearch('id', SearchOperatorEnum::IN, '1');
    }

    public function testListOperatorRejectsEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSearch('id', SearchOperatorEnum::IN, []);
    }

    public function testRejectsEmptyField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSearch('', SearchOperatorEnum::EQUAL, 'Ping');
    }

    public function testScalarOperatorRejectsListValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FakeCriteria())->withSearch('name', SearchOperatorEnum::EQUAL, ['Ping']);
    }

    public function testWithSearchReturnsANewInstance(): void
    {
        $original = new FakeCriteria();
        $searched = $original->withSearch('name', SearchOperatorEnum::EQUAL, 'Ping');

        self::assertNotSame($original, $searched);
        self::assertSame([], $original->getSearch());
        self::assertCount(1, $searched->getSearch());
    }
}
