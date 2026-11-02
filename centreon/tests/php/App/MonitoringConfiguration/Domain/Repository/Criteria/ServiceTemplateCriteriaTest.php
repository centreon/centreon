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

use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use PHPUnit\Framework\TestCase;

final class ServiceTemplateCriteriaTest extends TestCase
{
    // Pagination immutability is already covered generically by PaginableCriteriaTest, since
    // ServiceTemplateCriteria only wires in PaginableCriteriaTrait without overriding its behavior.

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ServiceTemplateCriteria())->withName('');
    }

    public function testWithNameAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on empty(), which treats "0" as empty.
        self::assertSame('0', (new ServiceTemplateCriteria())->withName('0')->getName());
    }

    public function testWithersReturnACopy(): void
    {
        $criteria = new ServiceTemplateCriteria();

        $withName = $criteria->withName('generic-service');

        self::assertNotSame($criteria, $withName);
        self::assertNull($criteria->getName());
        self::assertSame('generic-service', $withName->getName());
    }
}
