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

namespace Centreon\Infrastructure\CentreonLegacyDB\Interfaces;

interface PaginationRepositoryInterface
{
    /**
     * Get a list of elements by criteria
     *
     * @param mixed $filters
     * @param int $limit
     * @param int $offset
     * @param array<string,string> $ordering
     * @return array<int,mixed>
     */
    public function getPaginationList($filters = null, ?int $limit = null, ?int $offset = null, $ordering = []): array;

    /**
     * Get total count of elements in the list
     *
     * @return int
     */
    public function getPaginationListTotal(): int;
}
