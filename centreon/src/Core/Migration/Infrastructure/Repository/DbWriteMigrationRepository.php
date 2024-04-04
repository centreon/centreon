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
use CentreonLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;

class DbWriteMigrationRepository extends AbstractRepositoryRDB implements WriteMigrationRepositoryInterface
{
    use LoggerTrait;

    /** @var CentreonLog */
    private CentreonLog $centreonLog;

    /**
     * @param DatabaseConnection $db
     * @param \Traversable<MigrationInterface> $migrations
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly \Traversable $migrations,
    ) {
        $this->db = $db;

        $this->centreonLog = new CentreonLog();
    }

    /**
     * {@inheritDoc}
     */
    public function executeMigration(NewMigration $newMigration): void
    {
        $migration = $this->getMigrationInstance($newMigration);

        $this->info(sprintf('Run migration %s %s.', $newMigration->getModuleName(), $newMigration->getName()));
        try {
            $migration->up();
            $this->centreonLog->insertLog(
                CentreonLog::TYPE_UPGRADE,
                sprintf(
                    ' [%s] [%s] %s: Success',
                    $newMigration->getModuleName(),
                    $newMigration->getName(),
                    $newMigration->getDescription()
                )
            );
        } catch (\Exception $exception) {
            $this->error(sprintf('Migration %s %s failed: %s', $newMigration->getModuleName(), $newMigration->getName(), $exception->getMessage()), ['trace' => (string) $exception]);

            $this->centreonLog->insertLog(
                CentreonLog::TYPE_UPGRADE,
                sprintf(
                    ' [%s] [%s] %s: %s',
                    $newMigration->getModuleName(),
                    $newMigration->getName(),
                    $newMigration->getDescription(),
                    $exception->getMessage()
                )
            );

            throw $exception;
        }

        $this->storeMigration($newMigration);
    }

    /**
     * {@inheritDoc}
     */
    public function storeMigration(NewMigration $newMigration): void
    {
        $this->info(sprintf('Store migration %s in database.', $newMigration->getName()));

        $moduleId = $this->getModuleIdFromName($newMigration->getName());

        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.migrations
                (
                    module_id,
                    name,
                    executed_at
                )
                VALUES
                (
                    :module_id,
                    :name,
                    :executed_at
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':module_id', $moduleId, \PDO::PARAM_STR);
        $statement->bindValue(':name', $newMigration->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':executed_at', time(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Get migration instance from migration.
     *
     * @param NewMigration $newMigration
     *
     * @return MigrationInterface
     */
    private function getMigrationInstance(NewMigration $newMigration): MigrationInterface
    {
        foreach ($this->migrations as $migration) {
            if (
                $migration->getModuleName() === $newMigration->getModuleName()
                && $migration->getName() === $newMigration->getName()
            ) {
                return $migration;
            }
        }

        throw new \Exception(sprintf('Migration %s not found', $newMigration->getName()));
    }

    /**
     * Get module id from name.
     *
     * @param string $moduleName
     *
     * @return int|null
     */
    private function getModuleIdFromName(string $moduleName): ?int
    {
        $this->info(sprintf('Get id of module %s.', $moduleName));

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT id
                FROM `:db`.modules_informations
                WHERE name = :name
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $moduleName, \PDO::PARAM_STR);

        $statement->execute();

        $moduleId = null;
        if ($result = $statement->fetch()) {
            $moduleId = $result['id'];
        }

        return $moduleId;
    }
}
