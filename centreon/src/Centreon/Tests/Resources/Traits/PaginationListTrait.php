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

namespace Centreon\Tests\Resources\Traits;

trait PaginationListTrait
{
    /** @var \Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface */
    protected $repository;

    /**
     * Test pagination list
     *
     * @param array $data
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param array $ordering
     * @return void
     */
    public function getPaginationListTrait(
        array $data = [],
        ?array $filters = null,
        ?int $limit = null,
        ?int $offset = null,
        array $ordering = [],
    ): void {
        $this->assertEquals(
            [
                $this->repository->getEntityPersister()->load($data),
            ],
            $this->repository->getPaginationList($filters, $limit, $offset, $ordering)
        );
    }

    /**
     * Test pagination list total
     *
     * @param int|string $data
     * @return void
     */
    public function getPaginationListTotalTrait($data = 0): void
    {
        $this->assertEquals(
            (int) $data,
            $this->repository->getPaginationListTotal()
        );
    }
}
