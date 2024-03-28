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

namespace Core\Infrastructure\RealTime\Repository\Service;

use Core\Domain\RealTime\Model\Service;
use Core\Infrastructure\Common\Repository\DbFactoryUtilitiesTrait;
use Core\Infrastructure\RealTime\Repository\Icon\DbIconFactory;

/**
 * @phpstan-type _dataService array{
 *     service_id: int|string|null,
 *     host_id: int|string|null,
 *     description: int|string|null,
 *     performance_data: string|null,
 *     output: string|null,
 *     command_line: string|null,
 *     flapping: int|string|null,
 *     acknowledged: int|string|null,
 *     in_downtime: int|string|null,
 *     passive_checks: int|string|null,
 *     active_checks: int|string|null,
 *     latency: int|string|null,
 *     execution_time: int|string|null,
 *     status_change_percentage: int|string|null,
 *     notify: int|string|null,
 *     notification_number: int|string|null,
 *     last_status_change: int|string|null,
 *     last_status_change: int|string|null,
 *     last_notification: int|string|null,
 *     last_notification: int|string|null,
 *     last_check: int|string|null,
 *     last_check: int|string|null,
 *     last_time_ok: int|string|null,
 *     last_time_ok: int|string|null,
 *     max_check_attempts: int|string|null,
 *     check_attempt: int|string|null,
 *     has_graph_data: int|string|null,
 *     active_checks: int|string|null,
 *     next_check: int|string|null,
 *     icon_name: string|null,
 *     icon_url: string|null,
 * }
 */
class DbServiceFactory
{
    use DbFactoryUtilitiesTrait;

    /**
     * @param _dataService $data
     *
     * @return Service
     */
    public static function createFromRecord(array $data): Service
    {
        $service = new Service(
            (int) $data['service_id'],
            (int) $data['host_id'],
            (string) $data['description'],
            DbServiceStatusFactory::createFromRecord($data)
        );

        $service->setPerformanceData($data['performance_data'])
            ->setOutput($data['output'])
            ->setCommandLine($data['command_line'])
            ->setIsFlapping((int) $data['flapping'] === 1)
            ->setIsAcknowledged((int) $data['acknowledged'] === 1)
            ->setIsInDowntime((int) $data['in_downtime'] > 0)
            ->setPassiveChecks((int) $data['passive_checks'] === 1)
            ->setActiveChecks((int) $data['active_checks'] === 1)
            ->setLatency(self::getFloatOrNull($data['latency']))
            ->setExecutionTime(self::getFloatOrNull($data['execution_time']))
            ->setStatusChangePercentage(self::getFloatOrNull($data['status_change_percentage']))
            ->setNotificationEnabled((int) $data['notify'] === 1)
            ->setNotificationNumber(self::getIntOrNull($data['notification_number']))
            ->setLastStatusChange(self::createDateTimeFromTimestamp(is_numeric($data['last_status_change']) ? (int) $data['last_status_change'] : null))
            ->setLastNotification(self::createDateTimeFromTimestamp(is_numeric($data['last_notification']) ? (int) $data['last_notification'] : null))
            ->setLastCheck(self::createDateTimeFromTimestamp(is_numeric($data['last_check']) ? (int) $data['last_check'] : null))
            ->setLastTimeOk(self::createDateTimeFromTimestamp(is_numeric($data['last_time_ok']) ? (int) $data['last_time_ok'] : null))
            ->setMaxCheckAttempts(self::getIntOrNull($data['max_check_attempts']))
            ->setCheckAttempts(self::getIntOrNull($data['check_attempt']))
            ->setHasGraphData((int) $data['has_graph_data'] === 1);

        $nextCheck = self::createDateTimeFromTimestamp(
            (int) $data['active_checks'] === 1 ? (int) $data['next_check'] : null
        );

        $service->setNextCheck($nextCheck);
        $service->setIcon(DbIconFactory::createFromRecord($data));

        return $service;
    }
}
