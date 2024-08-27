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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\HostCategoryNamesById;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostCategoryRepositoryInterface
{
    /**
     * Find all host categories.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    public function findAll(?RequestParametersInterface $requestParameters): array;

    /**
     * Find all host categories by access groups.
     *
     * @param int[] $accessGroupIds
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    public function findAllByAccessGroupIds(array $accessGroupIds, ?RequestParametersInterface $requestParameters): array;

    /**
     * Check existence of a host category.
     *
     * @param int $hostCategoryId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $hostCategoryId): bool;

    /**
     * Check existence of a host category by access groups.
     *
     * @param int $hostCategoryId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $hostCategoryId, array $accessGroups): bool;

    /**
     * Check existence of a list of host categories.
     * Return the ids of the existing categories.
     *
     * @param int[] $hostCategoryIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $hostCategoryIds): array;

    /**
     * Check existence of a list of host categories by access groups.
     * Return the ids of the existing categories.
     *
     * @param int[] $hostCategoryIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function existByAccessGroups(array $hostCategoryIds, array $accessGroups): array;

    /**
     * Check existence of a host category by name.
     *
     * @param TrimmedString $hostCategoryName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $hostCategoryName): bool;

    /**
     * Find one host category.
     *
     * @param int $hostCategoryId
     *
     * @throws \Throwable
     *
     * @return HostCategory|null
     */
    public function findById(int $hostCategoryId): ?HostCategory;

    /**
     * Find host categories by their ID.
     *
     * @param int ...$hostCategoryIds
     *
     * @throws \Throwable
     *
     * @return list<HostCategory>
     */
    public function findByIds(int ...$hostCategoryIds): array;

    /**
     * Find host categories linked to a host (or host template) (no ACLs).
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    public function findByHost(int $hostId): array;

    /**
     * Find host categories linked to a host (or host template) by access groups.
     *
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    public function findByHostAndAccessGroups(int $hostId, array $accessGroups): array;

    /**
     * Find Host Categories names by their IDs.
     *
     * @param int[] $hostCategoryIds
     *
     * @return HostCategoryNamesById
     */
    public function findNames(array $hostCategoryIds): HostCategoryNamesById;

    /**
     * Determine if host categories are filtered for given access group ids.
     *
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function hasRestrictedAccessToHostCategories(array $accessGroupIds): bool;
}
