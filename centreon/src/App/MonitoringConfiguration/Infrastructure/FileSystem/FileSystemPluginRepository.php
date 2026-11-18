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

namespace App\MonitoringConfiguration\Infrastructure\FileSystem;

use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionValue;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\Plugin;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\PluginCommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\PluginDescription;
use App\MonitoringConfiguration\Domain\Aggregate\Plugin\PluginName;
use App\MonitoringConfiguration\Domain\Exception\PluginNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PluginRepository;
use App\Shared\Domain\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

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
                static fn (SplFileInfo $plugin): Plugin => new Plugin(
                    name: new PluginName($plugin->getFilename()),
                    commandLine: new PluginCommandLine($plugin->getRealPath()),
                ),
                iterator_to_array($pluginInfos->getIterator())
            ),
            Plugin::class
        );
    }

    public function getByPathAndName(OptionValue $path, PluginName $name): Plugin
    {
        $pluginInfos = $this->finder->files()
            ->in($path->value)
            ->name($name->value);

        if (! $this->finder->hasResults()) {
            throw new PluginNotFoundException(['path' => $path->value, 'name' => $name->value]);
        }

        $plugin = current(iterator_to_array($pluginInfos));
        Assert::isInstanceOf($plugin, SplFileInfo::class);

        $process = new Process([$plugin->getRealPath(), '--help']);
        $process->run();

        $description = null;
        if ($process->isSuccessful() && ! empty($process->getOutput())) {
            $description = new PluginDescription(trim($process->getOutput()));
        }

        return new Plugin(
            name: new PluginName($plugin->getFilename()),
            commandLine: new PluginCommandLine($plugin->getRealPath()),
            description: $description
        );
    }
}
