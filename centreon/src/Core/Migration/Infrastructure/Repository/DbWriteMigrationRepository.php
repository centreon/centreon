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

use CentreonUserLog;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;
use Pimple\Container;

class DbWriteMigrationRepository extends AbstractRepositoryRDB implements WriteMigrationRepositoryInterface
{
    use LoggerTrait;

    /** @var MigrationInterface[] */
    private $migrations;

    /** @var CentreonUserLog */
    private CentreonUserLog $centreonLog;

    public function __construct(
        DatabaseConnection $db,
        \Traversable $migrations,
        Container $dependencyInjector,
    ) {
        $this->db = $db;

        if (iterator_count($migrations) === 0) {
            throw new \Exception('Migrations not found');
        }

        $this->migrations = iterator_to_array($migrations);

        $pearDB = $dependencyInjector['configuration_db'];
        $this->centreonLog = new CentreonUserLog(-1, $pearDB);
    }

    /**
     * {@inheritDoc}
     */
    public function executeMigration(NewMigration $newMigration): void
    {
        $migration = $this->getMigrationClass($newMigration);

        $this->info(sprintf('Run migration %s.', $newMigration->getName()));
        try {
            $migration->up();
            $this->centreonLog->insertLog(
                CentreonUserLog::TYPE_UPGRADE,
                sprintf(
                    ' [%s] [%s] %s: Success',
                    $newMigration->getModuleName(),
                    $newMigration->getName(),
                    $newMigration->getDescription()
                )
            );
        } catch (\Exception $e) {
            $this->centreonLog->insertLog(
                CentreonUserLog::TYPE_UPGRADE,
                sprintf(
                    ' [%s] [%s] %s: %s',
                    $newMigration->getModuleName(),
                    $newMigration->getName(),
                    $newMigration->getDescription(),
                    $e->getMessage()
                )
            );
            throw $e;
        }


        $this->storeMigration($newMigration);
    }

    private function getMigrationClass(NewMigration $newMigration): MigrationInterface
    {
        foreach ($this->migrations as $migration) {
            $shortName = (new \ReflectionClass($migration))->getShortName();
            if (
                $migration->getModuleName() === $newMigration->getModuleName()
                && $shortName === $newMigration->getName()
            ) {
                return $migration;
            }
        }

        throw new \Exception(sprintf('Migration %s not found', $newMigration->getName()));
    }

    private function storeMigration(NewMigration $newMigration): void
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
