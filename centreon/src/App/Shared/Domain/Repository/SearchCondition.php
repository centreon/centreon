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
 * Immutable search condition: filter a field with an operator against a value.
 *
 * Scalar operators (eq, lk, gt, …) carry a single string value; list operators
 * (in, nin) carry a non-empty list of strings.
 */
final readonly class SearchCondition
{
    /**
     * @param string|list<string> $value a single value for scalar operators, a non-empty list for IN / NOT_IN
     *
     * @throws \InvalidArgumentException when $field is empty, or $value does not match the operator arity
     */
    public function __construct(
        public string $field,
        public SearchOperatorEnum $operator,
        public string|array $value,
    ) {
        Assert::notEmpty($field, 'Search field must not be empty.');

        if ($operator->expectsList()) {
            Assert::isList($value, sprintf('Operator "%s" expects a list of values.', $operator->value));
            Assert::notEmpty($value, sprintf('Operator "%s" expects a non-empty list of values.', $operator->value));
        } else {
            Assert::string($value, sprintf('Operator "%s" expects a single string value.', $operator->value));
        }
    }
}
