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

namespace Core\HostTemplate\Application\Repository;

use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\NewHostTemplate;

interface WriteHostTemplateRepositoryInterface
{
    /**
     * Delete host template by id.
     *
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     */
    public function delete(int $hostTemplateId): void;

    /**
     * Add host template.
     *
     * @param NewHostTemplate $hostTemplate
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewHostTemplate $hostTemplate): int;

    /**
     * Update host template.
     *
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    public function update(HostTemplate $hostTemplate): void;

    /**
     * Link a parent template to a child host(or another hostTemplate).
     *
     * @param int $childId host or host template to be linked as a child
     * @param int $parentId host template to be linked as a parent
     * @param int $order order of inheritance of the parent
     *
     * @throws \Throwable
     */
    public function addParent(int $childId, int $parentId, int $order): void;

    /**
     * Unlink parent templates from a child host(or another host template).
     *
     * @param int $childId host or host template to be unlinked as a child
     *
     * @throws \Throwable
     */
    public function deleteParents(int $childId): void;
}
