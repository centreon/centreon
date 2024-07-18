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

namespace Core\ServiceCategory\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceCategory\Domain\Model\ServiceCategoryNamesById;

interface ReadServiceCategoryRepositoryInterface
{
    /**
     * Find all existing service categories ids.
     *
     * @param list<int> $serviceCategoriesIds
     *
     * @throws \Throwable
     *
     * @return list<int>
     */
    public function findAllExistingIds(array $serviceCategoriesIds): array;

    /**
     * Find all existing service categories ids and according to access groups.
     *
     * @param list<int> $serviceCategoriesIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return list<int>
     */
    public function findAllExistingIdsByAccessGroups(array $serviceCategoriesIds, array $accessGroups): array;

    /**
     * Find all service categories.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceCategory[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * Find all service categories by access groups.
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceCategory[]
     */
    public function findByRequestParameterAndAccessGroups(
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find all service categories linked to a service.
     *
     * @param int $serviceId
     *
     * @return ServiceCategory[]
     */
    public function findByService(int $serviceId): array;

    /**
     * Find all service categories linked to a service and according to access groups.
     *
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     *
     * @return ServiceCategory[]
     */
    public function findByServiceAndAccessGroups(int $serviceId, array $accessGroups): array;

    /**
     * Check existence of a service category.
     *
     * @param int $serviceCategoryId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $serviceCategoryId): bool;

    /**
     * Check existence of a service category by access groups.
     *
     * @param int $serviceCategoryId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $serviceCategoryId, array $accessGroups): bool;

    /**
     * Check existance of a service category by name.
     *
     * @param TrimmedString $serviceCategoryName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $serviceCategoryName): bool;

    /**
     * Find one service category.
     *
     * @param int $serviceCategoryId
     *
     * @return ServiceCategory|null
     */
    public function findById(int $serviceCategoryId): ?ServiceCategory;

    /**
     * Find service categoriy names by their IDs.
     *
     * @param int[] $serviceCategoryIds
     *
     * @return ServiceCategoryNamesById
     */
    public function findNames(array $serviceCategoryIds): ServiceCategoryNamesById;

    /**
     * @param int[] $serviceCategoryIds
     *
     * @return int[]
     */
    public function exist(array $serviceCategoryIds): array;

    /**
     * Determine if service categories are filtered for given access group ids
     * true: accessible service categories are filtered (only specified are accessible)
     * false: accessible service categories are not filtered (all are accessible).
     *
     * @param int[] $accessGroupIds
     *
     * @phpstan-param non-empty-array<int> $accessGroupIds
     *
     * @return bool
     */
    public function hasRestrictedAccessToServiceCategories(array $accessGroupIds): bool;
}
