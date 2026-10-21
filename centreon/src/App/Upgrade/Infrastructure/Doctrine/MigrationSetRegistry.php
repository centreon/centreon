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

    public function configuration(MigrationSet $set): MigrationSetConfiguration
    {
        return match (true) {
            $set->module !== null => new MigrationSetConfiguration(
                'default',
                str_replace('-', '', ucwords($set->module, '-')) . '\Migrations',
                $this->modulesDir . '/' . $set->module . '/migrations',
                (string) MigrationSetConfiguration::moduleTable($set->module),
            ),
            $set->name === MigrationSet::REALTIME => new MigrationSetConfiguration(
                'realtime',
                'Centreon\Migrations\Realtime',
                $this->projectDir . '/migrations/realtime',
                MigrationSetConfiguration::CORE_TABLE,
            ),
            default => new MigrationSetConfiguration(
                'default',
                'Centreon\Migrations\Configuration',
                $this->projectDir . '/migrations/configuration',
                MigrationSetConfiguration::CORE_TABLE,
            ),
        };
    }

    /**
     * @return list<MigrationSet> the core sets in execution order, then every module shipping migrations
     */
    public function all(): array
    {
        $sets = MigrationSet::coreSets();

        foreach (glob($this->modulesDir . '/*/migrations', \GLOB_ONLYDIR) ?: [] as $directory) {
            $module = basename(\dirname($directory));
            // A stray directory ("centreon-foo.bak") must not break the other sets.
            if (MigrationSet::isModuleName($module)) {
                $sets[] = MigrationSet::module($module);
            }
        }

        return $sets;
    }
}
