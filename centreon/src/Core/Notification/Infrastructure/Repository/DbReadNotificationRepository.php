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
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\ConfigurationUser;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
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
        $this->info('Get a notification configuration with ID #' . $notificationId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT id, name, timeperiod_id, tp_name, is_activated
                FROM `:db`.notification
                INNER JOIN timeperiod ON timeperiod_id = tp_id
                WHERE id = :notificationId
                SQL
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
            new ConfigurationTimePeriod($result['timeperiod_id'], $result['tp_name']),
            (bool) $result['is_activated'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByName(TrimmedString $notificationName): ?Notification
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT id, name, timeperiod_id, tp_name, is_activated
                    FROM `:db`.notification
                    INNER JOIN timeperiod ON timeperiod_id = tp_id
                    WHERE name = :notificationName
                    SQL
            )
        );
        $statement->bindValue(':notificationName', $notificationName, \PDO::PARAM_STR);
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
            new ConfigurationTimePeriod($result['timeperiod_id'], $result['tp_name']),
            (bool) $result['is_activated'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findMessagesByNotificationId(int $notificationId): array
    {
        $this->info('Get all notification messages for notification with ID #' . $notificationId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT id, channel, subject, message, formatted_message
                FROM `:db`.notification_message
                WHERE notification_id = :notificationId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $messages = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $messages[] = new NotificationMessage(
                NotificationChannel::from($result['channel']),
                $result['subject'],
                $result['message'],
                $result['formatted_message']
            );
        }

        return $messages;
    }

    /**
     * @inheritDoc
     */
    public function findUsersByNotificationId(
        int $notificationId,
        bool $retrieveAlsoAllUsersOfContactGroups = false
    ): array {
        $this->info('Get all notification users for notification with ID #' . $notificationId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT notification_id, user_id, contact.contact_name, contact.contact_email
                FROM `:db`.notification_user_relation
                JOIN contact ON user_id = contact_id
                WHERE notification_id = :notificationId
                ORDER BY contact.contact_name ASC
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $users = [];

        foreach ($statement as $result) {
            /**
             * @var array{
             *     user_id: int,
             *     contact_name: string,
             *     contact_email: string
             * } $result
             */
            $users[$result['user_id']] = new ConfigurationUser(
                $result['user_id'],
                $result['contact_name'],
                $result['contact_email'],
            );
        }

        return $users;
    }

    public function findUsersByContactGroupIds(int ...$contactGroupIds): array
    {
        if ([] === $contactGroupIds) {
            return [];
        }

        $select = <<<'SQL'
            SELECT DISTINCT c.contact_id, c.contact_name, c.contact_email
            FROM `:db`.contactgroup_contact_relation cgcr
            INNER JOIN `:db`.contactgroup cg ON cg.cg_id=cgcr.contactgroup_cg_id
            INNER JOIN `:db`.contact c ON c.contact_id=cgcr.contact_contact_id
            WHERE cg.cg_id IN (:contact_group_ids)
            ORDER BY c.contact_name ASC
            SQL;

        $concatenator = (new SqlConcatenator())
            ->defineSelect($select)
            ->storeBindValueMultiple(':contact_group_ids', array_values($contactGroupIds), \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->concatAll()));
        $concatenator->bindValuesToStatement($statement);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $users = [];

        foreach ($statement as $result) {
            /**
             * @var array{
             *     contact_id: int,
             *     contact_name: string,
             *     contact_email: string
             * } $result
             */
            $users[$result['contact_id']] = new ConfigurationUser(
                $result['contact_id'],
                $result['contact_name'],
                $result['contact_email'],
            );
        }

        return $users;
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsByNotificationId(int $notificationId): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT notification_id, contactgroup_id, contactgroup.cg_name
                FROM `:db`.notification_contactgroup_relation
                JOIN `:db`.contactgroup ON cg_id = contactgroup_id
                WHERE notification_id = :notificationId
                ORDER BY contactgroup.cg_name ASC
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $contactgroups = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $contactgroups[] = new ContactGroup($result['contactgroup_id'], $result['cg_name']);
        }

        return $contactgroups;
    }

    /**
     * @inheritDoc
     */
    public function findUsersCountByNotificationIds(array $notificationIds): array
    {
        $bindValues = [];
        foreach ($notificationIds as $notificationId) {
            $bindValues[':notification_' . $notificationId] = $notificationId;
        }
        $bindToken = implode(', ', array_keys($bindValues));
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<SQL
                    SELECT notification_id, count(user) FROM (
                        SELECT rel.notification_id, user_id as user
                        FROM notification_user_relation rel
                        WHERE rel.notification_id IN ({$bindToken})
                        UNION
                        SELECT cg_rel.notification_id, contactgroup_id as user
                        FROM notification_contactgroup_relation cg_rel
                        WHERE cg_rel.notification_id IN ({$bindToken})
                    ) as subquery GROUP BY notification_id
                    SQL
            )
        );
        foreach ($bindValues as $token => $notificationId) {
            $statement->bindValue($token, $notificationId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $result ?: [];
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsByNotificationIdAndUserId(int $notificationId, int $userId): array
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT cg_id,cg_name FROM contactgroup cg
                    INNER JOIN contactgroup_contact_relation ccr
                        ON ccr.contactgroup_cg_id = cg.cg_id
                    INNER JOIN notification_contactgroup_relation ncr
                        ON ncr.contactgroup_id = cg.cg_id
                    WHERE ccr.contact_contact_id = :userId
                    AND ncr.notification_id = :notificationId
                    AND cg_activate = '1'
                    ORDER BY cg_name ASC
                    SQL
            )
        );
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->execute();

        $contactGroups = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $contactGroups[] = new ContactGroup($result['cg_id'], $result['cg_name']);
        }

        return $contactGroups;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(int $notificationId): bool
    {
        $this->info('Check existence of notification configuration with ID #' . $notificationId);

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
        $sqlTranslator?->getRequestParameters()->setConcordanceStrictMode(
            RequestParameters::CONCORDANCE_MODE_STRICT
        );
        $query = $this->buildFindAllQuery($sqlTranslator);

        $statement = $this->db->prepare($query);
        $sqlTranslator?->bindSearchValues($statement);
        $statement->execute();

        // Pagination
        $resultCount = $this->db->query('SELECT FOUND_ROWS()');
        if ($resultCount !== false && ($total = $resultCount->fetchColumn()) !== false) {
            $sqlTranslator?->getRequestParameters()->setTotal((int) $total);
        }

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $notifications = [];
        foreach ($result as $notificationData) {
            $notifications[] = new Notification(
                $notificationData['id'],
                $notificationData['name'],
                new ConfigurationTimePeriod($notificationData['timeperiod_id'], $notificationData['tp_name']),
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
                SELECT SQL_CALC_FOUND_ROWS id, name, timeperiod_id, tp_name, is_activated
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
