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
 *     dashboard_ids: ?string,
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
                    dpl.*, GROUP_CONCAT(dplr.dashboard_id) as dashboard_ids
                FROM `:db`.dashboard_playlist AS dpl
                LEFT JOIN `:db`.dashboard_playlist_relation dplr
                    ON dpl.id = dplr.playlist_id
                WHERE dpl.id = :playlistId
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_STR);
        $statement->execute();

        /** @var false|_Playlist $data */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

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
     * @inheritDoc
     */
    public function exists(int $id): bool
    {
        $query = <<<'SQL'
            SELECT
                1
            FROM
                `:db`.`dashboard_playlist` dpl
            WHERE
                dpl.id = :id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    public function findDashboardOrders($playlistId, array $dashboards): array
    {
        $bind = [];
        foreach ($dashboards as $key => $dashboard) {
            $bind[':dashboard_' . $key] = $dashboard->getId();
        }
        if ([] === $bind) {
            return [];
        }
        $dashboardIdsAsString = implode(', ', array_keys($bind));
        $query = <<<SQL
            SELECT dashboard_id, `order` FROM `:db`.`dashboard_playlist_relation`
            WHERE playlist_id = :playlistId
            AND dashboard_id IN ({$dashboardIdsAsString})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':playlistId', $playlistId, \PDO::PARAM_INT);
        foreach ($bind as $token => $dashboardId) {
            $statement->bindValue($token, $dashboardId, \PDO::PARAM_INT);
        }
        $statement->execute();

        /** @var false|array<array{dashboard_id: int, order: int}> $data */
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $data ? $this->createDashboardsOrderFromRecord($data) : [];
    }

    /**
     * @param _Playlist $data
     *
     * @return Playlist
     */
    private function createPlaylistFromRecord(array $data): Playlist
    {
        $playlist = new Playlist(
            $data['id'],
            $data['name'],
            $data['rotation_time'],
            (bool) $data['is_public'],
            (new \DateTimeImmutable())->setTimestamp($data['created_at'])
        );
        $playlist->setDescription($data['description']);
        $playlist->setAuthorId($data['created_by']);
        $dashboardIds = $data['dashboard_ids'] ? explode(',', $data['dashboard_ids']) : [];
        foreach ($dashboardIds as $dashboardId) {
            $playlist->addDashboardId((int) $dashboardId);
        }

        return $playlist;
    }

    /**
     * @param array<array{dashboard_id: int, order: int}> $data
     *
     * @return DashboardOrder[]
     */
    private function createDashboardsOrderFromRecord(array $data): array
    {
        $dashboardsOrder = [];
        foreach ($data as $row) {
            $dashboardsOrder[] = new DashboardOrder($row['dashboard_id'], $row['order']);
        }

        return $dashboardsOrder;
    }
}
