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
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadDashboardRepositoryInterface
{
    /**
     * Find all dashboards.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return Dashboard[]
     */
    public function findByRequestParameter(?RequestParametersInterface $requestParameters): array;

    /**
     * Find all dashboards by access groups.
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return Dashboard[]
     */
    public function findByRequestParameterAndAccessGroups(
        array $accessGroups,
        ?RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find one dashboard without acl.
     *
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return Dashboard|null
     */
    public function findOne(int $dashboardId): ?Dashboard;

    /**
     * Find one dashboard with access groups.
     *
     * @param int $dashboardId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return Dashboard|null
     */
    public function findOneByAccessGroups(int $dashboardId, array $accessGroups): ?Dashboard;
}
