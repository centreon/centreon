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

class DbWriteDashboardRepository extends AbstractRepositoryRDB implements WriteDashboardRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function add(NewDashboard $newDashboard): int
    {
        $insert = <<<'SQL'
            INSERT INTO `:db`.`dashboard`
                (
                    name,
                    description,
                    created_at,
                    updated_at
                )
            VALUES
                (
                    :name,
                    :description,
                    :created_at,
                    :updated_at
                )
            SQL;

        $statement = $this->db->prepare($this->translateDbName($insert));
        $this->bindValueOfDashboard($statement, $newDashboard);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param \PDOStatement $statement
     * @param Dashboard|NewDashboard $newDashboard
     */
    private function bindValueOfDashboard(\PDOStatement $statement, Dashboard|NewDashboard $newDashboard): void
    {
        $statement->bindValue(':name', $newDashboard->getName());
        $statement->bindValue(':description', $this->emptyStringAsNull($newDashboard->getDescription()));
        $statement->bindValue(':created_at', $newDashboard->getCreatedAt()->getTimestamp());
        $statement->bindValue(':updated_at', $newDashboard->getUpdatedAt()->getTimestamp());
    }
}
