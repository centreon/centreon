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

namespace Core\MonitoringServer\Application\Repository;

use Core\MonitoringServer\Model\MonitoringServer;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadMonitoringServerRepositoryInterface {
    /**
     * Determine if a monitoring server exists by its ID.
     *
     * @param int $monitoringServerId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $monitoringServerId): bool;

    /**
     * Determine if a monitoring server exists by its ID and access groups.
     *
     * @param int $monitoringServerId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $monitoringServerId, array $accessGroups): bool;

    /**
     * Get a monitoring server by its associated host ID.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return MonitoringServer|null
     */
    public function findByHost(int $hostId): ?MonitoringServer;

    /**
     * Get monitoring servers by their IDs.
     *
     * @param int[] $ids
     *
     * @throws \Throwable
     *
     * @return MonitoringServer[]
     */
    public function findByIds(array $ids): array;
}
