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

namespace Core\Service\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\Service\Domain\Model\ServiceLight;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\Service\Domain\Model\ServiceRelation;
use Core\Service\Domain\Model\TinyService;

interface ReadServiceRepositoryInterface
{
    /**
     * Indicates whether the service already exists.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $serviceId): bool;

    /**
     * Indicates whether the services already exists.
     *
     * @param int[] $serviceIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $serviceIds): array;

    /**
     * Indicates whether the service already exists by access groups.
     *
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $serviceId, array $accessGroups): bool;

    /**
     * Retrieve the monitoring server id related to the service.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function findMonitoringServerId(int $serviceId): int;

    /**
     * Find all service IDs link to the host.
     *
     * @param int $hostId Host ID for which the services are linked
     *
     * @throws \Throwable
     *
     * @return list<int>
     */
    public function findServiceIdsLinkedToHostId(int $hostId): array;

    /**
     * Indicates whether the service name already exists.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return ServiceNamesByHost|null
     */
    public function findServiceNamesByHost(int $hostId): ?ServiceNamesByHost;

    /**
     * Find one service.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return Service|null
     */
    public function findById(int $serviceId): ?Service;

    /**
     * Find services based on given IDs.
     *
     * @param int ...$serviceIds
     *
     * @throws \Throwable
     *
     * @return list<TinyService>
     */
    public function findByIds(int ...$serviceIds): array;

    /**
     * Find all services.
     *
     * @throws \Throwable
     *
     * @return \Traversable<int, TinyService>&\Countable
     */
    public function findAll(): \Traversable&\Countable;

    /**
     * Retrieves all service inheritances from a service.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return ServiceInheritance[]
     */
    public function findParents(int $serviceId): array;

    /**
     * Find all services by request parameter.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceLight[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * Find all services by request parameters and access groups.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return ServiceLight[]
     */
    public function findByRequestParameterAndAccessGroup(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array;

    /**
     * Find services relations with host group.
     *
     * param int $hostGroupId
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return ServiceRelation[]
     */
    public function findServiceRelationsByHostGroupId(int $hostGroupId): array;

    /**
     * Find a service name by its ID.
     *
     * @param int $serviceId
     *
     * @throws \Throwable
     *
     * @return string|null
     */
    public function findNameById(int $serviceId): ?string;
}
