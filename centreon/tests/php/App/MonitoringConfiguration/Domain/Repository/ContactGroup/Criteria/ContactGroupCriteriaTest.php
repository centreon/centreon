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

namespace Tests\App\MonitoringConfiguration\Domain\Repository\ContactGroup\Criteria;

use App\MonitoringConfiguration\Domain\Repository\ContactGroup\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;

final class ContactGroupCriteriaTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $criteria = new ContactGroupCriteria();

        $withName = $criteria->withName('foo', ContactGroupCriteria::OPERATOR_LIKE);

        self::assertNotSame($criteria, $withName);
        self::assertSame([], $criteria->getNames());
        self::assertSame([], $criteria->getIds());
        self::assertNull($criteria->getPage());
    }

    public function testWithPaginationStoresPageAndSize(): void
    {
        $criteria = (new ContactGroupCriteria())->withPagination(2, 20);

        self::assertSame(2, $criteria->getPage());
        self::assertSame(20, $criteria->getItemsPerPage());
    }

    public function testNamesAreGroupedByOperatorAndDeduplicated(): void
    {
        $criteria = (new ContactGroupCriteria())
            ->withName('foo', ContactGroupCriteria::OPERATOR_LIKE)
            ->withName('foo', ContactGroupCriteria::OPERATOR_LIKE)
            ->withName('bar', ContactGroupCriteria::OPERATOR_EQUAL);

        self::assertSame(
            [
                ContactGroupCriteria::OPERATOR_LIKE => ['foo'],
                ContactGroupCriteria::OPERATOR_EQUAL => ['bar'],
            ],
            $criteria->getNames()
        );
    }

    public function testWithNameRejectsAnUnknownOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withName('foo', 'gt'); // @phpstan-ignore argument.type (invalid operator on purpose)
    }

    public function testWithIdAcceptsEquality(): void
    {
        $criteria = (new ContactGroupCriteria())
            ->withId(5, ContactGroupCriteria::OPERATOR_EQUAL)
            ->withId(5, ContactGroupCriteria::OPERATOR_EQUAL)
            ->withId(7, ContactGroupCriteria::OPERATOR_EQUAL);

        self::assertSame([ContactGroupCriteria::OPERATOR_EQUAL => [5, 7]], $criteria->getIds());
    }

    public function testWithIdRejectsTheLikeOperator(): void
    {
        // ids only support equality — a LIKE on a numeric id is meaningless.
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withId(5, ContactGroupCriteria::OPERATOR_LIKE); // @phpstan-ignore argument.type (LIKE rejected on purpose)
    }

    public function testWithViewerIdStoresTheViewer(): void
    {
        $criteria = (new ContactGroupCriteria())->withViewerId(new UserId(12));

        self::assertNotNull($criteria->getViewerId());
        self::assertSame(12, $criteria->getViewerId()->value);
    }
}
