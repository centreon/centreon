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

namespace App\Shared\Domain\Repository;

use Webmozart\Assert\Assert;

/**
 * Default {@see SearchableCriteria} implementation, symmetric to {@see SortableCriteriaTrait}.
 *
 * The using class must implement {@see SearchableCriteria::allowedOperators()}.
 */
trait SearchableCriteriaTrait
{
    /** @var list<SearchCondition> */
    private array $search = [];

    /**
     * @return list<SearchOperatorEnum>
     */
    abstract public function allowedOperators(): array;

    /**
     * @param string|list<string> $value a single value for scalar operators, a non-empty list for IN / NOT_IN
     *
     * @throws \InvalidArgumentException when the operator is unknown or not allowed, the field is empty,
     *                                   or the value does not match the operator arity
     */
    public function withSearch(string $field, SearchOperatorEnum|string $operator, string|array $value): static
    {
        $condition = new SearchCondition($field, $this->resolveOperator($operator), $value);

        $clone = clone $this;
        $clone->search[] = $condition;

        return $clone;
    }

    /**
     * @return list<SearchCondition>
     */
    public function getSearch(): array
    {
        return $this->search;
    }

    /**
     * @throws \InvalidArgumentException when the operator is unknown or not allowed for this listing
     */
    private function resolveOperator(SearchOperatorEnum|string $operator): SearchOperatorEnum
    {
        if (is_string($operator)) {
            $resolved = SearchOperatorEnum::tryFrom($operator);
            Assert::notNull($resolved, sprintf('Unknown search operator "%s".', $operator));
            $operator = $resolved;
        }

        Assert::inArray(
            $operator,
            $this->allowedOperators(),
            sprintf(
                'Operator "%s" is not allowed for this listing. Allowed operators: %s.',
                $operator->value,
                implode(', ', array_map(static fn (SearchOperatorEnum $allowed): string => $allowed->value, $this->allowedOperators()))
            )
        );

        return $operator;
    }
}
