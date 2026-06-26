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
 * Canonical set of search operators a configuration listing may expose.
 *
 * A given listing only allows a subset of these operators (see
 * {@see SearchableCriteria::allowedOperators()}); any operator outside that subset
 * is rejected when building the criteria.
 */
enum SearchOperatorEnum: string
{
    case EQUAL = 'eq';
    case NOT_EQUAL = 'neq';
    case LIKE = 'lk';
    case NOT_LIKE = 'nlk';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case IN = 'in';
    case NOT_IN = 'nin';

    /**
     * Whether this operator filters against a list of values (IN / NOT_IN)
     * rather than a single value.
     */
    public function expectsList(): bool
    {
        return match ($this) {
            self::IN, self::NOT_IN => true,
            default => false,
        };
    }
}
