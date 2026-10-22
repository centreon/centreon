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

use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;

final class ContactGroupCriteriaTest extends TestCase
{
    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $criteria = new ContactGroupCriteria();

        $new = $criteria->withName('Supervisors');

        self::assertSame([], $criteria->getNames());
        self::assertSame(['Supervisors'], $new->getNames());
    }

    public function testWithPaginationLeavesTheOriginalUnchanged(): void
    {
        $criteria = new ContactGroupCriteria();

        $new = $criteria->withPagination(2, 10);

        self::assertNull($criteria->getPagination());
        $pagination = $new->getPagination();
        self::assertNotNull($pagination);
        self::assertSame(2, $pagination->page);
        self::assertSame(10, $pagination->itemsPerPage);
    }

    public function testWithViewerIdLeavesTheOriginalUnchanged(): void
    {
        $criteria = new ContactGroupCriteria();

        $new = $criteria->withViewerId(new UserId(7));

        self::assertNull($criteria->getViewerId());
        self::assertSame(7, $new->getViewerId()?->value);
    }

    public function testWithNameDeduplicates(): void
    {
        $criteria = (new ContactGroupCriteria())
            ->withName('Supervisors')
            ->withName('Supervisors');

        self::assertSame(['Supervisors'], $criteria->getNames());
    }

    public function testWithNameAcceptsAZeroName(): void
    {
        // "0" is a legitimate name: the guard must be stringNotEmpty, not notEmpty (which uses empty()).
        self::assertSame(['0'], (new ContactGroupCriteria())->withName('0')->getNames());
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withName('');
    }

    public function testWithPaginationRejectsANonPositivePage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withPagination(0, 10);
    }

    public function testWithPaginationRejectsANonPositiveItemsPerPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withPagination(1, 0);
    }
}
