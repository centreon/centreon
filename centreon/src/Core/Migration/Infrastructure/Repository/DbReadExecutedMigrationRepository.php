<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Migration\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Migration\Application\Repository\ReadExecutedMigrationRepositoryInterface;
use Core\Migration\Domain\Model\Migration;

class DbReadExecutedMigrationRepository extends AbstractRepositoryRDB implements ReadExecutedMigrationRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        $result = $this->db->query($this->translateDbName('SHOW TABLES FROM `:db` LIKE "migrations"'));
        if ($result->rowCount() === 0) {
            $this->logger->notice('Migrations table does not exist yet, considering not migrations has been done.');
            return [];
        }

        $query = $this->translateDbName(
            <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS m.id, m.name, mi.name as module_name, m.executed_at
                FROM `:db`.migrations m
                LEFT JOIN modules_informations mi ON mi.id = m.module_id
                SQL
        );

        $statement = $this->db->query($query);

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $migrations = [];
        foreach ($result as $migrationData) {
            $migrations[] = new Migration(
                $migrationData['id'],
                $migrationData['name'],
                $migrationData['module_name'],
                new \DateTime($migrationData['executed_at']),
            );
        }

        return $migrations;
    }
}
