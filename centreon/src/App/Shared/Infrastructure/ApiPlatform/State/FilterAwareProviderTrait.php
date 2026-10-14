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

namespace App\Shared\Infrastructure\ApiPlatform\State;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait FilterAwareProviderTrait
{
    /**
     * Parses an ApiPlatform "<name>[op]=value" query filter, where the same field can carry
     * several operators (each with one or several values), e.g. "name[eq][]=a&name[lk]=b".
     * Operators not in $allowedOperators are dropped rather than rejected: an unsupported
     * operator alongside a supported one should not fail the whole filter.
     *
     * @template TOperator of string
     *
     * @param list<TOperator> $allowedOperators
     *
     * @return array<TOperator, list<string>> values grouped by operator, only for operators
     *                                        present in the request AND in $allowedOperators
     */
    public function handleOperatorFilter(mixed $value, string $filterName, array $allowedOperators): array
    {
        if ($value === null) {
            return [];
        }

        // a client sending "?<name>=foo" instead of "?<name>[op]=foo" lands here as a plain string
        if (! is_array($value)) {
            throw new BadRequestHttpException(
                sprintf(
                    'The "%s" filter must use the "%s[%s]=value" format.',
                    $filterName,
                    $filterName,
                    implode('|', $allowedOperators)
                )
            );
        }

        $result = [];
        foreach ($value as $operator => $values) {
            if (! is_string($operator)) {
                continue;
            }
            if (! in_array($operator, $allowedOperators, true)) {
                continue;
            }

            $normalizedValues = [];
            foreach (is_array($values) ? $values : [$values] as $rawValue) {
                if (! is_scalar($rawValue)) {
                    continue;
                }

                // an empty value (e.g. "?name[eq]=") is treated as absent, not as a request to
                // match an empty string — the underlying Criteria::withX() methods assert
                // non-empty and would otherwise throw an uncaught 500 instead of a clean 400.
                // This also keeps every operator consistent with "lk", which already ignored an
                // empty value this way (see handleLikeFilter and its dedicated test coverage).
                $stringValue = (string) $rawValue;
                if ($stringValue !== '') {
                    $normalizedValues[] = $stringValue;
                }
            }

            $result[$operator] = $normalizedValues;
        }

        return $result;
    }

    /**
     * Parses an ApiPlatform "<name>[lk]=value" query filter.
     */
    public function handleLikeFilter(mixed $value, string $filterName): ?string
    {
        return $this->handleOperatorFilter($value, $filterName, ['lk'])['lk'][0] ?? null;
    }

    public function handlePositiveIntFilter(mixed $value, string $filterName): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be a positive integer.', $filterName));
        }

        return (int) $value;
    }

    public function handleBoolFilter(mixed $value, string $filterName): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be a boolean.', $filterName));
        }

        return $normalized;
    }
}
