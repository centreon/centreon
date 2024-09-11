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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\Notification;

class DbWriteNotificationRepository extends AbstractRepositoryRDB implements WriteNotificationRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function addNewNotification(NewNotification $notification): int
    {
        $this->debug('Add notification configuration', ['notification' => $notification]);

        $request = $this->translateDbName(
            'INSERT INTO `:db`.notification
            (name, timeperiod_id, is_activated) VALUES
            (:name, :timeperiodId, :isActivated)'
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':name', $notification->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':timeperiodId', $notification->getTimePeriod()->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':isActivated', $notification->isActivated(), \PDO::PARAM_BOOL);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function addMessagesToNotification(int $notificationId, array $messages): void
    {
        $this->debug('Add notification messages', ['notification_id' => $notificationId, 'messages' => $messages]);

        if ($messages === []) {
            return;
        }

        $queryBinding = [];
        $bindedValues = [];
        foreach ($messages as $key => $message) {
            $queryBinding[] = "(:notificationId, :channel_{$key}, :subject_{$key}, :message_{$key},"
                . " :formatted_message_{$key})";
            $bindedValues[":channel_{$key}"] = $message->getChannel()->value;
            $bindedValues[":subject_{$key}"] = $message->getSubject();
            $bindedValues[":message_{$key}"] = $message->getRawMessage();
            $bindedValues[":formatted_message_{$key}"] = $message->getFormattedMessage();
        }

        $request = $this->translateDbName(
            'INSERT INTO `:db`.notification_message
            (notification_id, channel, subject, message, formatted_message) VALUES '
            . implode(', ', $queryBinding)
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        foreach ($bindedValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_STR);
        }

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addUsersToNotification(int $notificationId, array $userIds): void
    {
        $this->debug('Add users to notification', ['notification_id' => $notificationId, 'users' => $userIds]);

        if ($userIds === []) {
            return;
        }

        $queryBinding = [];
        $bindedValues = [];
        foreach ($userIds as $key => $user) {
            $queryBinding[] = "(:notificationId, :userId_{$key})";
            $bindedValues[":userId_{$key}"] = $user;
        }

        $request = $this->translateDbName(
            'INSERT INTO `:db`.notification_user_relation
            (notification_id, user_id) VALUES '
            . implode(', ', $queryBinding)
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        foreach ($bindedValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addContactGroupsToNotification(int $notificationId, array $contactGroupIds): void
    {

        if ($contactGroupIds === []) {
            return;
        }

        $queryBinding = [];
        $bindedValues = [];
        foreach ($contactGroupIds as $key => $contactgroupId) {
            $queryBinding[] = "(:notificationId, :contactgroupId_{$key})";
            $bindedValues[":contactgroupId_{$key}"] = $contactgroupId;
        }

        $request = $this->translateDbName(
            'INSERT INTO `:db`.notification_contactgroup_relation
            (notification_id, contactgroup_id) VALUES '
            . implode(', ', $queryBinding)
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        foreach ($bindedValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function updateNotification(Notification $notification): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE notification
                SET
                    name = :name,
                    timeperiod_id = :timeperiodId,
                    is_activated = :isActivated
                WHERE
                    id = :notificationId
                SQL
        ));

        $statement->bindValue(':name', $notification->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':timeperiodId', $notification->getTimePeriod()->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':isActivated', $notification->isActivated(), \PDO::PARAM_BOOL);
        $statement->bindValue(':notificationId', $notification->getId(), \PDO::PARAM_INT);

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteNotificationMessages(int $notificationId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.notification_message
                    WHERE notification_id = :notificationId
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteUsersFromNotification(int $notificationId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.notification_user_relation
                    WHERE notification_id = :notificationId
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteContactGroupsFromNotification(int $notificationId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    DELETE FROM `:db`.notification_contactgroup_relation
                    WHERE notification_id = :notificationId
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteContactGroupsByNotificationAndContactGroupIds(
        int $notificationId,
        array $contactGroupsIds
    ): void {
        if ($contactGroupsIds === []) {
            return;
        }
        $bindValues = [];
        foreach ($contactGroupsIds as $contactGroupId) {
            $bindValues[':contactgroup_' . $contactGroupId] = $contactGroupId;
        }

        $bindToken = implode(', ', array_keys($bindValues));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                    DELETE FROM `:db`.notification_contactgroup_relation
                    WHERE notification_id = :notificationId
                    AND contactgroup_id IN ({$bindToken})
                SQL
        ));
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        foreach ($bindValues as $token => $value) {
            $statement->bindValue($token, $value, \PDO::PARAM_INT);
        }
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deleteNotification(int $notificationId): int
    {
        $request = <<<'SQL'
            DELETE FROM `:db`.`notification`
            WHERE `id` = :notification_id
            SQL;
        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':notification_id', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}
