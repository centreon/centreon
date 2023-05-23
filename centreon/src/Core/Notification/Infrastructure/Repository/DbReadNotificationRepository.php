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
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationGenericObject;
use Core\Notification\Domain\Model\NotificationMessage;
use Utility\SqlConcatenator;

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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function findUsersCountByNotificationIds(array $notificationIds): array
    {
        $concatenator = $this->getConcatenatorForFindUsersCountQuery()
            ->storeBindValueMultiple(':notification_ids', $notificationIds, \PDO::PARAM_INT)
            ->appendWhere(
                <<<'SQL'
                        WHERE notification_id IN (:notification_ids)
                    SQL
            );

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $result ?: [];
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

    /**
     * {@inheritDoc}
     */
    public function findAll(?RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;
        $query = $this->buildFindAllQuery($sqlTranslator);

        $statement = $this->db->prepare($query);
        $sqlTranslator?->bindSearchValues($statement);
        $statement->execute();
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $notifications = [];
        foreach ($result as $notificationData) {
            $notifications[] = new Notification(
                $notificationData['id'],
                $notificationData['name'],
                new NotificationGenericObject($notificationData['timeperiod_id'], $notificationData['tp_name']),
                (bool) $notificationData['is_activated'],
            );
        }

        return $notifications;
    }

    /**
     * @inheritDoc
     */
    public function findNotificationChannelsByNotificationIds(array $notificationIds): array
    {
        $concatenator = (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                        SELECT notification_id, channel
                    SQL
            )->defineFrom(
                <<<'SQL'
                        FROM `:db`.notification_message
                    SQL
            )
            ->storeBindValueMultiple(':notification_ids', $notificationIds, \PDO::PARAM_INT)
            ->defineWhere(
                <<<'SQL'
                        WHERE notification_id IN (:notification_ids)
                    SQL
            );

        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $notificationsChannels = [];
        foreach ($result as $notificationData) {
            $notificationsChannels[(int) $notificationData['notification_id']][] = NotificationChannel::from($notificationData['channel']);
        }

        return $notificationsChannels;
    }

    /**
     * @return SqlConcatenator
     */
    private function getConcatenatorForFindUsersCountQuery(): SqlConcatenator
    {
        return (new SqlConcatenator())
            ->defineSelect(
                <<<'SQL'
                    SELECT notification_id, COUNT(user_id)
                    SQL
            )->defineFrom(
                <<<'SQL'
                    FROM
                        `:db`.notification_user_relation rel
                    SQL
            )->defineGroupBy(
                <<<'SQL'
                        GROUP BY notification_id
                    SQL
            );
    }

    /**
     * Build Query for findAll with research parameters.
     *
     * @param SqlRequestParametersTranslator|null $sqlTranslator
     *
     * @return string
     */
    private function buildFindAllQuery(?SqlRequestParametersTranslator $sqlTranslator): string
    {

        $query = $this->translateDbName(
            <<<'SQL'
                    SELECT id, name, timeperiod_id, tp_name, is_activated
                    FROM `:db`.notification
                    INNER JOIN timeperiod ON timeperiod_id = tp_id
                SQL
        );

        if ($sqlTranslator === null) {
            return $query;
        }

        $sqlTranslator->setConcordanceArray([
            'name' => 'notification.name',
        ]);

        $searchQuery = $sqlTranslator->translateSearchParameterToSql();
        $query .= ! is_null($searchQuery) ? $searchQuery : '';

        $paginationQuery = $sqlTranslator->translatePaginationToSql();
        $query .= $paginationQuery;

        return $query;
    }
}
