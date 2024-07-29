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

namespace Core\Contact\Application\Repository;

use Core\Contact\Domain\Model\ContactGroup;

interface ReadContactGroupRepositoryInterface
{
    /**
     * Get all contact groups.
     *
     * @throws \Throwable
     *
     * @return array<ContactGroup>
     */
    public function findAll(): array;

    /**
     * Get all contact groups of a contact.
     *
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return array<ContactGroup>
     */
    public function findAllByUserId(int $userId): array;

    /**
     * Get a Contact Group.
     *
     * @param int $contactGroupId
     *
     * @throws \Throwable
     *
     * @return ContactGroup|null
     */
    public function find(int $contactGroupId): ?ContactGroup;

    /**
     * Get Contact groups by their ids.
     *
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable
     *
     * @return ContactGroup[]
     */
    public function findByIds(array $contactGroupIds): array;

    /**
     * Get Contact groups by their ids and related user.
     *
     * @param int[] $contactGroupIds
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return ContactGroup[]
     */
    public function findByIdsAndUserId(array $contactGroupIds, int $userId): array;

    /**
     * Check existence of provided contact groups.
     * Return an array of the existing contact group IDs out of the provided ones.
     *
     * @param int[] $contactGroupIds
     *
     * @return int[]
     */
    public function exist(array $contactGroupIds): array;

    /**
     * @param int $contactGroupId
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function existsInAccessGroups(int $contactGroupId, array $accessGroupIds): bool;
}
