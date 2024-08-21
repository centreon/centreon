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

namespace Core\Platform\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Platform\Application\Repository\ReadUpdateRepositoryInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FsReadUpdateRepository implements ReadUpdateRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param string $installDir
     * @param Filesystem $filesystem
     * @param Finder $finder
     */
    public function __construct(
        private string $installDir,
        private Filesystem $filesystem,
        private Finder $finder,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findOrderedAvailableUpdates(string $currentVersion): array
    {
        $availableUpdates = $this->findAvailableUpdates($currentVersion);

        return $this->orderUpdates($availableUpdates);
    }

    /**
     * Get available updates.
     *
     * @param string $currentVersion
     *
     * @return string[]
     */
    private function findAvailableUpdates(string $currentVersion): array
    {

        $fileNameVersionRegex = '/Update-(?<version>[a-zA-Z0-9\-\.]+)\.php/';
        $updates = [];

        if ($this->filesystem->exists($this->installDir)) {
            $files = $this->finder->files()
                ->in($this->installDir)
                ->name($fileNameVersionRegex);

            foreach ($files as $file) {
                if (!preg_match($fileNameVersionRegex, $file->getFilename(), $matches)) {
                    continue;
                }
                if (!version_compare($matches['version'], $currentVersion, '>')) {
                    continue;
                }
                $updates[] = $matches['version'];
            }
        }

        return $updates;
    }

    /**
     * Order updates.
     *
     * @param string[] $updates
     *
     * @return string[]
     */
    private function orderUpdates(array $updates): array
    {
        usort(
            $updates,
            fn (string $versionA, string $versionB) => version_compare($versionA, $versionB),
        );

        return $updates;
    }
}
