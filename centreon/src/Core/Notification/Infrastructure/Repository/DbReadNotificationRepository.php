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
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationGenericObject;
use Core\Notification\Domain\Model\NotificationMessage;

class DbReadNotificationRepository extends AbstractRepositoryRDB implements ReadNotificationRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $notificationId): ?Notification
    {
        $this->info('Get a notification configuration with id #' . $notificationId);

        $request = $this->translateDbName(
            'SELECT id, name, timeperiod_id, tp_name, is_activated
            FROM `:db`.notification
            INNER JOIN timeperiod ON timeperiod_id = tp_id
            WHERE id = :notificationId'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        /**
         * @var array{id:int,name:string,timeperiod_id:int,tp_name:string,is_activated:int} $result
         */
        return new Notification(
            $result['id'],
            $result['name'],
            new NotificationGenericObject($result['timeperiod_id'], $result['tp_name']),
            (bool) $result['is_activated'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findMessagesByNotificationId(int $notificationId): array
    {
        $this->info('Get all notification messages for notification with id #' . $notificationId);

        $request = $this->translateDbName(
            'SELECT id, channel, subject, message
            FROM `:db`.notification_message
            WHERE notification_id = :notificationId'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $messages = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $messages[] = new NotificationMessage(
                NotificationChannel::from($result['channel']),
                $result['subject'],
                $result['message']
            );
        }

        return $messages;
    }

    /**
     * Find notification users for a notification.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return NotificationGenericObject[]
     */
    public function findUsersByNotificationId(int $notificationId): array
    {
        $this->info('Get all notification users for notification with id #' . $notificationId);

        $request = $this->translateDbName(
            'SELECT notification_id, user_id, contact.contact_name
            FROM `:db`.notification_user_relation
            JOIN contact ON user_id = contact_id
            WHERE notification_id = :notificationId'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $users = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $users[] = new NotificationGenericObject($result['user_id'], $result['contact_name']);
        }

        return $users;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(int $notificationId): bool
    {
        $this->info('Check existence of notification configuration with id #' . $notificationId);

        $request = $this->translateDbName('SELECT 1 FROM `:db`.notification WHERE id = :notificationId');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByName(TrimmedString $notificationName): bool
    {
        $this->info('Check existence of notification configuration with name ' . $notificationName);

        $request = $this->translateDbName('SELECT 1 FROM `:db`.notification WHERE name = :notificationName');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationName', $notificationName, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
