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

namespace Core\Infrastructure\RealTime\Repository\Host;

use Core\Domain\RealTime\Model\Host;
use Core\Infrastructure\Common\Repository\DbFactoryUtilitiesTrait;
use Core\Infrastructure\RealTime\Repository\Icon\DbIconFactory;

/**
 * @phpstan-type _dataHost array{
 *     host_id: int|string|null,
 *     name: int|string|null,
 *     address: int|string|null,
 *     monitoring_server_name: int|string|null,
 *     timezone: string|null,
 *     performance_data: string|null,
 *     output: string|null,
 *     command_line: string|null,
 *     flapping: int|string|null,
 *     acknowledged: int|string|null,
 *     nb_downtime: int|string|null,
 *     passive_checks: int|string|null,
 *     active_checks: int|string|null,
 *     latency: int|string|null,
 *     execution_time: int|string|null,
 *     status_change_percentage: int|string|null,
 *     notify: int|string|null,
 *     notification_number: int|string|null,
 *     last_status_change: int|string|null,
 *     last_notification: int|string|null,
 *     last_check: int|string|null,
 *     last_time_up: int|string|null,
 *     max_check_attempts: int|string|null,
 *     check_attempt: int|string|null,
 *     alias: string|null,
 *     next_check: int|string|null,
 *     icon_name: string|null,
 *     icon_url: string|null,
 * }
 */
class DbHostFactory
{
    use DbFactoryUtilitiesTrait;

    /**
     * @param _dataHost $data
     *
     * @return Host
     */
    public static function createFromRecord(array $data): Host
    {
        $host = new Host(
            (int) $data['host_id'],
            (string) $data['name'],
            (string) $data['address'],
            (string) $data['monitoring_server_name'],
            DbHostStatusFactory::createFromRecord($data)
        );

        $host->setTimezone($data['timezone'])
            ->setPerformanceData($data['performance_data'])
            ->setOutput($data['output'])
            ->setCommandLine($data['command_line'])
            ->setIsFlapping((int) $data['flapping'] === 1)
            ->setIsAcknowledged((int) $data['acknowledged'] === 1)
            ->setIsInDowntime((int) $data['nb_downtime'] > 0)
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
            ->setLastTimeUp(self::createDateTimeFromTimestamp(is_numeric($data['last_time_up']) ? (int) $data['last_time_up'] : null))
            ->setMaxCheckAttempts(self::getIntOrNull($data['max_check_attempts']))
            ->setCheckAttempts(self::getIntOrNull($data['check_attempt']))
            ->setAlias($data['alias']);

        $nextCheck = self::createDateTimeFromTimestamp(
            (int) $data['active_checks'] === 1 ? (int) $data['next_check'] : null
        );

        $host->setNextCheck($nextCheck);
        $host->setIcon(DbIconFactory::createFromRecord($data));

        return $host;
    }
}
