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

namespace Core\Infrastructure\Configuration\NotificationPolicy\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Application\Configuration\Notification\Repository\ReadMetaServiceNotificationRepositoryInterface;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadMetaServiceNotificationRepository extends AbstractRepositoryDRB implements ReadMetaServiceNotificationRepositoryInterface
{
    use LoggerTrait, SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsById(int $metaServiceId): array
    {
        $this->info('Fetching contacts from database');

        $contacts = [];

        $request = $this->translateDbName(
            "SELECT
                c.contact_id,
                c.contact_alias,
                c.contact_name,
                c.contact_email,
                c.contact_admin,
                c.contact_host_notification_options,
                c.contact_service_notification_options,
                t1.tp_id as host_timeperiod_id,
                t1.tp_name as host_timeperiod_name,
                t1.tp_alias as host_timeperiod_alias,
                t2.tp_id as service_timeperiod_id,
                t2.tp_name as service_timeperiod_name,
                t2.tp_alias as service_timeperiod_alias
            FROM `:db`.contact c
            INNER JOIN `:db`.meta_contact cm
              ON cm.contact_id = c.contact_id
              AND cm.meta_id = :meta_id
            INNER JOIN `:db`.timeperiod t1
                ON t1.tp_id = c.timeperiod_tp_id
            INNER JOIN `:db`.timeperiod t2
                ON t2.tp_id = c.timeperiod_tp_id2
            WHERE c.contact_activate = '1'"
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':meta_id', $metaServiceId, \PDO::PARAM_INT);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contacts[] = DbNotifiedContactFactory::createFromRecord($row);
        }

        return $contacts;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsByIdAndAccessGroups(int $metaServiceId, array $accessGroups): array
    {
        $contacts = [];
        if ($accessGroups === []) {
            return $contacts;
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        [$aclBindValues, $aclBindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT
                    c.contact_id,
                    c.contact_alias,
                    c.contact_name,
                    c.contact_email,
                    c.contact_admin,
                    c.contact_host_notification_options,
                    c.contact_service_notification_options,
                    t1.tp_id as host_timeperiod_id,
                    t1.tp_name as host_timeperiod_name,
                    t1.tp_alias as host_timeperiod_alias,
                    t2.tp_id as service_timeperiod_id,
                    t2.tp_name as service_timeperiod_name,
                    t2.tp_alias as service_timeperiod_alias
                FROM `:db`.contact c
                INNER JOIN `:db`.meta_contact cm
                  ON cm.contact_id = c.contact_id
                  AND cm.meta_id = :meta_id
                INNER JOIN `:db`.timeperiod t1
                    ON t1.tp_id = c.timeperiod_tp_id
                INNER JOIN `:db`.timeperiod t2
                    ON t2.tp_id = c.timeperiod_tp_id2
                INNER JOIN `:db`.acl_group_contacts_relations agcr
                    ON agcr.contact_contact_id = c.contact_id
                WHERE c.contact_activate = '1'
                    AND agcr.acl_group_id IN ({$aclBindQuery})
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($aclBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->bindValue(':meta_id', $metaServiceId, \PDO::PARAM_INT);

        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contacts[] = DbNotifiedContactFactory::createFromRecord($row);
        }

        return $contacts;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsById(int $metaServiceId): array
    {
        $this->info('Fetching contact groups from database');

        $contactGroups = [];

        $request = $this->translateDbName(
            "SELECT
                cg.cg_id AS `id`,
                cg.cg_name AS `name`,
                cg.cg_alias AS `alias`,
                cg.cg_activate AS `activated`
            FROM `:db`.contactgroup cg
            INNER JOIN `:db`.meta_contactgroup_relation cgm
              ON cgm.cg_cg_id = cg.cg_id
              AND cgm.meta_id = :meta_id
            WHERE cg.cg_activate = '1'"
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':meta_id', $metaServiceId, \PDO::PARAM_INT);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contactGroups[] = DbNotifiedContactGroupFactory::createFromRecord($row);
        }

        return $contactGroups;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsByIdAndAccessGroups(int $metaServiceId, array $accessGroups): array
    {
        $contactGroups = [];
        if ($accessGroups === []) {
            return $contactGroups;
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        [$aclBindValues, $aclBindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT
                    cg.cg_id AS `id`,
                    cg.cg_name AS `name`,
                    cg.cg_alias AS `alias`,
                    cg.cg_activate AS `activated`
                FROM `:db`.contactgroup cg
                INNER JOIN `:db`.meta_contactgroup_relation cgm
                    ON cgm.cg_cg_id = cg.cg_id
                    AND cgm.meta_id = :meta_id
                INNER JOIN `:db`.acl_group_contactgroups_relations agcgr
                    ON agcgr.cg_cg_id = cg.cg_id
                WHERE cg.cg_activate = '1'
                    AND agcgr.acl_group_id IN ({$aclBindQuery})
                SQL
        );

        $statement = $this->db->prepare($request);
        foreach ($aclBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->bindValue(':meta_id', $metaServiceId, \PDO::PARAM_INT);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contactGroups[] = DbNotifiedContactGroupFactory::createFromRecord($row);
        }

        return $contactGroups;
    }
}
