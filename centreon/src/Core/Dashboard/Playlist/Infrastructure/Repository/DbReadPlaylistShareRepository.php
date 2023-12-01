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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactGroupShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistShare;

class DbReadPlaylistShareRepository extends AbstractRepositoryRDB implements ReadPlaylistShareRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function existsAsEditor(int $playlistId, ContactInterface $contact): bool
    {
        $query = <<<'SQL'
            SELECT 1 FROM `:db`.`dashboard_playlist` dpl
            LEFT JOIN  `:db`.`dashboard_playlist_contact_relation` dpcr
            ON dpcr.playlist_id = dpl.id
            LEFT JOIN  `:db`.`dashboard_playlist_contactgroup_relation` dpcgr
            ON dpcgr.playlist_id = dpl.id
            WHERE
                (dpcgr.playlist_id = :playlistId
                AND dpcgr.contactgroup_id IN (
                    SELECT contactgroup_cg_id FROM contactgroup_contact_relation WHERE contact_contact_id = :contactId
                )
                AND dpcgr.role = 'editor')
            OR (
                dpcr.playlist_id = :playlistId
                AND dpcr.contact_id = :contactId
                AND dpcr.role = 'editor')
            OR dpl.created_by = :contactId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->bindValue(':contactId', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exists(int $playlistId, ContactInterface $contact): bool
    {
        $query = <<<'SQL'
            SELECT 1 FROM `:db`.`dashboard_playlist` dpl
            LEFT JOIN  `:db`.`dashboard_playlist_contact_relation` dpcr
            ON dpcr.playlist_id = dpl.id
            LEFT JOIN  `:db`.`dashboard_playlist_contactgroup_relation` dpcgr
            ON dpcgr.playlist_id = dpl.id
            WHERE
                (dpcgr.playlist_id = :playlistId
                AND dpcgr.contactgroup_id IN (
                    SELECT contactgroup_cg_id FROM contactgroup_contact_relation WHERE contact_contact_id = :contactId
                ))
            OR (
                dpcr.playlist_id = :playlistId
                AND dpcr.contact_id = :contactId
            )
            OR dpl.created_by = :contactId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->bindValue(':contactId', $contact->getId(), \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function findByPlaylistId(int $playlistId): PlaylistShare
    {
        $contactShares = $this->findContactShareByPlaylistId($playlistId);
        $contactGroupShares = $this->findContactGroupShareByPlaylistId($playlistId);

        return new PlaylistShare($playlistId, $contactShares, $contactGroupShares);
    }

    public function findByPlaylistIdAndContactGroupIds(int $playlistId, array $contactGroupIds): PlaylistShare
    {
        $contactShares = $this->findContactShareByPlaylistIdAndContactGroupIds($playlistId, $contactGroupIds);
        $contactGroupShares = $this->findContactGroupShareByPlaylistIdAndContactGroupIds($playlistId, $contactGroupIds);

        return new PlaylistShare(1,$contactShares,$contactGroupShares);
    }

    /**
     * Find the shared contacts for a playlist.
     *
     * @param int $playlistId
     * @return PlaylistContactShare[]
     */
    private function findContactShareByPlaylistId(int $playlistId): array
    {
        $query = <<<'SQL'
        SELECT
            dpcr.contact_id,
            c.contact_name,
            dpcr.role
        FROM
            `:db`.`dashboard_playlist` AS dp
            INNER JOIN `:db`.`dashboard_playlist_contact_relation` dpcr ON dpcr.playlist_id = dp.id
            INNER JOIN `:db`.`contact` AS c ON c.contact_id = dpcr.contact_id
        WHERE
            dp.id = :playlistId
        SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $contactShares = [];
        foreach ($statement as $result) {
            $contactShares[] = new PlaylistContactShare(
                $playlistId,
                $result['contact_id'],
                $result['contact_name'],
                $result['role' ]
            );
        }

        return $contactShares;
    }

    /**
     * Find the contacts shared to a playlist by given playlist id and contact group ids.
     *
     * @param int $playlistId
     * @param int[] $contactGroupIds
     *
     * @return PlaylistContactShare[]
     */
    private function findContactShareByPlaylistIdAndContactGroupIds(int $playlistId, array $contactGroupIds): array
    {
        $bindContactGroups = [];
        foreach($contactGroupIds as $key => $contactGroupId) {
            $bindContactGroups[':cg_' . $key] = $contactGroupId;
        }

        if ([] === $bindContactGroups) {
            return [];
        }

        $bindContactGroupsString = implode(', ', array_keys($bindContactGroups));

        $query = <<<SQL
            SELECT
                dpcr.contact_id,
                c.contact_name,
                dpcr.role
            FROM
                `:db`.`dashboard_playlist` AS dp
                INNER JOIN `:db`.`dashboard_playlist_contact_relation` dpcr ON dpcr.playlist_id = dp.id
                INNER JOIN `:db`.`contact` AS c ON c.contact_id = dpcr.contact_id
                INNER JOIN `:db`.`contactgroup_contact_relation` AS cgcr ON cgcr = dpcr.contact_id
            WHERE
                dp.id = :playlistId
            AND
                dpcr.contact_id IN (SELECT contact_contact_id FROM`:db`.`contactgroup_contact_relation`
                    WHERE contactgroup_cg_id IN({$bindContactGroupsString})
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        foreach ($bindContactGroups as $token => $contactGroupId) {
            $statement->bindValue($token, $contactGroupId, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $contactShares = [];
        foreach ($statement as $result) {
            $contactShares[] = new PlaylistContactShare(
                $playlistId,
                $result['contact_id'],
                $result['contact_name'],
                $result['role' ]
            );
        }

        return $contactShares;
    }

    /**
     * Find the shared contact groups for a playlist.
     *
     * @param int $playlistId
     * @return PlaylistContactGroupShare[]
     */
    private function findContactGroupShareByPlaylistId(int $playlistId): array
    {
        $query = <<<'SQL'
            SELECT
                dpcgr.contactgroup_id,
                cg.cg_name,
                dpcgr.role AS contactgroup_role
            FROM
                `:db`.`dashboard_playlist` AS dp
                INNER JOIN `:db`.`dashboard_playlist_contactgroup_relation` dpcgr ON dpcgr.playlist_id = dp.id
                INNER JOIN `:db`.`contactgroup` AS cg ON cg.cg_id = dpcgr.contactgroup_id
            WHERE
                dp.id = :playlistId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $contactGroupShares = [];
        foreach ($statement as $result) {
            $contactGroupShares[] = new PlaylistContactGroupShare(
                $playlistId,
                $result['contactgroup_id'],
                $result['cg_name'],
                $result['role' ]
            );
        }

        return $contactGroupShares;
    }

    /**
     * Find the contact groups shared to a playlist by given playlist id and contact group ids.
     *
     * @param integer $playlistId
     * @param array $contactGroupIds
     * @return array
     */
    private function findContactGroupShareByPlaylistIdAndContactGroupIds(int $playlistId, array $contactGroupIds): array
    {
        $bindContactGroups = [];
        foreach($contactGroupIds as $key => $contactGroupId) {
            $bindContactGroups[':cg_' . $key] = $contactGroupId;
        }

        if ([] === $bindContactGroups) {
            return [];
        }

        $bindContactGroupsString = implode(', ', array_keys($bindContactGroups));

        $query = <<<SQL
        SELECT
            dpcgr.contactgroup_id,
            cg.cg_name,
            dpcgr.role AS contactgroup_role
        FROM
            `:db`.`dashboard_playlist` AS dp
            INNER JOIN `:db`.`dashboard_playlist_contactgroup_relation` dpcgr ON dpcgr.playlist_id = dp.id
            INNER JOIN `:db`.`contactgroup` AS cg ON cg.cg_id = dpcgr.contactgroup_id
        WHERE
            dp.id = :playlistId
        AND
            dpcgr.contactgroup_id IN ({$bindContactGroupsString})
        SQL;

    $statement = $this->db->prepare($this->translateDbName($query));
    $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
    foreach ($bindContactGroups as $token => $contactGroupId) {
        $statement->bindValue($token, $contactGroupId, \PDO::PARAM_INT);
    }
    $statement->setFetchMode(\PDO::FETCH_ASSOC);
    $statement->execute();

    $contactGroupShares = [];
    foreach ($statement as $result) {
        $contactGroupShares[] = new PlaylistContactGroupShare(
            $playlistId,
            $result['contactgroup_id'],
            $result['cg_name'],
            $result['role' ]
        );
    }

    return $contactGroupShares;
    }
}
