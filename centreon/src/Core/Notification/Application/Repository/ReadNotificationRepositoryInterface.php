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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Contact;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Notification;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

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
     * @return Message[]
     */
    public function findMessagesByNotificationId(int $notificationId): array;

    /**
     * Find notification channels for multiple notification.
     *
     * @param non-empty-array<int> $notificationIds
     *
     * @return array<int, Channel[]> [notification_id => ["Slack","Sms","Email"]]
     */
    public function findNotificationChannelsByNotificationIds(array $notificationIds): array;

    /**
     * Find notification users and those defined in contact groups.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return array<int, Contact>
     */
    public function findUsersByNotificationId(int $notificationId): array;

    /**
     * Find notification users and those defined in contact groups by access groups and user based on ACL.
     *
     * @param int $notificationId
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return array<int, Contact>
     */
    public function findUsersByNotificationIdAndAccessGroups(
        int $notificationId,
        ContactInterface $user,
        array $accessGroups
    ): array;

    /**
     * Find notification users for a list of contact group Ids.
     *
     * @param int ...$contactGroupIds
     *
     * @throws \Throwable
     *
     * @return array<int, Contact>
     */
    public function findUsersByContactGroupIds(int ...$contactGroupIds): array;

    /**
     * Find notification Contact Groups for a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return ContactGroup[]
     */
    public function findContactGroupsByNotificationId(int $notificationId): array;

    /**
     * Count notification users for notifications.
     *
     * @param int[] $notificationIds
     *
     * @throws \Throwable
     *
     * @return array<int,int> [notification_id => user_count]
     */
    public function countContactsByNotificationIds(array $notificationIds): array;

    /**
     * Count notification users for notifications by access groups and current user based on ACL.
     *
     * @param int[] $notificationIds
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return array<int,int> [notification_id => user_count]
     */
    public function countContactsByNotificationIdsAndAccessGroup(
        array $notificationIds,
        ContactInterface $user,
        array $accessGroups
    ): array;

    /**
     * Find notification Contact Groups linked to a given user for a notification.
     *
     * @param int $notificationId
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return ContactGroup[]
     */
    public function findContactGroupsByNotificationIdAndAccessGroups(
        int $notificationId,
        ContactInterface $user,
        array $accessGroups
    ): array;

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
