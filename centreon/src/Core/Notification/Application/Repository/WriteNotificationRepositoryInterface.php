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

use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationMessage;

interface WriteNotificationRepositoryInterface
{
    /**
     * Add a notification
     * Return the id of the notification.
     *
     * @param NewNotification $notification
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewNotification $notification): int;

    /**
     * Add messages to a notification.
     *
     * @param int $notificationId
     * @param NotificationMessage[] $messages
     *
     * @throws \Throwable
     */
    public function addMessages(int $notificationId, array $messages): void;

    /**
     * Add users to a notification.
     *
     * @param int $notificationId
     * @param int[] $userIds
     *
     * @throws \Throwable
     */
    public function addUsers(int $notificationId, array $userIds): void;

    /**
     * Update notification.
     *
     * @param Notification $notification
     *
     * @throws \Throwable
     */
    public function update(Notification $notification): void;

    /**
     * delete all the messages of a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    public function deleteMessages(int $notificationId): void;

    /**
     * delete all the users of a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    public function deleteUsers(int $notificationId): void;
}
