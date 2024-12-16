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

use Centreon\Domain\Option\OptionService;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Application\Configuration\Notification\Repository\ReadHostNotificationRepositoryInterface;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadHostNotificationRepository extends AbstractDbReadNotificationRepository implements ReadHostNotificationRepositoryInterface
{
    /** @var array<int,NotifiedContact[]> */
    private array $notifiedContacts = [];

    /** @var array<int,NotifiedContactGroup[]> */
    private array $notifiedContactGroups = [];

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly OptionService $optionService,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsById(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsByIdAndAccessGroups(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds, array $accessGroups): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds, $accessGroups);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsById(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsByIdAndAccessGroups(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds, array $accessGroups): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds, $accessGroups);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * Initialize notified contacts and contactgroups for given host id.
     *
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     */
    private function fetchNotifiedContactsAndContactGroups(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds, array $accessGroups = []): void
    {
        if ($accessGroups === []) {
            $this->notifiedContacts[$hostId] = $this->findContactsByIds($notifiedContactsIds);
            $this->notifiedContactGroups[$hostId] = $this->findContactGroupsByIds($notifiedContactGroupsIds);
        } else {
            $this->notifiedContacts[$hostId] = $this->findContactsByIdsAndAccessGroups(
                $notifiedContactsIds,
                $accessGroups
            );
            $this->notifiedContactGroups[$hostId] = $this->findContactGroupsByIdsAndAccessGroups(
                $notifiedContactGroupsIds,
                $accessGroups
            );
        }
    }

    public function findContactsForHostAndHostTemplate(int $id): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT chr.contact_id
                FROM `:db`.contact_host_relation chr, `:db`.contact
                WHERE host_host_id = :hostId
                AND chr.contact_id = contact.contact_id
                AND contact.contact_activate = '1'
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostId', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findContactGroupsForHostAndHostTemplate(int $id): array
    {
         $request = $this->translateDbName(
            <<<'SQL'
                SELECT contactgroup_cg_id
                FROM contactgroup_host_relation, contactgroup
                WHERE host_host_id = :hostId
                AND contactgroup_cg_id = cg_id
                AND cg_activate = '1'
            SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostId', $id, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findHostTemplates(int $hostId): array
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT host_tpl_id
                FROM host_template_relation
                WHERE host_host_id = :hostId
                ORDER BY `order` ASC
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':hostId', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}