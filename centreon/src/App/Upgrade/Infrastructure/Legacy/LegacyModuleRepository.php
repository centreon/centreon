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

namespace App\Upgrade\Infrastructure\Legacy;

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\ServiceProvider;
use Doctrine\DBAL\Connection;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Extension Manager engine, the one module upgrade scripts are written for.
 */
final readonly class LegacyModuleRepository implements ModuleRepository
{
    public function __construct(
        #[Autowire(service: 'upgrade.legacy_container')]
        private Container $legacyContainer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(param: 'upgrade.modules_dir')]
        private string $modulesDir,
    ) {
    }

    public function updateAll(): void
    {
        /** @var CentreonModuleService $moduleService */
        $moduleService = $this->legacyContainer[ServiceProvider::CENTREON_MODULE];
        /** @var CacheClearer $cacheClearer */
        $cacheClearer = $this->legacyContainer[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER];

        // UpdateCommandHandler clears them once, at the end.
        $cacheClearer->suspended(static fn (): array => $moduleService->updateAll(ModuleSource::TYPE));
    }

    /**
     * Its row only: the legacy engine would load its conf.php and read its pending migrations.
     */
    public function isInstalled(string $module): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        return $queryBuilder
            ->select('1')
            ->from('modules_informations')
            ->where('name = ' . $queryBuilder->createNamedParameter($module))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne() !== false;
    }

    /**
     * The versions the Upgrader loop runs: a migrate it launches itself, once they ran, is not refused.
     *
     * @throws \UnexpectedValueException
     */
    public function hasPendingLegacyUpgrade(string $module): bool
    {
        $installedRelease = $this->findInstalledRelease($module);
        $moduleDirectory = $this->modulesDir . '/' . $module;
        // GLOB_ERR: an unreadable upgrade directory is reported, not read as empty.
        $upgradeDirectories = glob($moduleDirectory . '/upgrade/*', \GLOB_ONLYDIR | \GLOB_ERR);
        if ($upgradeDirectories === false && file_exists($moduleDirectory . '/upgrade')) {
            throw new \UnexpectedValueException(sprintf('Cannot list the upgrade scripts of the "%s" module: check the permissions of "%s".', $module, $moduleDirectory . '/upgrade'));
        }

        if ($installedRelease === null || $upgradeDirectories === false || $upgradeDirectories === []) {
            return false;
        }

        $codeRelease = $this->codeRelease($module, $moduleDirectory . '/conf.php');
        foreach ($upgradeDirectories as $upgradeDirectory) {
            $version = basename($upgradeDirectory);
            if (preg_match('/^\d+\.\d+\.\d+/', $version) === 1
                && version_compare($version, $installedRelease) > 0
                && version_compare($version, $codeRelease) <= 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function findInstalledModules(): array
    {
        $names = $this->connection->createQueryBuilder()
            ->select('name')
            ->from('modules_informations')
            ->orderBy('name')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * Its metadata table is only created by its first run.
     */
    public function countExecutedMigrations(string $module): int
    {
        $table = MigrationSetRegistry::table(MigrationSet::module($module));
        if (! $this->connection->createSchemaManager()->tablesExist([$table])) {
            return 0;
        }

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->connection->quoteSingleIdentifier($table));

        return is_numeric($count) ? (int) $count : 0;
    }

    private function findInstalledRelease(string $module): ?string
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $release = $queryBuilder
            ->select('mod_release')
            ->from('modules_informations')
            ->where('name = ' . $queryBuilder->createNamedParameter($module))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return is_string($release) ? $release : null;
    }

    /**
     * @throws \UnexpectedValueException
     */
    private function codeRelease(string $module, string $configurationFile): string
    {
        if (! is_file($configurationFile)) {
            throw new \UnexpectedValueException(sprintf('Cannot read the version of the "%s" module: "%s" is missing.', $module, $configurationFile));
        }

        // As the legacy engine reads it, in a scope of its own.
        $configuration = (static function (string $file): mixed {
            $module_conf = [];
            require $file;

            /** @var mixed $module_conf set by the required file */
            return $module_conf;
        })($configurationFile);
        $release = is_array($configuration) && is_array($configuration[$module] ?? null) ? ($configuration[$module]['mod_release'] ?? null) : null;
        if (! is_string($release)) {
            throw new \UnexpectedValueException(sprintf('Cannot read the version of the "%s" module: "%s" sets no mod_release.', $module, $configurationFile));
        }

        return $release;
    }
}
