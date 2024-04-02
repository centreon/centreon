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

namespace Core\Migration\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Migration\Application\Repository\ReadMigrationRepositoryInterface;
use Core\Migration\Domain\Model\ExecutedMigration;
use Core\Migration\Domain\Model\NewMigration;

class DbReadMigrationRepository extends AbstractRepositoryRDB implements ReadMigrationRepositoryInterface
{
    use LoggerTrait;

    /** @var MigrationInterface[] */
    private $migrations;

    public function __construct(
        DatabaseConnection $db,
        \Traversable $migrations,
    ) {
        $this->db = $db;

        if (iterator_count($migrations) === 0) {
            throw new \Exception('Migrations not found');
        }

        $this->migrations = iterator_to_array($migrations);
    }

    /**
     * {@inheritDoc}
     */
    public function findAvailableMigrations(): array
    {
        $migrations = [];

        foreach ($this->migrations as $migration) {
            $shortName = (new \ReflectionClass($migration))->getShortName();

            $migrations[] = new NewMigration(
                $shortName,
                $migration->getModuleName(),
                $migration->getDescription(),
            );
        }

        return $migrations;
    }

    /**
     * {@inheritDoc}
     */
    public function findExecutedMigrations(): array
    {
        $result = $this->db->query($this->translateDbName('SHOW TABLES FROM `:db` LIKE "migrations"'));
        if (! $result || $result->rowCount() === 0) {
            $this->notice('Migrations table does not exist yet, considering no migrations has been done.');

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

        if (! $statement) {
            return [];
        }

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $migrations = [];
        foreach ($result as $migrationData) {
            $migrations[] = new ExecutedMigration(
                $migrationData['name'],
                $migrationData['module_name'] ?: ExecutedMigration::CORE_MODULE_NAME,
                $migrationData['id'],
                (new \DateTime())->setTimestamp($migrationData['executed_at']),
            );
        }

        return $migrations;
    }

    /**
     * {@inheritDoc}
     */
    public function findNewMigrations(): array
    {
        $availableMigrations = $this->findAvailableMigrations();

        $executedMigrations = $this->findExecutedMigrations();

        return array_filter(
            $availableMigrations,
            function ($availableMigration) use (&$executedMigrations) {
                $availableMigrationName = $availableMigration->getName();
                $availableMigrationModuleName = $availableMigration->getModuleName();

                foreach ($executedMigrations as $executedMigrationKey => $executedMigration) {
                    if (
                        $availableMigrationName === $executedMigration->getName()
                        && $availableMigrationModuleName === $executedMigration->getModuleName()
                    ) {
                        unset($executedMigrations[$executedMigrationKey]);

                        return false;
                    }
                }

                return true;
            }
        );
    }
}
