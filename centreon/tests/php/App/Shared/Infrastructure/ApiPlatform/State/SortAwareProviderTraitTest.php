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

namespace Tests\App\Shared\Infrastructure\ApiPlatform\State;

use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortableCriteriaTrait;
use App\Shared\Domain\Repository\SortDirectionEnum;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SortAwareProviderTraitTest extends TestCase
{
    public function testItLeavesTheCriteriaUnsortedWithoutSortParameter(): void
    {
        $criteria = $this->handleSort([]);

        self::assertSame([], $criteria->getSort());
    }

    public function testItAppliesTheSortedFieldsInTheOrderOfTheRequest(): void
    {
        $criteria = $this->handleSort(['sort' => ['alias' => 'desc', 'name' => 'asc']]);

        self::assertSame(
            ['alias' => SortDirectionEnum::DESC, 'name' => SortDirectionEnum::ASC],
            $criteria->getSort()
        );
    }

    public function testItAcceptsADirectionWhateverItsCase(): void
    {
        $criteria = $this->handleSort(['sort' => ['name' => 'DeSc']]);

        self::assertSame(['name' => SortDirectionEnum::DESC], $criteria->getSort());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidSortParameters(): iterable
    {
        yield 'scalar sort parameter' => ['name'];

        yield 'unknown field' => [['poller' => 'asc']];

        yield 'integer field' => [[0 => 'asc']];

        yield 'unknown direction' => [['name' => 'upward']];

        yield 'non-string direction' => [['name' => ['asc']]];

        yield 'valid field followed by an unknown one' => [['name' => 'asc', 'poller' => 'asc']];
    }

    #[DataProvider('provideInvalidSortParameters')]
    public function testItRejectsAnInvalidSortParameter(mixed $sort): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->handleSort(['sort' => $sort]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function handleSort(array $filters): SortableCriteria
    {
        $provider = new class () {
            use SortAwareProviderTrait;
        };

        return $provider->handleSort($filters, new class () implements SortableCriteria {
            use SortableCriteriaTrait;

            public function getFieldMapping(): array
            {
                return ['name' => 'host_name', 'alias' => 'host_alias'];
            }
        });
    }
}
