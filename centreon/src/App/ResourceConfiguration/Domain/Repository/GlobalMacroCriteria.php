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

namespace App\ResourceConfiguration\Domain\Repository;

use Webmozart\Assert\Assert;

final class GlobalMacroCriteria
{
    public const EQUAL_OPERATOR = 'eq';
    public const LIKE_OPERATOR = 'lk';
    public const ALLOWED_OPERATORS = [
        self::EQUAL_OPERATOR,
        self::LIKE_OPERATOR,
    ];

    private ?int $page = null;

    private ?int $itemsPerPage = null;

    private ?string $name = null;

    private ?string $operator = null;

    public function withPagination(int $page, int $itemsPerPage): self
    {
        Assert::positiveInteger($page);
        Assert::positiveInteger($itemsPerPage);

        $new = clone $this;
        $new->page = $page;
        $new->itemsPerPage = $itemsPerPage;

        return $new;
    }

    public function withName(string $name): self
    {
        Assert::notEmpty($name);

        $new = clone $this;
        $new->name = $name;

        return $new;
    }

    public function withOperator(string $operator): self
    {
        Assert::oneOf($operator, self::ALLOWED_OPERATORS);
        $new = clone $this;
        $new->operator = $operator;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }
}
