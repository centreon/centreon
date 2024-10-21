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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadContactGroupRepositoryInterface
{
    /**
     * Get all contact groups.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return array<ContactGroup>
     */
    public function findAll(?RequestParametersInterface $requestParameters = null): array;

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
     * Get Contact groups by access groups, user and request parameters.
     *
     * Be careful, it will return contact groups that are in the access groups
     * and the contact groups of the user.
     *
     * @param AccessGroup[] $accessGroups
     * @param ContactInterface $user
     * @param RequestParametersInterface|null $requestParameters
     *
     * @return ContactGroup[]
     */
    public function findByAccessGroupsAndUserAndRequestParameter(
        array $accessGroups,
        ContactInterface $user,
        ?RequestParametersInterface $requestParameters = null
    ): array;

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
     * Check that the contact group ID provided exists.
     *
     * @param int $contactGroupId
     *
     * @return bool
     */
    public function exists(int $contactGroupId): bool;

    /**
     * @param int $contactGroupId
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function existsInAccessGroups(int $contactGroupId, array $accessGroupIds): bool;

    /**
     * Find contact group names by IDs.
     *
     * @param int ...$ids
     *
     * @throws \Throwable
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function findNamesByIds(int ...$ids): array;
}
