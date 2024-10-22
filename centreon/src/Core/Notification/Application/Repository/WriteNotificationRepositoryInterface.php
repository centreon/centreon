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

use Centreon\Domain\Repository\RepositoryException;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\Notification;

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
    public function addNewNotification(NewNotification $notification): int;

    /**
     * Add messages to a notification.
     *
     * @param int $notificationId
     * @param Message[] $messages
     *
     * @throws \Throwable
     */
    public function addMessagesToNotification(int $notificationId, array $messages): void;

    /**
     * Add users to a notification.
     *
     * @param int $notificationId
     * @param int[] $userIds
     *
     * @throws \Throwable
     */
    public function addUsersToNotification(int $notificationId, array $userIds): void;

    /**
     * Add Contact Groups to a notification.
     *
     * @param int $notificationId
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable
     */
    public function addContactGroupsToNotification(int $notificationId, array $contactGroupIds): void;

    /**
     * Update notification.
     *
     * @param Notification $notification
     *
     * @throws \Throwable
     */
    public function updateNotification(Notification $notification): void;

    /**
     * delete all the messages of a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    public function deleteNotificationMessages(int $notificationId): void;

    /**
     * delete all the users of a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    public function deleteUsersFromNotification(int $notificationId): void;

    /**
     * delete all the contactGroups of a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    public function deleteContactGroupsFromNotification(int $notificationId): void;

    /**
     * delete the given contactGroups of a notification.
     *
     * @param int $notificationId
     * @param int[] $contactGroupsIds
     *
     * @throws \Throwable
     */
    public function deleteContactGroupsByNotificationAndContactGroupIds(
        int $notificationId,
        array $contactGroupsIds
    ): void;

    /**
     * Delete a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable|RepositoryException
     *
     * @return int
     */
    public function deleteNotification(int $notificationId): int;
}
