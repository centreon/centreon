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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Contact;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\TimePeriod;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadNotificationRepository extends AbstractRepositoryRDB implements ReadNotificationRepositoryInterface
{
    use LoggerTrait;
    use SqlMultipleBindTrait;

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
            new TimePeriod($result['timeperiod_id'], $result['tp_name']),
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
            new TimePeriod($result['timeperiod_id'], $result['tp_name']),
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
            $messages[] = new Message(
                Channel::from($result['channel']),
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
    public function findUsersByNotificationId(int $notificationId): array
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT contact.contact_id, contact.contact_name, contact.contact_email
                FROM `:db`.contact
                LEFT JOIN `:db`.notification_user_relation nur
                    ON nur.user_id = contact.contact_id
                INNER JOIN `:db`.notification notif
                    ON notif.id = nur.notification_id
                WHERE notif.id = :notification_id
                SQL
            )
        );
        $statement->bindValue(':notification_id', $notificationId, \PDO::PARAM_INT);
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
            $users[] = new Contact(
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
    public function findUsersByNotificationIdUserAndAccessGroups(
        int $notificationId,
        ContactInterface $user,
        array $accessGroups
    ): array {
        $accessGroupIds = array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups);
        [$bindValues, $subQuery] = $this->createMultipleBindQuery($accessGroupIds, ':ag_id_');
        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT contact.*
                FROM `:db`.notification notif
                INNER JOIN `:db`.notification_user_relation nur
                    ON nur.notification_id = notif.id
                INNER JOIN (
                        SELECT /* Finds associated users in ACL group rules */
                            contact.contact_id, contact.contact_alias, contact.contact_name,
                            contact.contact_email, contact.contact_admin, contact.contact_activate
                        FROM `:db`.`contact`
                        INNER JOIN `:db`.`acl_group_contacts_relations` acl_c_rel
                            ON acl_c_rel.contact_contact_id = contact.contact_id
                        WHERE contact.contact_register = '1'
                            AND acl_c_rel.acl_group_id IN ({$subQuery})
                        UNION
                        SELECT  /* Finds users belonging to associated contact groups in ACL group rules */
                            contact.contact_id, contact.contact_alias, contact.contact_name,
                            contact.contact_email, contact.contact_admin, contact.contact_activate
                        FROM `:db`.`contact`
                        INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                            ON c_cg_rel.contact_contact_id = contact.contact_id
                        INNER JOIN `:db`.`acl_group_contactgroups_relations` acl_cg_rel
                            ON acl_cg_rel.cg_cg_id = c_cg_rel.contactgroup_cg_id
                        WHERE contact.contact_register = '1'
                            AND acl_cg_rel.acl_group_id IN ({$subQuery})
                        UNION
                        SELECT /* Finds users belonging to the same contact groups as the user */
                            contact2.contact_id, contact2.contact_alias, contact2.contact_name,
                            contact2.contact_email, contact2.contact_admin, contact.contact_activate
                        FROM `:db`.`contact`
                        INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                            ON c_cg_rel.contact_contact_id = contact.contact_id
                        INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel2
                            ON c_cg_rel2.contactgroup_cg_id = c_cg_rel.contactgroup_cg_id
                        INNER JOIN `:db`.`contact` contact2
                            ON contact2.contact_id = c_cg_rel2.contact_contact_id
                        WHERE c_cg_rel.contact_contact_id  = :user_id
                            AND contact.contact_register = '1'
                            AND contact2.contact_register = '1'
                        GROUP BY contact2.contact_id
                    ) as contact
                    ON contact.contact_id = nur.user_id
                WHERE notif.id = :notification_id
                SQL
            )
        );
        $statement->bindValue(':notification_id', $notificationId, \PDO::PARAM_INT);
        $statement->bindValue(':user_id', $user->getId(), \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $users = [];

        foreach ($statement as $result) {
            /**
             * @var array{
             *     contact_id: int,
             *     contact_name: string,
             *     contact_email: string
             * } $result
             */
            $users[] = new Contact(
                $result['contact_id'],
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

        [$bindValues, $subQuery] = $this->createMultipleBindQuery($contactGroupIds, ':id_');

        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT DISTINCT c.contact_id, c.contact_name, c.contact_email
                FROM `:db`.contactgroup_contact_relation cgcr
                INNER JOIN `:db`.contactgroup cg
                    ON cg.cg_id=cgcr.contactgroup_cg_id
                INNER JOIN `:db`.contact c
                    ON c.contact_id=cgcr.contact_contact_id
                WHERE cg.cg_id IN ({$subQuery})
                ORDER BY c.contact_name ASC
                SQL
            )
        );
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $users = [];

        foreach ($statement as $result) {
            /**
             * @var array{
             *     contact_id: int,
             *     contact_name: string,
             *     contact_email: string
             * } $result
             */
            $users[$result['contact_id']] = new Contact(
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
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT contactgroup_id, contactgroup.cg_name, contactgroup.cg_alias
                FROM `:db`.notification_contactgroup_relation
                INNER JOIN `:db`.contactgroup
                    ON cg_id = contactgroup_id
                WHERE notification_id = :notificationId
                ORDER BY contactgroup.cg_name ASC
                SQL
            )
        );
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $contactGroups = [];

        /**
         * @var array{contactgroup_id:int, cg_name:string, cg_alias:string} $result
         */
        foreach ($statement as $result) {
            $contactGroups[] = new ContactGroup(
                $result['contactgroup_id'],
                $result['cg_name'],
                $result['cg_alias']
            );
        }

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function countContactsByNotificationIds(array $notificationIds): array
    {
        [$bindValues, $subQuery] = $this->createMultipleBindQuery($notificationIds, ':id_');
        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT result.id, COUNT(result.contact_id)
                FROM (
                    SELECT notif.id, contact.contact_id
                    FROM `:db`.contact
                    LEFT JOIN `:db`.notification_user_relation nur
                        ON nur.user_id = contact.contact_id
                    INNER JOIN `:db`.notification notif
                        ON notif.id = nur.notification_id
                    WHERE notif.id IN ({$subQuery})
                ) AS result
                GROUP BY result.id
                SQL
            )
        );
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function countContactsByNotificationIdsAndAccessGroup(
        array $notificationIds,
        ContactInterface $user,
        array $accessGroups
    ): array {
        $accessGroupIds = array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups);
        [$accessGroupBindValues, $accessGroupSubQuery] = $this->createMultipleBindQuery($accessGroupIds, ':ag_id_');
        [$notificationBindValues, $notificationSubQuery] = $this->createMultipleBindQuery($notificationIds, ':notif_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT result.id, COUNT(result.contact_id)
                FROM (
                    SELECT notif.id, contact.contact_id
                    FROM `:db`.notification notif
                    INNER JOIN `:db`.notification_user_relation nur
                        ON nur.notification_id = notif.id
                    INNER JOIN (
                            SELECT contact.contact_id, contact.contact_alias, contact.contact_name,
                                contact.contact_email, contact.contact_admin, contact.contact_activate
                            FROM `:db`.`contact`
                            INNER JOIN `:db`.`acl_group_contacts_relations` acl_c_rel
                                ON acl_c_rel.contact_contact_id = contact.contact_id
                                AND acl_c_rel.acl_group_id IN ({$accessGroupSubQuery})
                            WHERE contact.contact_register = '1'
                            UNION ALL
                            SELECT contact.contact_id, contact.contact_alias, contact.contact_name,
                                contact.contact_email, contact.contact_admin, contact.contact_activate
                            FROM `:db`.`contact`
                            JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                                ON c_cg_rel.contact_contact_id = contact.contact_id
                            JOIN `:db`.`acl_group_contactgroups_relations` acl_cg_rel
                                ON acl_cg_rel.cg_cg_id = c_cg_rel.contactgroup_cg_id
                                AND acl_cg_rel.acl_group_id IN ({$accessGroupSubQuery})
                            WHERE contact.contact_register = '1'
                            UNION ALL
                            SELECT contact2.contact_id, contact2.contact_alias, contact2.contact_name,
                                contact2.contact_email, contact2.contact_admin, contact.contact_activate
                            FROM `:db`.`contact`
                            INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel
                                ON c_cg_rel.contact_contact_id = contact.contact_id
                            INNER JOIN `:db`.`contactgroup_contact_relation` c_cg_rel2
                                ON c_cg_rel2.contactgroup_cg_id = c_cg_rel.contactgroup_cg_id
                            INNER JOIN `:db`.`contact` contact2
                                ON contact2.contact_id = c_cg_rel2.contact_contact_id
                            WHERE c_cg_rel.contact_contact_id  = :user_id
                                AND contact.contact_register = '1'
                                AND contact2.contact_register = '1'
                            GROUP BY contact2.contact_id
                        ) as contact
                        ON contact.contact_id = nur.user_id
                    WHERE notif.id IN ({$notificationSubQuery})
                ) AS result
                GROUP BY result.id
                SQL
            )
        );
        foreach ([...$accessGroupBindValues, ...$notificationBindValues] as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':user_id', $user->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsByNotificationIdAndAccessGroups(
        int $notificationId,
        ContactInterface $user,
        array $accessGroups
    ): array {
        $accessGroupIds = array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups);
        [$bindValues, $subQuery] = $this->createMultipleBindQuery($accessGroupIds, ':ag_id_');

        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT cg_id, cg_name, cg_alias
                FROM (
                    SELECT
                        cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                    FROM `:db`.acl_group_contactgroups_relations gcgr
                    INNER JOIN `:db`.contactgroup cg
                        ON cg.cg_id = gcgr.cg_cg_id
                    WHERE gcgr.acl_group_id IN ({$subQuery})
                        AND cg_activate = '1'
                    GROUP BY cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                    UNION
                    SELECT cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                    FROM `:db`.contactgroup cg
                    INNER JOIN `:db`.contactgroup_contact_relation ccr
                        ON ccr.contactgroup_cg_id = cg.cg_id
                    INNER JOIN `:db`.contact c
                        ON c.contact_id = ccr.contact_contact_id
                    WHERE ccr.contact_contact_id = :user_id
                        AND cg.cg_activate = '1'
                        AND c.contact_register = '1'
                ) AS cg
                INNER JOIN `:db`.notification_contactgroup_relation ncr
                    ON ncr.contactgroup_id = cg.cg_id
                WHERE ncr.notification_id = :notificationId
                ORDER BY cg_name ASC
                SQL
            )
        );
        $statement->bindValue(':notificationId', $notificationId, \PDO::PARAM_INT);
        $statement->bindValue(':user_id', $user->getId(), \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $contactGroups = [];
        /**
         * @var array{cg_id:int,cg_name:string,cg_alias:string} $result
         */
        foreach ($statement as $result) {
            $contactGroups[] = new ContactGroup($result['cg_id'], $result['cg_name'], $result['cg_alias']);
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
        $sqlTranslator?->setConcordanceArray([
            'id' => 'id',
            'name' => 'name',
            'is_activated' => 'is_activated',
            'timeperiod.id' => 'timeperiod_id',
        ]);

        $query = $this->buildFindAllQuery($sqlTranslator);

        $statement = $this->db->prepare($query);
        $sqlTranslator?->bindSearchValues($statement);
        $statement->execute();
        $sqlTranslator?->calculateNumberOfRows($this->db);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $notifications = [];
        /**
         * @var array{
         *     id: int,
         *     name: string,
         *     timeperiod_id: int,
         *     tp_name: string,
         *     is_activated: int
         * } $notificationData
         */
        foreach ($statement as $notificationData) {
            $notifications[] = new Notification(
                $notificationData['id'],
                $notificationData['name'],
                new TimePeriod($notificationData['timeperiod_id'], $notificationData['tp_name']),
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
        [$bindValues, $subQuery] = $this->createMultipleBindQuery($notificationIds, ':id_');

        $statement = $this->db->prepare(
            $this->translateDbName(<<<SQL
                SELECT notification_id, channel
                FROM `:db`.notification_message
                WHERE notification_id IN ({$subQuery})
                SQL
            )
        );

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $notificationsChannels = [];
        foreach ($result as $notificationData) {
            $notificationsChannels[(int) $notificationData['notification_id']][] = Channel::from($notificationData['channel']);
        }

        return $notificationsChannels;
    }

    /**
     * @inheritDoc
     */
    public function findLastNotificationDependencyIdsByHostGroup(int $hostGroupId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT count(dependency_dep_id) AS nb_dependency , dependency_dep_id AS id
                FROM dependency_hostgroupParent_relation
                WHERE dependency_dep_id IN
                    (SELECT dependency_dep_id FROM dependency_hostgroupParent_relation
                        WHERE hostgroup_hg_id =  :hostGroupId)
                GROUP BY dependency_dep_id
                SQL
        ));
        $statement->bindValue(':hostGroupId', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();

        $lastDependencyIds = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            /**
             * @var array{id:int,nb_dependency:int} $result
             */
            if ($result['nb_dependency'] === 1) {
                $lastDependencyIds[] = $result['id'];
            }
        }

        return $lastDependencyIds;
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
            <<<'SQL_WRAP'
                SELECT SQL_CALC_FOUND_ROWS id, name, timeperiod_id, tp_name, is_activated
                FROM `:db`.notification
                INNER JOIN timeperiod ON timeperiod_id = tp_id
                SQL_WRAP
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
