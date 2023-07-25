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

use Core\Security\AccessGroup\Domain\Model\AccessGroup;

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
}
