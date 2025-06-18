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
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Application\Configuration\Notification\Repository\ReadHostNotificationRepositoryInterface;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DbReadHostNotificationRepository extends AbstractDbReadNotificationRepository implements ReadHostNotificationRepositoryInterface
{
    use LoggerTrait;

    /** @var array<int,NotifiedContact[]> */
    private array $notifiedContacts = [];

    /** @var array<int,NotifiedContactGroup[]> */
    private array $notifiedContactGroups = [];

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(
        DatabaseConnection $db,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsByIds(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsByIdsAndAccessGroups(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds, array $accessGroups): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds, $accessGroups);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsByIds(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsByIdsAndAccessGroups(int $hostId, array $notifiedContactsIds, array $notifiedContactGroupsIds, array $accessGroups): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $notifiedContactsIds, $notifiedContactGroupsIds, $accessGroups);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findContactsByHostOrHostTemplate(int $id): array
    {
        try {
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
        } catch (\PDOException $exception) {
            $errorMessage = "Error while fetching contacts for host or host template with id {$id} : {$exception->getMessage()}";
            $this->error($errorMessage, [
                'id' => $id,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'pdo_code' => $exception->getCode(),
                    'pdo_info' => $exception->errorInfo,
                    'trace' => $exception->getTraceAsString(),
                ],
            ]);

            throw new RepositoryException(message: $errorMessage, previous: $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupsByHostOrHostTemplate(int $id): array
    {
        try {
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
        } catch (\PDOException $exception) {
            $errorMessage = "Error while fetching contact groups for host or host template with id {$id} : {$exception->getMessage()}";
            $this->error($errorMessage, [
                'id' => $id,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'pdo_code' => $exception->getCode(),
                    'pdo_info' => $exception->errorInfo,
                    'trace' => $exception->getTraceAsString(),
                ],
            ]);

            throw new RepositoryException(message: $errorMessage, previous: $exception);
        }
    }

    /**
     * Initialize notified contacts and contactgroups for given host id.
     *
     * @param int $hostId
     * @param array<int, int> $notifiedContactsIds
     * @param array<int, int> $notifiedContactGroupsIds
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
}