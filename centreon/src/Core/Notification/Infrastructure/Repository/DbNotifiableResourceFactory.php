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

use Core\Notification\Application\Converter\{NotificationHostEventConverter, NotificationServiceEventConverter};
use Core\Notification\Domain\Model\{NotifiableHost, NotifiableResource, NotifiableService, NotificationHostEvent};

class DbNotifiableResourceFactory
{
    /**
     * @param array<int,array{
     *  notification_id: int,
     *  host_id: int,
     *  host_name: string,
     *  host_alias: string|null,
     *  host_events: int,
     *  service_id: int,
     *  service_name: string,
     *  service_alias: string,
     *  service_events: int,
     *  included_service_events: int
     * }> $records
     *
     * @throws \Throwable
     *
     * @return array<NotifiableResource>
     */
    public static function createFromRecords(array $records): array
    {
        $notifiableResources = [];
        $currentNotificationId = 0;
        $currentRecords = [];
        foreach ($records as $record) {
            if ($currentNotificationId === 0) {
                $currentNotificationId = $record['notification_id'];
                $currentRecords[] = $record;
                continue;
            }

            if ($currentNotificationId === $record['notification_id']) {
                $currentRecords[] = $record;
                continue;
            }

            $notifiableResources[] = self::createNotifiableResourceFromRecord($currentNotificationId, $currentRecords);

            $currentRecords = [];
            $currentRecords[] = $record;
            $currentNotificationId = $record['notification_id'];
        }

        $notifiableResources[] = self::createNotifiableResourceFromRecord($currentNotificationId, $currentRecords);

        return $notifiableResources;
    }

    /**
     * @param int $notificationId
     * @param array<int,array{
     *  notification_id: int,
     *  host_id: int,
     *  host_name: string,
     *  host_alias: string|null,
     *  host_events: int,
     *  service_id: int,
     *  service_name: string,
     *  service_alias: string,
     *  service_events: int,
     *  included_service_events: int
     * }> $records
     *
     * @throws \Throwable
     *
     * @return NotifiableResource
     */
    private static function createNotifiableResourceFromRecord(int $notificationId, array $records): NotifiableResource
    {
        $notificationHosts = [];
        $currentHostId = 0;
        $currentRecords = [];
        $currentHostEvents = [];
        $index = 0;
        foreach ($records as $record) {
            if ($currentHostId === 0) {
                $currentHostId = $record['host_id'];
                $currentRecords[] = $record;
                $index++;
                continue;
            }

            if ($currentHostId === $record['host_id']) {
                $currentRecords[] = $record;
                $index++;
                continue;
            }

            if ($record['host_events'] !== '0') {
                $currentHostEvents = NotificationHostEventConverter::fromBitFlags((int) $record['host_events']);
            }

            $notificationHosts[] = self::createNotificationHostFromRecord(
                $currentHostId,
                $currentRecords[$index - 1]['host_name'],
                $currentRecords[$index - 1]['host_alias'],
                $currentHostEvents,
                $currentRecords
            );

            $currentRecords = [];
            $index = 1;
            $currentRecords[] = $record;
            $currentHostId = $record['host_id'];
        }

        if ($currentRecords[$index - 1]['host_events'] !== '0') {
            $currentHostEvents = NotificationHostEventConverter::fromBitFlags(
                $currentRecords[$index - 1]['host_events']
            );
        }

        $notificationHosts[] = self::createNotificationHostFromRecord(
            $currentHostId,
            $currentRecords[$index - 1]['host_name'],
            $currentRecords[$index - 1]['host_alias'],
            $currentHostEvents,
            $currentRecords
        );

        return new NotifiableResource($notificationId, $notificationHosts);
    }

    /**
     * @param int $hostId
     * @param string $hostName
     * @param string|null $hostAlias
     * @param array<NotificationHostEvent> $hostEvents
     * @param array<int,array{
     *  notification_id: int,
     *  host_id: int,
     *  host_name: string,
     *  host_alias: string|null,
     *  host_events: int,
     *  service_id: int,
     *  service_name: string,
     *  service_alias: string,
     *  service_events: int,
     *  included_service_events: int
     * }> $records
     *
     * @throws \Throwable
     *
     * @return NotifiableHost
     */
    private static function createNotificationHostFromRecord(
        int $hostId,
        string $hostName,
        ?string $hostAlias,
        array $hostEvents,
        array $records
    ): NotifiableHost {
        $notificationServices = [];
        $currentServiceEvents = [];
        foreach ($records as $record) {
            if ($record['service_events'] !== '0') {
                $currentServiceEvents = NotificationServiceEventConverter::fromBitFlags(
                    (int) $record['service_events']
                );
            }

            if ($record['included_service_events'] !== '0') {
                $currentServiceEvents = NotificationServiceEventConverter::fromBitFlags(
                    (int) $record['included_service_events']
                );
            }

            if ([] === $currentServiceEvents) {
                continue;
            }

            $notificationServices[] = new NotifiableService(
                (int) $record['service_id'],
                $record['service_name'],
                $record['service_alias'],
                $currentServiceEvents
            );
        }

        return new NotifiableHost($hostId, $hostName, $hostAlias, $hostEvents, $notificationServices);
    }
}
