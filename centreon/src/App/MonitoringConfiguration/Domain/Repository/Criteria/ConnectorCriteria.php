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

namespace App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use Webmozart\Assert\Assert;

final class ConnectorCriteria implements PaginableCriteria
{
    use PaginableCriteriaTrait;
    use OperatorNameFilterTrait;

    /** @var array<self::OPERATOR_*, list<int>> */
    private array $ids = [];

    public function withId(int $id, string $operator): self
    {
        Assert::positiveInteger($id);
        Assert::inArray($operator, self::ALLOWED_OPERATORS);

        $ids = $this->ids[$operator] ?? [];
        $ids[] = $id;
        $ids = array_values(array_unique($ids));

        $new = clone $this;
        $new->ids[$operator] = $ids;

        return $new;
    }

    /**
     * @return array<self::OPERATOR_*, list<int>>
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
