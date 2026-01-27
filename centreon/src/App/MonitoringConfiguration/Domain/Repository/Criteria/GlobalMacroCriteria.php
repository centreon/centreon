<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Webmozart\Assert\Assert;

final class GlobalMacroCriteria
{
    public const OPERATOR_EQUAL = 'eq';
    public const OPERATOR_LIKE = 'lk';
    public const ALLOWED_OPERATORS = [self::OPERATOR_EQUAL, self::OPERATOR_LIKE];

    private ?int $page = null;

    private ?int $itemsPerPage = null;

    /** @var array<self::OPERATOR_*, list<string>> */
    private array $names = [];

    private bool $lazyRelations = false;

    public function withPagination(int $page, int $itemsPerPage): self
    {
        Assert::positiveInteger($page);
        Assert::positiveInteger($itemsPerPage);

        $new = clone $this;
        $new->page = $page;
        $new->itemsPerPage = $itemsPerPage;

        return $new;
    }

    /**
     * @param self::OPERATOR_* $operator
     */
    public function withName(string $name, string $operator): self
    {
        Assert::notEmpty($name);
        Assert::inArray($operator, self::ALLOWED_OPERATORS);

        $names = $this->names[$operator] ?? [];
        $names[] = $name;
        $names = array_values(array_unique($names));

        $new = clone $this;
        $new->names[$operator] = $names;

        return $new;
    }

    public function withLazyRelations(bool $lazyRelations = true): self
    {
        $new = clone $this;
        $new->lazyRelations = $lazyRelations;

        return $new;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    /**
     * @return array<self::OPERATOR_*, list<string>>
     */
    public function getNames(): array
    {
        return $this->names;
    }

    public function hasLazyRelations(): bool
    {
        return $this->lazyRelations;
    }
}
