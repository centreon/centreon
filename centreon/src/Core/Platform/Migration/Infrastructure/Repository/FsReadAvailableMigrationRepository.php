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

namespace Core\Platform\Migration\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Platform\Migration\Application\Repository\ReadAvailableMigrationRepositoryInterface;
use Core\Platform\Migration\Domain\Model\NewMigration;
use Core\Platform\Migration\Application\Repository\MigrationInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FsReadAvailableMigrationRepository implements ReadAvailableMigrationRepositoryInterface
{
    use LoggerTrait;

    /** @var MigrationInterface[] */
    private $edgeMigrations;

    /**
     * @param string $installDir
     * @param \Traversable $edgeMigrations
     * @param Filesystem $filesystem
     * @param Finder $finder
     */
    public function __construct(
        private string $installDir,
        \Traversable $edgeMigrations,
        private Filesystem $filesystem,
        private Finder $finder,
    ) {
        if (iterator_count($edgeMigrations) === 0) {
            throw new \Exception('Migrations not found');
        }

        $this->edgeMigrations = iterator_to_array($edgeMigrations);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll($currentVersion): array
    {
        $legacyMigrations = $this->findLegacyMigrations($currentVersion);
        $edgeMigrations = $this->findEdgeMigrations();

        return [...$legacyMigrations, ...$edgeMigrations];
    }

    /**
     * Get available legacy migrations.
     *
     * @return string[]
     */
    private function findLegacyMigrations($currentVersion): array
    {
        $fileNameVersionRegex = '/Update-(?<version>[a-zA-Z0-9\-\.]+)\.php/';
        $migrations = [];

        if ($this->filesystem->exists($this->installDir)) {
            $files = $this->finder->files()
                ->in($this->installDir)
                ->name($fileNameVersionRegex);

            foreach ($files as $file) {
                if (preg_match($fileNameVersionRegex, $file->getFilename(), $matches)) {
                    if (version_compare($matches['version'], $currentVersion, '>')) {
                        $migrations[] = new NewMigration(
                            'Update-' . $matches['version'],
                            'core',
                            'Update to ' . $matches['version'],
                        );
                    }
                }
            }
        }

        return $migrations;
    }

    /**
     * Get available edge migrations.
     *
     * @return string[]
     */
    private function findEdgeMigrations(): array
    {
        $migrations = [];

        foreach ($this->edgeMigrations as $edgeMigration) {
            $shortName = (new \ReflectionClass($edgeMigration))->getShortName();

            $migrations[] = new NewMigration(
                $shortName,
                'core',
                $edgeMigration->getDescription(),
            );
        }

        return $migrations;
    }
}
