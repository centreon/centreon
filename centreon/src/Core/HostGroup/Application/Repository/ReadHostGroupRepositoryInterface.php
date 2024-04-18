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

namespace Core\HostGroup\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostGroupNamesById;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostGroupRepositoryInterface
{
    /**
     * Find All host groups without acl.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return \Traversable<int, HostGroup>&\Countable
     */
    public function findAll(?RequestParametersInterface $requestParameters = null): \Traversable&\Countable;

    /**
     * Find All host groups with access groups.
     *
     * @param RequestParametersInterface|null $requestParameters
     * @param list<int> $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return \Traversable<HostGroup>&\Countable
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds):
    \Traversable&\Countable;

    /**
     * Find one host group without acl.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return HostGroup|null
     */
    public function findOne(int $hostGroupId): ?HostGroup;

    /**
     * Find one host group with access groups.
     *
     * @param int $hostGroupId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return HostGroup|null
     */
    public function findOneByAccessGroups(int $hostGroupId, array $accessGroups): ?HostGroup;

    /**
     * Tells whether the host group exists.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsOne(int $hostGroupId): bool;

    /**
     * Tells whether the host group exists but with access groups.
     *
     * @param int $hostGroupId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsOneByAccessGroups(int $hostGroupId, array $accessGroups): bool;

    /**
     * Check existence of a list of host groups.
     * Return the ids of the existing groups.
     *
     * @param int[] $hostGroupIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $hostGroupIds): array;

    /**
     * Check existence of a list of host groups by access groups.
     * Return the ids of the existing groups.
     *
     * @param int[] $hostGroupIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function existByAccessGroups(array $hostGroupIds, array $accessGroups): array;

    /**
     * Tells whether the host group name already exists.
     * This method does not need an acl version of it.
     *
     * @param string $hostGroupName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function nameAlreadyExists(string $hostGroupName): bool;

    /**
     * Find host groups linked to a host (no ACLs).
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return HostGroup[]
     */
    public function findByHost(int $hostId): array;

    /**
     * Find host groups linked to a host by access groups.
     *
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return HostGroup[]
     */
    public function findByHostAndAccessGroups(int $hostId, array $accessGroups): array;

    /**
     * Find host groups by their ID.
     *
     * @param int ...$hostGroupIds
     *
     * @throws \Throwable
     *
     * @return list<HostGroup>
     */
    public function findByIds(int ...$hostGroupIds): array;

    /**
     * Find Host Groups names by their IDs.
     *
     * @param int[] $hostGroupIds
     *
     * @return HostGroupNamesById
     */
    public function findNames(array $hostGroupIds): HostGroupNamesById;

    /**
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function hasAccessToAllHostGroups(array $accessGroupIds): bool;
}
