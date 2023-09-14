<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\HostCategory\Application\Repository;

use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\NewHostCategory;

interface WriteHostCategoryRepositoryInterface
{
    /**
     * Delete host category.
     *
     * @param int $hostCategoryId
     */
    public function deleteById(int $hostCategoryId): void;

    /**
     * Add a host category
     * Return the id of the host category.
     *
     * @param NewHostCategory $hostCategory
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewHostCategory $hostCategory): int;

    /**
     * Update a host category.
     *
     * @param HostCategory $hostCategory
     *
     * @throws \Throwable
     */
    public function update(HostCategory $hostCategory): void;

    /**
     * Link a list of categories to a host (or hostTemplate).
     *
     * @param int $hostId
     * @param int[] $categoryIds
     *
     * @throws \Throwable
     */
    public function linkToHost(int $hostId, array $categoryIds): void;

    /**
     * Unlink a list of categories to a host (or hostTemplate).
     *
     * @param int $hostId
     * @param int[] $categoryIds
     *
     * @throws \Throwable
     */
    public function unlinkFromHost(int $hostId, array $categoryIds): void;
}
