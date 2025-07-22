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

namespace Core\Dashboard\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadDashboardPerformanceMetricRepositoryInterface
{
    /**
     * Get metrics filtered by request parameters.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RepositoryException
     *
     * @return ResourceMetric[]
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Get metrics filtered by request parameters and accessgroups.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws RepositoryException
     *
     * @return ResourceMetric[]
     */
    public function findByRequestParametersAndAccessGroups(RequestParametersInterface $requestParameters, array $accessGroups): array;

    /**
     * Get metrics filtered by request parameters.
     *
     * @param RequestParametersInterface $requestParameters
     * @param string $metricName
     *
     * @throws RepositoryException
     *
     * @return ResourceMetric[]
     */
    public function findByRequestParametersAndMetricName(RequestParametersInterface $requestParameters, string $metricName): array;

    /**
     * Get metrics filtered by request parameters and accessgroups.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     * @param string $metricName
     *
     * @throws RepositoryException
     *
     * @return ResourceMetric[]
     */
    public function findByRequestParametersAndAccessGroupsAndMetricName(RequestParametersInterface $requestParameters, array $accessGroups, string $metricName): array;
}
