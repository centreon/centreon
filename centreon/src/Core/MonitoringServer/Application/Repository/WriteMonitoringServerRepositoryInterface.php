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

use Core\MonitoringServer\Model\MonitoringServer;

interface WriteMonitoringServerRepositoryInterface
{
    /**
     * Define the monitoring server as changed since its last configuration export.
     *
     * @param int $monitoringServerId
     */
    public function notifyConfigurationChange(int $monitoringServerId): void;

    /**
     * Notify the monitoring servers as changed.
     *
     * @param int[] $monitoringServerIds
     */
    public function notifyConfigurationChanges(array $monitoringServerIds): void;

    /**
     * Update a monitoring server.
     *
     * @param MonitoringServer $monitoringServer
     */
    public function update(MonitoringServer $monitoringServer): void;

    /**
     * Update the encryption readiness of Monitoring Server configuration,
     * based on the value of the Monitoring Server readiness in real time.
     *
     * This ensure that the configuration is always up to date with the realtime.
     */
    public function updateAllEncryptionReadyFromRealtime(): void;
}
