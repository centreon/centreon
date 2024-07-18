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
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

abstract class AbstractDbReadNotificationRepository extends AbstractRepositoryDRB
{
    use LoggerTrait, SqlMultipleBindTrait;

    /**
     * Find contacts from ids.
     *
     * @param int[] $contactIds
     *
     * @return NotifiedContact[]
     */
    protected function findContactsByIds(array $contactIds): array
    {
        $this->info('Fetching contacts from database');

        $contacts = [];

        if (empty($contactIds)) {
            return $contacts;
        }

        $request = $this->translateDbName(
            'SELECT
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
            INNER JOIN `:db`.timeperiod t1
                ON t1.tp_id = c.timeperiod_tp_id
            INNER JOIN `:db`.timeperiod t2
                ON t2.tp_id = c.timeperiod_tp_id2'
        );

        $collector = new StatementCollector();

        $bindKeys = [];
        foreach ($contactIds as $index => $contactId) {
            $key = ":contactId_{$index}";

            $bindKeys[] = $key;
            $collector->addValue($key, $contactId, \PDO::PARAM_INT);
        }

        $request .= ' WHERE contact_id IN (' . implode(', ', $bindKeys) . ')
            AND contact_activate = \'1\'';

        $statement = $this->db->prepare($request);

        $collector->bind($statement);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contacts[] = DbNotifiedContactFactory::createFromRecord($row);
        }

        return $contacts;
    }

    /**
     * Find contacts from ids and access groups.
     *
     * @param int[] $contactIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return NotifiedContact[]
     */
    protected function findContactsByIdsAndAccessGroups(array $contactIds, array $accessGroups): array
    {
        $this->info('Fetching contacts from database');

        $contacts = [];
        if ($contactIds === [] || $accessGroups === []) {
            return $contacts;
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        [$contactsBindValues, $contactsBindQuery] = $this->createMultipleBindQuery($contactIds, ':contact_id_');
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
                INNER JOIN `:db`.timeperiod t1
                    ON t1.tp_id = c.timeperiod_tp_id
                INNER JOIN `:db`.timeperiod t2
                    ON t2.tp_id = c.timeperiod_tp_id2
                INNER JOIN `:db`.acl_group_contacts_relations agcr
                    ON agcr.contact_contact_id = c.contact_id
                WHERE contact_id IN ({$contactsBindQuery})
                    AND agcr.acl_group_id IN ({$aclBindQuery})
                    AND contact_activate = '1'
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($contactsBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        foreach ($aclBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contacts[] = DbNotifiedContactFactory::createFromRecord($row);
        }

        return $contacts;
    }

    /**
     * Find contact groups from ids.
     *
     * @param int[] $contactGroupIds
     *
     * @return NotifiedContactGroup[]
     */
    protected function findContactGroupsByIds(array $contactGroupIds): array
    {
        $this->info('Fetching contact groups from database');

        $contactGroups = [];

        if (empty($contactGroupIds)) {
            return $contactGroups;
        }

        $collector = new StatementCollector();

        $request = $this->translateDbName(
            'SELECT
                cg_id AS `id`,
                cg_name AS `name`,
                cg_alias AS `alias`,
                cg_activate AS `activated`
            FROM `:db`.contactgroup'
        );

        $bindKeys = [];
        foreach ($contactGroupIds as $index => $contactGroupId) {
            $key = ":contactGroupId_{$index}";

            $bindKeys[] = $key;
            $collector->addValue($key, $contactGroupId, \PDO::PARAM_INT);
        }

        $request .= ' WHERE cg_id IN (' . implode(', ', $bindKeys) . ')';

        $statement = $this->db->prepare($request);

        $collector->bind($statement);
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contactGroups[] = DbNotifiedContactGroupFactory::createFromRecord($row);
        }

        return $contactGroups;
    }

    /**
     * Find contact groups from and access groups.
     *
     * @param int[] $contactGroupIds
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return NotifiedContactGroup[]
     */
    protected function findContactGroupsByIdsAndAccessGroups(array $contactGroupIds, array $accessGroups): array
    {
        $this->info('Fetching contact groups from database');

        $contactGroups = [];

        if ($contactGroupIds === [] || $accessGroups === []) {
            return $contactGroups;
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        [$cgBindValues, $cgBindQuery] = $this->createMultipleBindQuery($contactGroupIds, ':cg_id_');
        [$aclBindValues, $aclBindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':acl_group_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT
                    cg_id AS `id`,
                    cg_name AS `name`,
                    cg_alias AS `alias`,
                    cg_activate AS `activated`
                FROM `:db`.contactgroup
                INNER JOIN `:db`.acl_group_contactgroups_relations agcgr
                    ON agcgr.cg_cg_id = contactgroup.cg_id
                WHERE cg_id IN ({$cgBindQuery})
                    AND agcgr.acl_group_id IN ({$aclBindQuery})
                SQL
        );

        $statement = $this->db->prepare($request);
        foreach ($cgBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        foreach ($aclBindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var array<string,int|string|null> $row */
            $contactGroups[] = DbNotifiedContactGroupFactory::createFromRecord($row);
        }

        return $contactGroups;
    }
}
