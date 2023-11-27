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
                AND dpcgr.contactgroup_id IN (SELECT contactgroup_cg_id FROM contactgroup_contact_relation WHERE contact_contact_id = 1)
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
}