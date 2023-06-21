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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationMessage;
use Core\Notification\Domain\Model\ConfigurationUser;

interface ReadNotificationRepositoryInterface
{
    /**
     * Find one notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return Notification|null
     */
    public function findById(int $notificationId): ?Notification;

    /**
     * Find one notification by its name.
     *
     * @param TrimmedString $notificationName
     *
     * @throws \Throwable
     *
     * @return Notification|null
     */
    public function findByName(TrimmedString $notificationName): ?Notification;

    /**
     * Find notification message for a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return NotificationMessage[]
     */
    public function findMessagesByNotificationId(int $notificationId): array;

    /**
     * Find notification channels for multiple notification.
     *
     * @param non-empty-array<int> $notificationIds
     *
     * @return array<int, NotificationChannel[]> [notification_id => ["Slack","Sms","Email"]]
     */
    public function findNotificationChannelsByNotificationIds(array $notificationIds): array;

    /**
     * Find notification users for a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return ConfigurationUser[]
     */
    public function findUsersByNotificationId(int $notificationId): array;

    /**
     * Find notification users for a notification.
     *
     * @param non-empty-array<int> $notificationIds
     *
     * @throws \Throwable
     *
     * @return array<int,int> [notification_id => user_count]
     */
    public function findUsersCountByNotificationIds(array $notificationIds): array;

    /**
     * Tells whether the notification exists.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $notificationId): bool;

    /**
     * Tells whether the notification name already exists.
     * This method does not need an acl version of it.
     *
     * @param TrimmedString $notificationName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $notificationName): bool;

    /**
     * Return all the notifications.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return Notification[]
     */
    public function findAll(?RequestParametersInterface $requestParameters): array;
}
