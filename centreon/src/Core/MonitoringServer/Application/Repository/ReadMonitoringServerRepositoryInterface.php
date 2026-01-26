<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Exception\EntityNotFoundException;
use Core\MonitoringServer\Model\MonitoringServer;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadMonitoringServerRepositoryInterface
{
    /**
     * Determine if a monitoring server exists by its ID.
     *
     * @param int $monitoringServerId
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
     * @return bool
     */
    public function existsByAccessGroups(int $monitoringServerId, array $accessGroups): bool;

    /**
     * Determine if monitoring servers exist by their IDs.
     * Return the ids found.
     *
     * @param int[] $monitoringServerIds
     *
     * @return int[]
     */
    public function exist(array $monitoringServerIds): array;

    /**
     * Determine if monitoring servers exist by their IDs and access groups.
     * Return the ids found.
     *
     * @param int[] $monitoringServerIds
     * @param AccessGroup[] $accessGroups
     *
     * @return int[]
     */
    public function existByAccessGroups(array $monitoringServerIds, array $accessGroups): array;

    /**
     * Get a monitoring server by its associated host ID.
     *
     * @param int $hostId
     *
     * @return MonitoringServer|null
     */
    public function findByHost(int $hostId): ?MonitoringServer;

    /**
     * Get monitoring servers by their IDs.
     *
     * @param int[] $ids
     *
     * @return MonitoringServer[]
     */
    public function findByIds(array $ids): array;

    /**
     * Find all the Monitoring Servers.
     *
     * @return MonitoringServer[]
     */
    public function findAll(): array;

    /**
     * Get a monitoring server.
     *
     * @param int $monitoringServerId
     *
     * @throws EntityNotFoundException when no Monitoring server are found
     * @return MonitoringServer
     */
    public function get(int $monitoringServerId): MonitoringServer;
}
