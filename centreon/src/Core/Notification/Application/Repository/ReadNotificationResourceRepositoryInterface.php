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

namespace Core\Notification\Application\Repository;

use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadNotificationResourceRepositoryInterface
{
    /**
     * Indicate whether the IDs provided exist (without ACLs)
     * Return an array of the existing resource IDs out of the provided ones.
     *
     * @param int[] $resourceIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $resourceIds): array;

    /**
     * Indicate whether the IDs provided exist (with ACLs)
     * Return an array of the existing resource IDs out of the provided ones.
     *
     * @param int[] $resourceIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function existByAccessGroups(array $resourceIds, array $accessGroups): array;

    /**
     * Retrieve a notification resource by notification ID.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return null|NotificationResource
     */
    public function findByNotificationId(int $notificationId): ?NotificationResource;

    /**
     * Retrieve a notification resource by notification ID and AccessGroups.
     *
     * @param int $notificationId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return null|NotificationResource
     */
    public function findByNotificationIdAndAccessGroups(
        int $notificationId,
        array $accessGroups
    ): ?NotificationResource;

    /**
     * Count the Resource by their notification id.
     *
     * @param non-empty-array<int> $notificationIds
     * @param AccessGroup[] $accessGroups
     *
     * @return array<int,int> [notification_id => resource_count]
     */
    public function countResourcesByNotificationIdsAndAccessGroups(
        array $notificationIds,
        array $accessGroups
    ): array;

    /**
     * Count the Resource by their notification id.
     *
     * @param non-empty-array<int> $notificationIds
     *
     * @return array<int,int> [notification_id => resource_count]
     */
    public function countResourcesByNotificationIds(array $notificationIds): array;
}
