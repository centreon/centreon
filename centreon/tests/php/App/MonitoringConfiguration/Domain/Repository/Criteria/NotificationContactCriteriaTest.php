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

use App\MonitoringConfiguration\Domain\Repository\Criteria\NotificationContactCriteria;
use PHPUnit\Framework\TestCase;

final class NotificationContactCriteriaTest extends TestCase
{
    // Pagination immutability is already covered generically by PaginableCriteriaTest, and
    // viewer-scoping immutability has no dedicated test anywhere ViewerScopedCriteriaTrait is used
    // (Host, HostCategory, HostGroup, HostTemplate, Poller) since NotificationContactCriteria only
    // wires both traits in without overriding their behavior.

    public function testWithNameReturnsANewInstanceAndLeavesTheOriginalUnchanged(): void
    {
        $criteria = new NotificationContactCriteria();

        $withName = $criteria->withName('foo');

        self::assertNotSame($criteria, $withName);
        self::assertNull($criteria->getName());
        self::assertSame('foo', $withName->getName());
    }

    public function testWithNameAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on PHP's empty(), which wrongly
        // treats "0" as empty. A contact legitimately named "0" must not be rejected.
        $criteria = (new NotificationContactCriteria())->withName('0');

        self::assertSame('0', $criteria->getName());
    }

    public function testWithNameRejectsAnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new NotificationContactCriteria())->withName('');
    }
}
