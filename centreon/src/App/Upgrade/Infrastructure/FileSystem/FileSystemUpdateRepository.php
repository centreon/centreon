<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\Upgrade\Infrastructure\FileSystem;

use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final readonly class FileSystemUpdateRepository implements UpdateScriptFinder
{
    private const FILE_NAME_VERSION_REGEX = '/Update-(?<version>[a-zA-Z0-9\-\.]+)\.php/';

    public function __construct(
        #[Autowire(param: 'upgrade.install_dir')]
        private string $installDir,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function findOrderedAvailableUpdates(string $currentVersion): array
    {
        $updates = [];

        if ($this->filesystem->exists($this->installDir . '/php')) {
            $finder = new Finder();
            $files = $finder->files()
                ->in($this->installDir . '/php')
                ->name(self::FILE_NAME_VERSION_REGEX);

            foreach ($files as $file) {
                if (! preg_match(self::FILE_NAME_VERSION_REGEX, $file->getFilename(), $matches)) {
                    continue;
                }
                if (! version_compare($matches['version'], $currentVersion, '>')) {
                    continue;
                }
                $updates[] = $matches['version'];
            }
        }

        usort(
            $updates,
            fn (string $versionA, string $versionB): int => version_compare($versionA, $versionB),
        );

        if ($updates !== []) {
            $this->logger->info('Available updates found', ['updates' => $updates]);
        } else {
            $this->logger->info('No available updates to perform', ['current_version' => $currentVersion]);
        }

        return $updates;
    }
}
