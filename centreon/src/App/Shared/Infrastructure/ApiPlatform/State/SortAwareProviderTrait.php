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

trait SortAwareProviderTrait
{
    /**
     * @template T of SortableCriteria
     *
     * @param array{sort?: array<string,string>} $filters
     * @param T $criteria
     *
     * @return T
     */
    public function handleSort(array $filters, SortableCriteria $criteria): SortableCriteria
    {
        $sort = $filters['sort'] ?? [];
        foreach ($sort as $field => $direction) {
            $criteria = $criteria->withSort($field, mb_strtoupper((string) $direction));
        }

        return $criteria;
    }
}
