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

/**
 * Search capability for a listing criteria, symmetric to {@see SortableCriteria}.
 *
 * Immutable: {@see self::withSearch()} returns a new instance and rejects any
 * operator outside {@see self::allowedOperators()}. Pull in
 * {@see SearchableCriteriaTrait} for the default implementation.
 */
interface SearchableCriteria
{
    /**
     * @param string|list<string> $value a single value for scalar operators, a non-empty list for IN / NOT_IN
     *
     * @throws \InvalidArgumentException when the operator is unknown or not allowed, the field is empty,
     *                                   or the value does not match the operator arity
     */
    public function withSearch(string $field, SearchOperatorEnum|string $operator, string|array $value): static;

    /**
     * @return list<SearchCondition>
     */
    public function getSearch(): array;

    /**
     * Operators this listing accepts; any other operator is rejected by {@see self::withSearch()}.
     *
     * @return list<SearchOperatorEnum>
     */
    public function allowedOperators(): array;
}
