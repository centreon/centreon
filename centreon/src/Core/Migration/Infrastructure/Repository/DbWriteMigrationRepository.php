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
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DbalMigrator;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\Version\MigrationFactory;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\MigratorConfigurationFactory;
use Doctrine\Migrations\Version\DbalExecutor;
use Doctrine\Migrations\Version\Version;

class DbWriteMigrationRepository extends AbstractRepositoryRDB implements WriteMigrationRepositoryInterface
{
    use LoggerTrait;

    private MigratorConfiguration $migratorConfiguration;

    private DbalMigrator $dbalMigrator;

    //private DbalExecutor $dbalExecutor;

    public function __construct(
        DatabaseConnection $db,
        private DependencyFactory $dependencyFactory,
        private MigrationFactory $migrationFactory,
        private Configuration $configuration,
    ) {
        $this->db = $db;
        $this->dbalMigrator = $dependencyFactory->getMigrator();
    }

    /**
     * {@inheritDoc}
     */
    public function executeMigration(string $name): void
    {
        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();
        $planCalculator = $this->dependencyFactory->getMigrationPlanCalculator();
        $version = new Version('Migrations\\' . $name);
        $migrationPlanList = $planCalculator->getPlanForVersions([$version], 'UP');
        $migratorConfiguration = (new MigratorConfiguration())
            ->setDryRun(false)
            ->setTimeAllQueries(true)
            ->setAllOrNothing(false);
        $this->dbalMigrator->migrate($migrationPlanList, $migratorConfiguration);
    }
}
