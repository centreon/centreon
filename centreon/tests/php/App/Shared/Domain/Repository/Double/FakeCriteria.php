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

namespace Tests\App\Shared\Domain\Repository\Double;

use App\Shared\Domain\Repository\PaginableCriteria;
use App\Shared\Domain\Repository\PaginableCriteriaTrait;
use App\Shared\Domain\Repository\SearchableCriteria;
use App\Shared\Domain\Repository\SearchableCriteriaTrait;
use App\Shared\Domain\Repository\SearchOperatorEnum;
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortableCriteriaTrait;

/**
 * Minimal concrete criteria composing the three listing capabilities, used to
 * exercise the reusable foundation. Allows EQUAL, LIKE, IN and NOT_IN: GREATER_THAN
 * stays out so the "operator not allowed" path can be covered, IN / NOT_IN exercise
 * list operators.
 */
final class FakeCriteria implements PaginableCriteria, SearchableCriteria, SortableCriteria
{
    use PaginableCriteriaTrait;
    use SearchableCriteriaTrait;
    use SortableCriteriaTrait;

    public function allowedOperators(): array
    {
        return [
            SearchOperatorEnum::EQUAL,
            SearchOperatorEnum::LIKE,
            SearchOperatorEnum::IN,
            SearchOperatorEnum::NOT_IN,
        ];
    }

    public function getFieldMapping(): array
    {
        return ['name' => 'ba_name', 'id' => 'ba_id'];
    }
}
