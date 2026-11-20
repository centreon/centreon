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

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Log\FailSafeLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MigrationSetRegistry
{
    private const string WEB_TABLE = 'doctrine_migration_versions';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        #[Autowire(param: 'upgrade.modules_dir')]
        private string $modulesDir,
        #[Autowire(service: FailSafeLogger::class)]
        private LoggerInterface $logger,
    ) {
    }

    public function configuration(MigrationSet $set): MigrationSetConfiguration
    {
        if ($set->module !== null) {
            return new MigrationSetConfiguration(
                'default',
                $this->moduleNamespace($set->module),
                $this->modulesDir . '/' . $set->module . '/migrations',
                $this->table($set),
            );
        }

        return match ($set->webSet) {
            WebMigrationSetEnum::Realtime => new MigrationSetConfiguration(
                'realtime',
                'Centreon\Migrations\Realtime',
                $this->projectDir . '/migrations/realtime',
                $this->table($set),
            ),
            WebMigrationSetEnum::Configuration => new MigrationSetConfiguration(
                'default',
                'Centreon\Migrations\Configuration',
                $this->projectDir . '/migrations/configuration',
                $this->table($set),
            ),
            null => throw new \LogicException(sprintf('The %s is neither a module nor a Centreon Web set.', $set->label())),
        };
    }

    /**
     * @return list<MigrationSet> the Centreon Web sets in execution order, then every module set not ignored (see ignored())
     */
    public function all(): array
    {
        [$sets, $ignored] = $this->scan();
        foreach ($ignored as $reason) {
            $this->logger->warning($reason);
        }

        return $sets;
    }

    /**
     * @return list<string> why each ignored module directory is not a set
     */
    public function ignored(): array
    {
        return $this->scan()[1];
    }

    /**
     * @return array{list<MigrationSet>, list<string>}
     */
    private function scan(): array
    {
        $modulesByNamespace = [];
        $ignored = [];

        // GLOB_ERR: an unreadable modules directory is reported, not read as empty.
        $moduleDirectories = glob($this->modulesDir . '/*', \GLOB_ONLYDIR | \GLOB_ERR);
        if ($moduleDirectories === false) {
            $moduleDirectories = [];
            if (file_exists($this->modulesDir)) {
                $ignored[] = sprintf('Cannot list the modules of "%s": check its permissions.', $this->modulesDir);
            }
        }

        foreach ($moduleDirectories as $moduleDirectory) {
            $directory = $moduleDirectory . '/migrations';
            if (! is_dir($directory)) {
                if (! is_executable($moduleDirectory)) {
                    $ignored[] = sprintf('Cannot read the module directory "%s": check its permissions.', $moduleDirectory);
                } elseif (file_exists($directory) || is_link($directory)) {
                    $ignored[] = sprintf('Ignored the migrations of "%s": it is not a directory.', $directory);
                }

                continue;
            }

            $module = basename($moduleDirectory);
            // A stray directory ("centreon-foo.bak") must not break the other sets.
            if (! MigrationSet::isModuleName($module)) {
                $ignored[] = sprintf('Ignored the migrations of "%s": its name is not a supported module name.', $directory);

                continue;
            }

            // Dashes are dropped and PHP namespaces are case-insensitive: loading both sets would redeclare their classes.
            $modulesByNamespace[mb_strtolower($this->moduleNamespace($module))][] = $module;
        }

        $sets = MigrationSet::webSets();

        foreach ($modulesByNamespace as $modules) {
            if (count($modules) > 1) {
                $ignored[] = sprintf('Ignored the migrations of the modules "%s": they map to the same PHP namespace when case is ignored.', implode('", "', $modules));

                continue;
            }

            $sets[] = MigrationSet::module($modules[0]);
        }

        return [$sets, $ignored];
    }

    private function moduleNamespace(string $module): string
    {
        return str_replace('-', '', ucwords($module, '-')) . '\Migrations';
    }

    /**
     * Each Centreon Web set has its own table in its database, each module a suffixed one.
     */
    private function table(MigrationSet $set): string
    {
        return $set->module === null ? self::WEB_TABLE : self::WEB_TABLE . '_' . str_replace('-', '_', $set->module);
    }
}
