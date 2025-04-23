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

namespace Core\ServiceGroup\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupNamesById;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;

interface ReadServiceGroupRepositoryInterface
{
    /**
     * Find All service groups without acl.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return \Traversable<int, ServiceGroup>&\Countable
     */
    public function findAll(?RequestParametersInterface $requestParameters): \Traversable&\Countable;

    /**
     * Find All service groups with access groups.
     *
     * @param RequestParametersInterface|null $requestParameters
     * @param int[] $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return \Traversable<int, ServiceGroup>&\Countable
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds): \Traversable&\Countable;

    /**
     * Find service groups by their ID.
     *
     * @param int ...$serviceGroupIds
     *
     * @throws \Throwable
     *
     * @return list<ServiceGroup>
     */
    public function findByIds(int ...$serviceGroupIds): array;

    /**
     * Find one service group without acl.
     *
     * @param int $serviceGroupId
     *
     * @throws \Throwable
     *
     * @return ServiceGroup|null
     */
    public function findOne(int $serviceGroupId): ?ServiceGroup;

    /**
     * Find one service group with access groups.
     *
     * @param int $serviceGroupId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return ServiceGroup|null
     */
    public function findOneByAccessGroups(int $serviceGroupId, array $accessGroups): ?ServiceGroup;

    /**
     * Tells whether the service group exists.
     *
     * @param int $serviceGroupId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsOne(int $serviceGroupId): bool;

    /**
     * Tells whether the service group exists but with access groups.
     *
     * @param int $serviceGroupId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsOneByAccessGroups(int $serviceGroupId, array $accessGroups): bool;

    /**
     * Tells whether the service group name already exists.
     * This method does not need an acl version of it.
     *
     * @param string $serviceGroupName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function nameAlreadyExists(string $serviceGroupName): bool;

    /**
     * Find all existing service groups ids.
     *
     * @param list<int> $serviceGroupIds
     *
     * @throws \Throwable
     *
     * @return list<int>
     */
    public function exist(array $serviceGroupIds): array;

    /**
     * Find all existing service groups ids and according to access groups.
     *
     * @param list<int> $serviceGroupIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return list<int>
     */
    public function existByAccessGroups(array $serviceGroupIds, array $accessGroups): array;

    /**
     * Find all service groups linked to a service.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}>
     */
    public function findByService(int $serviceId): array;

    /**
     * Find all service groups linked to a service and according to access groups.
     *
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}>
     */
    public function findByServiceAndAccessGroups(int $serviceId, array $accessGroups): array;

    /**
     * Find service group names by their IDs.
     *
     * @param int[] $serviceGroupIds
     *
     * @throws \Throwable
     *
     * @return ServiceGroupNamesById
     */
    public function findNames(array $serviceGroupIds): ServiceGroupNamesById;

    /**
     * Determine if accessGroups give access to all serviceGroups
     * true: all service groups are accessible
     * false: all service groups are NOT accessible.
     *
     * @param int[] $accessGroupIds
     *
     * @return bool
     */
    public function hasAccessToAllServiceGroups(array $accessGroupIds): bool;
}
