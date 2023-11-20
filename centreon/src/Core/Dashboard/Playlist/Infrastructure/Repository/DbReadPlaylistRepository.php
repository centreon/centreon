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
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Domain\Model\DashboardOrder;
use Core\Dashboard\Playlist\Domain\Model\Playlist;
use Core\Dashboard\Playlist\Domain\Model\PlaylistAuthor;

/**
 * @phpstan-type _Playlist array{
 *     id: int,
 *     name: string,
 *     description: ?string,
 *     rotation_time: int,
 *     created_by: ?int,
 *     updated_by: ?int,
 *     created_at: int,
 *     updated_at: ?int,
 *     is_public: int,
 *     contact_alias: ?string,
 *     dashboard_id: ?int,
 *     order: ?int
 * }
 */
class DbReadPlaylistRepository extends AbstractRepositoryRDB implements ReadPlaylistRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function find(int $playlistId): ?Playlist
    {
        $query = <<<'SQL'
                SELECT
                    dpl.*, c.contact_alias, dplr.*
                FROM `:db`.dashboard_playlist AS dpl
                LEFT JOIN `:db`.contact AS c
                    ON dpl.created_by = c.contact_id
                LEFT JOIN `:db`.dashboard_playlist_relation dplr
                    ON dpl.id = dplr.playlist_id
                WHERE dpl.id = :playlistId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_STR);
        $statement->execute();

        /** @var false|_Playlist[] $data */
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $data ? $this->createPlaylistFromRecord($data) : null;
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $name): bool
    {
        $query = <<<'SQL'
            SELECT
                1
            FROM
                `:db`.`dashboard_playlist` dpl
            WHERE
                dpl.name = :name
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':name', $name, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param _Playlist[] $data
     *
     * @return Playlist
     */
    private function createPlaylistFromRecord(array $data): Playlist
    {
        $playlistConfiguration = $data[0];
        $playlist = new Playlist(
            $playlistConfiguration['id'],
            $playlistConfiguration['name'],
            $playlistConfiguration['rotation_time'],
            (bool) $playlistConfiguration['is_public']
        );
        $playlist->setDescription($playlistConfiguration['description']);
        if ($playlistConfiguration['contact_alias'] !== null && $playlistConfiguration['created_by'] !== null) {
            $playlist->setAuthor(new PlaylistAuthor($playlistConfiguration['created_by'], $playlistConfiguration['contact_alias']));
        }
        $playlist->setCreatedAt((new \DateTimeImmutable())->setTimestamp($playlistConfiguration['created_at']));
        $dashboardsOrder = [];
        foreach ($data as $recordRow) {
            if ($recordRow['dashboard_id'] !== null && $recordRow['order'] !== null) {
                $dashboardsOrder[] = new DashboardOrder($recordRow['dashboard_id'], $recordRow['order']);
            }
        }
        $playlist->setDashboardsOrder($dashboardsOrder);

        return $playlist;
    }
}
