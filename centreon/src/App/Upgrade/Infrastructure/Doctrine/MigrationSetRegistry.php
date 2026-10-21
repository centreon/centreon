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

namespace App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\MigrationSet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MigrationSetRegistry
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        #[Autowire(param: 'upgrade.modules_dir')]
        private string $modulesDir,
    ) {
    }

    /**
     * @throws \RuntimeException when the set is unknown
     */
    public function get(string $name): MigrationSet
    {
        $module = MigrationSet::moduleOf($name);
        $moduleTable = $module === null ? null : MigrationSet::moduleTable($module);

        return match (true) {
            $name === MigrationSet::REALTIME => new MigrationSet(
                $name,
                'realtime',
                'Centreon\Migrations\Realtime',
                $this->projectDir . '/migrations/realtime',
                MigrationSet::CORE_TABLE,
            ),
            $name === MigrationSet::CONFIGURATION => new MigrationSet(
                $name,
                'default',
                'Centreon\Migrations\Configuration',
                $this->projectDir . '/migrations/configuration',
                MigrationSet::CORE_TABLE,
            ),
            $module !== null && $moduleTable !== null => new MigrationSet(
                $name,
                'default',
                str_replace('-', '', ucwords($module, '-')) . '\Migrations',
                $this->modulesDir . '/' . $module . '/migrations',
                $moduleTable,
            ),
            default => throw new \RuntimeException(\sprintf('Unknown migration set "%s".', $name)),
        };
    }

    /**
     * @return list<MigrationSet> the core sets in execution order, then every module shipping migrations
     */
    public function all(): array
    {
        $sets = array_map($this->get(...), MigrationSet::CORE);

        foreach (glob($this->modulesDir . '/*/migrations', \GLOB_ONLYDIR) ?: [] as $directory) {
            $module = basename(\dirname($directory));
            // A stray directory ("centreon-foo.bak") must not break the other sets.
            if (MigrationSet::moduleTable($module) !== null) {
                $sets[] = $this->get(MigrationSet::forModule($module));
            }
        }

        return $sets;
    }
}
