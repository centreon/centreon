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

use Core\Dashboard\Domain\Model\DashboardPanel;

interface ReadDashboardPanelRepositoryInterface
{
    /**
     * Find all panels IDs of a specific dashboard.
     *
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function findPanelIdsByDashboardId(int $dashboardId): array;

    /**
     * Find all panels of a specific dashboard.
     *
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return DashboardPanel[]
     */
    public function findPanelsByDashboardId(int $dashboardId): array;
}
