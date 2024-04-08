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

namespace Migrations;

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;

class Migration000000000001 extends AbstractCoreMigration implements MigrationInterface
{
    use LoggerTrait;

    /**
     * @param ReadVersionRepositoryInterface $readVersionRepository
     * @param WriteMigrationRepositoryInterface $writeMigrationRepository
     * @param \Traversable<LegacyMigrationInterface> $legacyMigrations
     */
    public function __construct(
        private readonly ReadVersionRepositoryInterface $readVersionRepository,
        private readonly WriteMigrationRepositoryInterface $writeMigrationRepository,
        private readonly \Traversable $legacyMigrations,
    ) {
        if (iterator_count($legacyMigrations) === 0) {
            throw new \Exception('Legacy migrations not found');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return _('Synchronization of migrations');
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $currentVersion = $this->readVersionRepository->findCurrentVersion();
        if ($currentVersion === null) {
            throw new \Exception('Cannot retrieve Centreon web version');
        }

        foreach ($this->legacyMigrations as $legacyMigration) {
            if (
                $legacyMigration->getModuleName() === $this->getModuleName()
                && version_compare($currentVersion, $legacyMigration->getVersion(), '>=')
            ) {
                $migration = new NewMigration(
                    $legacyMigration->getName(),
                    $legacyMigration->getModuleName(),
                    $legacyMigration->getDescription()
                );
                $this->writeMigrationRepository->storeMigration($migration);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
