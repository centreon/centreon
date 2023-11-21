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
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Domain\Model\NewPlaylist;

class DbWritePlaylistRepository extends AbstractRepositoryRDB implements WritePlaylistRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function add(NewPlaylist $playlist): int
    {
        $query = <<<'SQL'
                INSERT INTO `:db`.dashboard_playlist (name, description, rotation_time, created_at, created_by, is_public)
                VALUES (:name, :description, :rotationTime, :createdAt, :createdBy, :isPublic)
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':name', $playlist->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $playlist->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':rotationTime', $playlist->getRotationTime(), \PDO::PARAM_INT);
        $statement->bindValue(':createdAt', $playlist->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->bindValue(':createdBy', $playlist->getAuthor()?->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':isPublic', $playlist->isPublic(), \PDO::PARAM_INT);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function addDashboardsToPlaylist(int $playlistId, array $dashboardsOrder): void
    {
        $query = <<<'SQL'
            INSERT INTO `:db`.dashboard_playlist_relation VALUES (:dashboardId, :playlistId, :order)
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        foreach ($dashboardsOrder as $dashboardOrder) {
            $statement->bindValue(':dashboardId', $dashboardOrder->getDashboardId(), \PDO::PARAM_INT);
            $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
            $statement->bindValue(':order', $dashboardOrder->getOrder(), \PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
