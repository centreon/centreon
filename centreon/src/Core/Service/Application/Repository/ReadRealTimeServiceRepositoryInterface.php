<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use Core\Common\Domain\Exception\RepositoryException;
use Core\Service\Domain\Model\ServiceStatusesCount;

interface ReadRealTimeServiceRepositoryInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return string[]
     */
    public function findUniqueServiceNamesByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param int[] $accessGroupIds
     *
     * @return string[]
     */
    public function findUniqueServiceNamesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds,
    ): array;

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @return ServiceStatusesCount
     */
    public function findStatusesByRequestParameters(RequestParametersInterface $requestParameters): ServiceStatusesCount;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param int[] $accessGroupIds
     *
     * @return ServiceStatusesCount
     */
    public function findStatusesByRequestParametersAndAccessGroupIds(
        RequestParametersInterface $requestParameters,
        array $accessGroupIds,
    ): ServiceStatusesCount;

    /**
     * Indicates whether the service already exists for the given service ID and host ID
     *
     * @param int $serviceId
     * @param int $hostId
     *
     * @throws RepositoryException
     *
     * @return bool
     */
    public function exists(int $serviceId, int $hostId): bool;

    /**
     * Indicates whether the service already exists for a meta service ID
     *
     * @param int $metaServiceId
     *
     * @throws RepositoryException
     *
     * @return array<int>|false
     */
    public function existsByDescription(int $metaServiceId): array|false;
}
