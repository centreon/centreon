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
use Core\Dashboard\Domain\Model\Metric\ResourceMetric;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadDashboardPerformanceMetricRepositoryInterface
{
    /**
     * Get metrics filtered by request parameters with the count of total metrics.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @return ResourceMetric[]
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Get metrics filtered by request parameters and accessgroups with the count of total metrics.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @return ResourceMetric[]
     */
    public function FindByRequestParametersAndAccessGroups(RequestParametersInterface $requestParameters, array $accessGroups): array;
}
