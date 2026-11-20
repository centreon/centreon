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

use App\Shared\Domain\Repository\SortableCriteria;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait SortAwareProviderTrait
{
    /**
     * Applies the "sort[field]=asc|desc" query parameter. Only the fields the criteria exposes
     * through getFieldMapping() can be sorted on: anything else is rejected rather than ignored,
     * so that a typo does not return an unsorted page that looks valid.
     *
     * @template T of SortableCriteria
     *
     * @param array<string, mixed> $filters
     * @param T $criteria
     *
     * @return T
     */
    public function handleSort(array $filters, SortableCriteria $criteria): SortableCriteria
    {
        $sort = $filters['sort'] ?? [];
        if (! is_array($sort)) {
            throw new BadRequestHttpException('The "sort" parameter must use the "sort[field]=asc|desc" format.');
        }

        $allowedFields = array_keys($criteria->getFieldMapping());
        foreach ($sort as $field => $direction) {
            if (! in_array($field, $allowedFields, true)) {
                throw new BadRequestHttpException(
                    sprintf('Cannot sort on "%s", allowed fields are: %s.', $field, implode(', ', $allowedFields))
                );
            }
            if (! is_string($direction) || ! in_array(mb_strtolower($direction), ['asc', 'desc'], true)) {
                throw new BadRequestHttpException(sprintf('The sort direction of "%s" must be "asc" or "desc".', $field));
            }

            $criteria = $criteria->withSort($field, mb_strtoupper($direction));
        }

        return $criteria;
    }
}
