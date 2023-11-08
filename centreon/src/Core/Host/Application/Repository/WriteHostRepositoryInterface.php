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

namespace Core\Host\Application\Repository;

use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\NewHost;

interface WriteHostRepositoryInterface
{
    /**
     * Add host.
     *
     * @param NewHost $host
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewHost $host): int;

    /**
     * Update host.
     *
     * @param Host $host
     *
     * @throws \Throwable
     */
    public function update(Host $host): void;

    /**
     * Link a parent template to a child host.
     *
     * @param int $childId host to be linked as a child
     * @param int $parentId host template to be linked as a parent
     * @param int $order order of inheritance of the parent
     *
     * @throws \Throwable
     */
    public function addParent(int $childId, int $parentId, int $order): void;

    /**
     * Unlink parent templates from a child host.
     *
     * @param int $childId host to be unlinked as a child
     *
     * @throws \Throwable
     */
    public function deleteParents(int $childId): void;

    /**
     * Delete a host by ID.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     */
    public function deleteById(int $hostId): void;
}
