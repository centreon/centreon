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

namespace Core\Notification\Infrastructure\Repository;

use Core\Notification\Domain\Model\NotificationHost;
use Core\Notification\Domain\Model\NotifiableResource;
use Core\Notification\Domain\Model\NotificationService;
use Core\Notification\Domain\Model\NotificationHostEvent;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;

class DbNotifiableResourceFactory
{
    /**
     * @param array<string,int|string> $records
     *
     * @return array<NotifiableResource>
     */
    public static function createFromRecords(array $records): array
    {
        $notifiableResources = [];
        $currentNotificationId = null;
        $currentRecords = [];
        foreach ($records as $record) {
            if ($currentNotificationId === null) {
                $currentNotificationId = $record['notification_id'];
                $currentRecords[] = $record;
                continue;
            }

            if ($currentNotificationId === $record['notification_id']) {
                $currentRecords[] = $record;
                continue;
            }

            $notifiableResources[] = self::createNotifiableResourceFromRecord($currentNotificationId, $currentRecords);
        }

        return $notifiableResources;
    }

    /**
     * @param int $notificationId
     * @param array<string,int|string> $records
     *
     * @return NotifiableResource
     */
    private static function createNotifiableResourceFromRecord(int $notificationId, array $records): NotifiableResource
    {
        $notificationHosts = [];
        $currentHostId = null;
        $currentRecords = [];
        foreach ($records as $record) {
            if ($currentHostId === null) {
                $currentHostId = $record['host_id'];
                $currentRecords[] = $record;
                continue;
            }

            if ($currentHostId === $record['host_id']) {
                $currentRecords[] = $record;
                continue;
            }

            if ($record['host_events'] !== 0) {
                $currentHostEvents = NotificationHostEventConverter::fromBitFlags((int) $record['host_events']);
            } else {
                $currentHostEvents = [];
            }

            $notificationHosts[] = self::createNotificationHostFromRecord(
                $currentHostId,
                $record['host_name'],
                $record['host_alias'],
                $currentHostEvents,
                $currentRecords
            );
        }

        return new NotifiableResource($notificationId, $notificationHosts);
    }

    /**
     * @param int $hostId
     * @param string $hostName
     * @param string $hostAlias
     * @param array<NotificationHostEvent> $hostEvents
     * @param array<string,int|string> $records
     *
     * @return NotificationHost
     */
    private static function createNotificationHostFromRecord(
        int $hostId,
        string $hostName,
        string $hostAlias,
        array $hostEvents,
        array $records
    ): NotificationHost {
        $notificationServices = [];
        $currentServiceId = null;
        $currentRecords = [];
        foreach ($records as $record) {
            if ($currentServiceId === null) {
                $currentServiceId = $record['service_id'];
                $currentRecords[] = $record;
                continue;
            }

            if ($currentServiceId === $record['service_id']) {
                $currentRecords[] = $record;
                continue;
            }

            if ($record['service_events'] !== 0) {
                $currentServiceEvents = NotificationServiceEventConverter::fromBitFlags(
                    (int) $record['service_events']
                );
            } else if ($record['included_service_events'] !== 0) {
                $currentServiceEvents = NotificationServiceEventConverter::fromBitFlags(
                    (int) $record['included_service_events']
                );
            } else {
                $currentServiceEvents = [];
            }

            $notificationServices[] = new NotificationService(
                $currentServiceId,
                $record['service_name'],
                $record['service_alias'],
                $currentServiceEvents
            );
        }

        return new NotificationHost($hostId, $hostName, $hostAlias, $hostEvents, $notificationServices);
    }
}
