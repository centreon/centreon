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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Notification\Application\UseCase\FindNotifications;

class NotificationCounts
{
    public function __construct(
        private readonly array $notificationsUsersCount,
        private readonly array $hostgroupResourcesCount,
        private readonly array $servicegroupResourcesCount,
    ) {
    }

    public function getUsersCountByNotificationId(int $notificationId): int
    {
        return $this->notificationsUsersCount[$notificationId];
    }

    public function getHostgroupResourcesCountByNotificationId(int $notificationId): ?int
    {
        if (array_key_exists($notificationId, $this->hostgroupResourcesCount)) {
            return $this->hostgroupResourcesCount[$notificationId];
        }

        return null;
    }

    public function getServicegroupResourcesCountByNotificationId(int $notificationId): ?int
    {
        if (array_key_exists($notificationId, $this->servicegroupResourcesCount)) {
            return $this->servicegroupResourcesCount[$notificationId];
        }

        return null;
    }
}