<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Infrastructure\FileSystem;

use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionValue;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\Plugin;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\PluginName;
use App\MonitoringConfiguration\Domain\Repository\PluginRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final readonly class FileSystemPluginRepository implements PluginRepository
{
    private Finder $finder;

    public function __construct()
    {
        $this->finder = new Finder();
    }

    public function findByPath(OptionValue $path): Collection
    {
        $pluginInfos = $this->finder->files()->in($path->value);

        return new Collection(
            array_map(
                static fn (SplFileInfo $plugin): Plugin => new Plugin(new PluginName($plugin->getFilename())),
                iterator_to_array($pluginInfos->getIterator())
            ),
            Plugin::class
        );
    }
}
