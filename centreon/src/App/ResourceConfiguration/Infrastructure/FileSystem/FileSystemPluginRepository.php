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

namespace App\ResourceConfiguration\Infrastructure\FileSystem;

use App\ResourceConfiguration\Domain\Aggregate\Plugin;
use App\ResourceConfiguration\Domain\Aggregate\PluginName;
use App\ResourceConfiguration\Domain\Repository\PluginRepository;
use Symfony\Component\Finder\Finder;

final readonly class FileSystemPluginRepository implements PluginRepository
{
    private Finder $finder;

    public function __construct(
    ) {
        $this->finder = new Finder();
    }

    public function findByPath(string $path): array
    {
        $pluginInfos = $this->finder->files()->in($path);
        $plugins = [];
        foreach ($pluginInfos as $pluginInfo) {
            $plugins[] = $this->createPlugin($pluginInfo->getFilename());
        }

        return $plugins;
    }

    private function createPlugin(string $pluginName): Plugin
    {
        return new Plugin(new PluginName($pluginName));
    }
}
