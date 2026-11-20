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
 * Pagination capability for a listing criteria, symmetric to {@see SortableCriteria}.
 *
 * Immutable: {@see self::withPagination()} returns a new instance. Pull in
 * {@see PaginableCriteriaTrait} for the default implementation.
 */
interface PaginableCriteria
{
    /**
     * @throws \InvalidArgumentException when $page or $itemsPerPage is not a positive integer
     */
    public function withPagination(int $page, int $itemsPerPage): static;

    public function getPagination(): ?Pagination;
}
