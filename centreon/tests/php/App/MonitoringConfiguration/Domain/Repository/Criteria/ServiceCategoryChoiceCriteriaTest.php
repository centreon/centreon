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

namespace Tests\App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCategoryChoiceCriteria;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('wip')]
final class ServiceCategoryChoiceCriteriaTest extends TestCase
{
    // Pagination immutability is already covered generically by PaginableCriteriaTest, since
    // ServiceCategoryChoiceCriteria only wires in PaginableCriteriaTrait without overriding its behavior.

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ServiceCategoryChoiceCriteria())->withName('');
    }

    public function testWithNameAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on empty(), which treats "0" as empty.
        self::assertSame('0', (new ServiceCategoryChoiceCriteria())->withName('0')->getName());
    }

    public function testWithersReturnACopy(): void
    {
        $criteria = new ServiceCategoryChoiceCriteria();

        $withName = $criteria->withName('Critical');
        $withViewer = $criteria->withViewerId(new UserId(7));

        self::assertNotSame($criteria, $withName);
        self::assertNotSame($criteria, $withViewer);
        self::assertNull($criteria->getName());
        self::assertNull($criteria->getViewerId());
        self::assertSame('Critical', $withName->getName());
        self::assertSame(7, $withViewer->getViewerId()?->value);
    }
}
