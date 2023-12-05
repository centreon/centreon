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

namespace Core\Dashboard\Playlist\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistShareRepositoryInterface;

class DbWritePlaylistShareRepository extends AbstractRepositoryRDB implements WritePlaylistShareRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function deletePlaylistShares(int $playlistId): void
    {
        $deleteContactShareQuery = <<<'SQL'
            DELETE FROM `:db`.`dashboard_playlist_contact_relation` WHERE playlist_id = :playlistId
            SQL;
        $deleteContactStatement = $this->db->prepare($this->translateDbName($deleteContactShareQuery));
        $deleteContactStatement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $deleteContactStatement->execute();

        $deleteContactGroupQuery = <<<'SQL'
            DELETE FROM `:db`.`dashboard_playlist_contactgroup_relation` WHERE playlist_id = :playlistId
            SQL;
        $deleteContactGroupStatement = $this->db->prepare($this->translateDbName($deleteContactGroupQuery));
        $deleteContactGroupStatement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $deleteContactGroupStatement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addPlaylistContactShares(int $playlistId, array $contacts): void
    {
        if ([] === $contacts) {
            return;
        }

        $query = <<<'SQL'
            INSERT INTO `:db`.`dashboard_playlist_contact_relation` VALUES (:contactId, :playlistId, :role)
            SQL;
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        foreach ($contacts as $contact) {
            $statement->bindValue(':contactId', $contact['id'], \PDO::PARAM_INT);
            $statement->bindValue(':role', $contact['role'], \PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function addPlaylistContactGroupShares(int $playlistId, array $contactGroups): void
    {
        if ([] === $contactGroups) {
            return;
        }

        $query = <<<'SQL'
            INSERT INTO `:db`.`dashboard_playlist_contactgroup_relation` VALUES (:contactGroupId, :playlistId, :role)
            SQL;
        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        foreach ($contactGroups as $contactGroup) {
            $statement->bindValue(':contactGroupId', $contactGroup['id'], \PDO::PARAM_INT);
            $statement->bindValue(':role', $contactGroup['role'], \PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function deletePlaylistSharesByContactIds(int $playlistId, array $contactIds): void
    {
        $bind = [];
        foreach ($contactIds as $key => $contactId) {
            $bind[':contact_' . $key] = $contactId;
        }
        if ([] === $bind) {
            return;
        }
        $bindAsString = implode(', ', array_keys($bind));
        $query = <<<SQL
            DELETE FROM `:db`.`dashboard_playlist_contact_relation` WHERE contact_id IN ({$bindAsString}) AND playlist_id = :playlistId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $bindToken => $bindValue) {
            $statement->bindValue($bindToken, $bindValue, \PDO::PARAM_INT);
        }
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function deletePlaylistSharesByContactGroupIds(int $playlistId, array $contactGroupIds): void
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[':contactgroup_' . $key] = $contactGroupId;
        }
        if ([] === $bind) {
            return;
        }
        $bindAsString = implode(', ', array_keys($bind));
        $query = <<<SQL
            DELETE FROM `:db`.`dashboard_playlist_contactgroup_relation` WHERE contactgroup_id IN ({$bindAsString}) AND playlist_id = :playlistId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($bind as $bindToken => $bindValue) {
            $statement->bindValue($bindToken, $bindValue, \PDO::PARAM_INT);
        }
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->execute();
    }
}