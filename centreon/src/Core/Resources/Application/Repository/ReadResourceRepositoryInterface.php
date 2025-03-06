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

namespace Core\Resources\Application\Repository;

use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Common\Domain\Exception\RepositoryException;

interface ReadResourceRepositoryInterface
{
    /**
     * Find all resources.
     *
     * @param ResourceFilter $filter
     *
     * @throws RepositoryException
     *
     * @return ResourceEntity[]
     */
    public function findResources(ResourceFilter $filter): array;

    /**
     * Find all resources with filter on access group IDs.
     *
     * @param ResourceFilter $filter
     * @param int[] $accessGroupIds
     *
     * @throws RepositoryException
     *
     * @return ResourceEntity[]
     */
    public function findResourcesByAccessGroupIds(ResourceFilter $filter, array $accessGroupIds): array;

    /**
     * @param ResourceFilter $filter
     *
     * @throws RepositoryException
     *
     * @return ResourceEntity[]
     */
    public function findParentResourcesById(ResourceFilter $filter): array;

    /**
     * @param ResourceFilter $filter
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResourcesByMaxResults(ResourceFilter $filter, int $maxResults = 0): \Traversable;

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return \Traversable<ResourceEntity>
     */
    public function iterateResourcesByAccessGroupIdsAndMaxResults(
        ResourceFilter $filter,
        array $accessGroupIds,
        int $maxResults = 0
    ): \Traversable;

    /**
     * @param ResourceFilter $filter
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResourcesByMaxResults(ResourceFilter $filter, int $maxResults): int;

    /**
     * @param ResourceFilter $filter
     * @param array<int> $accessGroupIds
     * @param int $maxResults
     *
     * @throws RepositoryException
     * @return int
     */
    public function countResourcesByAccessGroupIdsAndByMaxResults(
        ResourceFilter $filter,
        array $accessGroupIds,
        int $maxResults
    ): int;
}
