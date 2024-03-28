<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Metric\Application\Repository;

use Centreon\Domain\Monitoring\Service;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Metric\Domain\Model\Metric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadMetricRepositoryInterface
{
    /**
     * @param int $indexId
     *
     * @return array<Metric>
     */
    public function findMetricsByIndexId(int $indexId): array;

    /**
     * Find Service by Metric Names.
     *
     * @param string[] $metricNames
     * @param RequestParametersInterface $requestParameters
     *
     * @return Service[]
     */
    public function findServicesByMetricNamesAndRequestParameters(
        array $metricNames,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find Service by Metric Names and Access Groups.
     *
     * @param string[] $metricNames
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface $requestParameters
     *
     * @return Service[]
     */
    public function findServicesByMetricNamesAndAccessGroupsAndRequestParameters(
        array $metricNames,
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find Metrics by their host and service id.
     *
     * @param int $hostId
     * @param int $serviceId
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return Metric[]
     */
    public function findByHostIdAndServiceId(
        int $hostId,
        int $serviceId,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find Metrics by their host, service id and user access groups.
     *
     * @param int $hostId
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return Metric[]
     */
    public function findByHostIdAndServiceIdAndAccessGroups(
        int $hostId,
        int $serviceId,
        array $accessGroups,
        RequestParametersInterface $requestParameters
    ): array;
}
