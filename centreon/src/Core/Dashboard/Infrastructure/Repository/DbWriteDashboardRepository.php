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

namespace Core\Dashboard\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\NewDashboard;
use Core\Dashboard\Infrastructure\Model\RefreshTypeConverter;
use Core\Media\Domain\Model\Media;

class DbWriteDashboardRepository extends AbstractRepositoryRDB implements WriteDashboardRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function addThumbnailRelation(Dashboard $dashboard, Media $thumbnail): void {
        $request = <<<'SQL'
                INSERT INTO `:db`.dashboard_thumbnail_relation
                    (
                        dashboard_id,
                        img_id
                    )
                VALUES
                    (
                        :dashboardId,
                        :thumbnailId
                    )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':dashboardId', $dashboard->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':thumbnailId', $thumbnail->getId(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $dashboardId): void
    {
        $this->info('Delete dashboard', ['id' => $dashboardId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`dashboard`
            WHERE id = :dashboard_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':dashboard_id', $dashboardId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function add(NewDashboard $newDashboard): int
    {
        $insert = <<<'SQL'
            INSERT INTO `:db`.`dashboard`
                (
                    name,
                    description,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at,
                    refresh_type,
                    refresh_interval
                )
            VALUES
                (
                    :name,
                    :description,
                    :created_by,
                    :updated_by,
                    :created_at,
                    :updated_at,
                    :refresh_type,
                    :refresh_interval
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($insert));
        $this->bindValueOfDashboard($statement, $newDashboard);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * {@inheritDoc}
     */
    public function update(Dashboard $dashboard): void
    {
        $update = <<<'SQL'
            UPDATE `:db`.`dashboard`
            SET
                `name` = :name,
                `description` = :description,
                `updated_by` = :updated_by,
                `updated_at` = :updated_at,
                `refresh_type` = :refresh_type,
                `refresh_interval` =:refresh_interval
            WHERE
                `id` = :dashboard_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($update));
        $statement->bindValue(':dashboard_id', $dashboard->getId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':refresh_type',
            RefreshTypeConverter::toString($dashboard->getRefresh()->getRefreshType()),
            \PDO::PARAM_STR
        );
        $statement->bindValue(
            ':refresh_interval',
            $dashboard->getRefresh()->getRefreshInterval(),
            \PDO::PARAM_INT
        );
        $this->bindValueOfDashboard($statement, $dashboard);
        $statement->execute();
    }

    /**
     * @param \PDOStatement $statement
     * @param Dashboard|NewDashboard $dashboard
     */
    private function bindValueOfDashboard(\PDOStatement $statement, Dashboard|NewDashboard $dashboard): void
    {
        $statement->bindValue(':name', $dashboard->getName());
        $statement->bindValue(':description', $dashboard->getDescription());
        $statement->bindValue(':updated_at', $dashboard->getUpdatedAt()->getTimestamp());
        $statement->bindValue(':updated_by', $dashboard->getUpdatedBy());

        if ($dashboard instanceof NewDashboard) {
            $statement->bindValue(':created_at', $dashboard->getCreatedAt()->getTimestamp());
            $statement->bindValue(':created_by', $dashboard->getCreatedBy());
            $statement->bindValue(':refresh_type', RefreshTypeConverter::toString($dashboard->getRefresh()->getRefreshType()), \PDO::PARAM_STR);
            $statement->bindValue(':refresh_interval', $dashboard->getRefresh()->getRefreshInterval(), \PDO::PARAM_INT);
        }
    }
}
