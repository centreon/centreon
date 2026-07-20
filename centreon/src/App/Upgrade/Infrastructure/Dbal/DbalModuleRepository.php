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

namespace App\Upgrade\Infrastructure\Dbal;

use App\Upgrade\Domain\Repository\ModuleRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

final class DbalModuleRepository implements ModuleRepository
{
    /** @var array<string, string> module name => installed version (cached per run) */
    private array $installedModuleVersions = [];

    /** @var array<string, array<string, mixed>> module name => conf.php config (cached per run) */
    private array $moduleConfigurations = [];

    /** @var string[] modules already upgraded in this run (prevents infinite recursion) */
    private array $alreadyUpgraded = [];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $configConnection,
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private readonly Connection $realtimeConnection,
        #[Autowire(param: 'upgrade.modules_dir')]
        private readonly string $modulesDir,
        #[Autowire(param: 'upgrade.centreon_path')]
        private readonly string $centreonPath,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function updateAll(): void
    {
        $this->installedModuleVersions = $this->getInstalledModuleVersions();
        $this->moduleConfigurations = $this->loadModuleConfigurations();
        $this->alreadyUpgraded = [];

        foreach (array_keys($this->installedModuleVersions) as $moduleName) {
            $this->upgradeModuleIfNeeded($moduleName);
        }
    }

    private function upgradeModuleIfNeeded(string $moduleName): void
    {
        if (in_array($moduleName, $this->alreadyUpgraded, true)) {
            return;
        }

        // Mark early to prevent infinite recursion on cyclic dependencies.
        $this->alreadyUpgraded[] = $moduleName;

        if (! isset($this->installedModuleVersions[$moduleName])) {
            return;
        }

        $codeConfig = $this->moduleConfigurations[$moduleName] ?? null;
        if ($codeConfig === null) {
            return;
        }

        $installedVersion = $this->installedModuleVersions[$moduleName];
        $codeVersion = $this->asString($codeConfig['mod_release'] ?? '');

        if ($codeVersion === '' || version_compare($installedVersion, $codeVersion) >= 0) {
            return;
        }

        $this->upgradeModuleDependencies($moduleName);

        $this->logger->info('Upgrading module', [
            'name' => $moduleName,
            'from' => $installedVersion,
            'to' => $codeVersion,
        ]);

        $moduleId = $this->getModuleIdByName($moduleName);
        if ($moduleId === null) {
            return;
        }

        $this->updateModuleMetadata($moduleId, $codeConfig);
        $this->runModuleUpgradeScripts($moduleName, $moduleId, $installedVersion, $codeVersion);
        $this->updateModuleVersion($moduleId, $codeVersion);

        $this->installedModuleVersions[$moduleName] = $codeVersion;
    }

    private function upgradeModuleDependencies(string $moduleName): void
    {
        $dependencies = $this->getModuleDependencies($moduleName);
        foreach ($dependencies as $dependency) {
            if (isset($this->installedModuleVersions[$dependency])) {
                $this->upgradeModuleIfNeeded($dependency);
            }
        }
    }

    /**
     * @param string[] $alreadyProcessed
     *
     * @return string[]
     */
    private function getModuleDependencies(string $moduleName, array $alreadyProcessed = []): array
    {
        if (in_array($moduleName, $alreadyProcessed, true)) {
            return [];
        }

        $alreadyProcessed[] = $moduleName;
        $config = $this->moduleConfigurations[$moduleName] ?? null;
        if ($config === null || ! isset($config['require'])) {
            return [];
        }

        $dependencies = [];
        $requires = is_array($config['require']) ? $config['require'] : [];

        foreach ($requires as $dependency) {
            if (! is_string($dependency)) {
                continue;
            }
            $subDeps = $this->getModuleDependencies($dependency, $alreadyProcessed);
            $dependencies = [...$dependencies, ...$subDeps, $dependency];
        }

        return array_unique($dependencies);
    }

    /**
     * @return array<string, string> module name => installed version
     */
    private function getInstalledModuleVersions(): array
    {
        $rows = $this->configConnection->fetchAllAssociative(
            'SELECT `name`, `mod_release` FROM `modules_informations`'
        );

        $result = [];
        foreach ($rows as $row) {
            $name = is_string($row['name']) ? $row['name'] : '';
            $version = is_string($row['mod_release']) ? $row['mod_release'] : '';
            if ($name !== '') {
                $result[$name] = $version;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadModuleConfigurations(): array
    {
        $result = [];

        if (! is_dir($this->modulesDir)) {
            return $result;
        }

        $files = (new Finder())
            ->files()
            ->name('conf.php')
            ->depth('== 1')
            ->in($this->modulesDir);

        foreach ($files as $file) {
            $module_conf = [];
            include $file->getPathname();

            /** @var array<string, array<string, mixed>> $loadedConf — conf.php populates $module_conf */
            $loadedConf = $module_conf;

            foreach ($loadedConf as $name => $config) {
                $result[$name] = $config;
            }
        }

        return $result;
    }

    private function getModuleIdByName(string $moduleName): ?int
    {
        $result = $this->configConnection->fetchOne(
            'SELECT `id` FROM `modules_informations` WHERE `name` = :name',
            ['name' => $moduleName]
        );

        return is_numeric($result) ? (int) $result : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function updateModuleMetadata(int $moduleId, array $config): void
    {
        $this->configConnection->executeStatement(
            'UPDATE `modules_informations` SET '
            . '`name` = :name, '
            . '`rname` = :rname, '
            . '`is_removeable` = :is_removeable, '
            . '`infos` = :infos, '
            . '`author` = :author, '
            . '`svc_tools` = :svc_tools, '
            . '`host_tools` = :host_tools '
            . 'WHERE `id` = :id',
            [
                'name' => $this->asString($config['name'] ?? ''),
                'rname' => $this->asString($config['rname'] ?? ''),
                'is_removeable' => $this->asString($config['is_removeable'] ?? '0'),
                'infos' => $this->asString($config['infos'] ?? ''),
                'author' => $this->asString($config['author'] ?? ''),
                'svc_tools' => $this->asString($config['svc_tools'] ?? '0'),
                'host_tools' => $this->asString($config['host_tools'] ?? '0'),
                'id' => $moduleId,
            ]
        );
    }

    private function runModuleUpgradeScripts(string $moduleName, int $moduleId, string $installedVersion, string $targetVersion): void
    {
        $upgradesPath = $this->modulesDir . '/' . $moduleName . '/upgrade';
        if (! is_dir($upgradesPath)) {
            return;
        }

        $directories = (new Finder())
            ->directories()
            ->depth('== 0')
            ->in($upgradesPath);

        $versions = [];
        foreach ($directories as $dir) {
            $versionName = $dir->getBasename();
            if (preg_match('/^\d+\.\d+\.\d+/', $versionName)) {
                $versions[] = $versionName;
            }
        }

        usort($versions, static fn (string $versionA, string $versionB): int => version_compare($versionA, $versionB));

        foreach ($versions as $version) {
            if (version_compare($installedVersion, $version) >= 0) {
                continue;
            }
            if (version_compare($version, $targetVersion) > 0) {
                break;
            }

            $versionPath = $upgradesPath . '/' . $version;

            $this->logger->info('Running module upgrade scripts', [
                'module' => $moduleName,
                'version' => $version,
            ]);

            $this->executeModulePhpFile($versionPath . '/php/upgrade.pre.php');
            $this->executeModuleSqlFile($versionPath . '/sql/upgrade.sql');
            $this->executeModulePhpFile($versionPath . '/php/upgrade.php');

            $this->updateModuleVersion($moduleId, $version);
        }
    }

    private function executeModulePhpFile(string $filePath): void
    {
        if (! is_readable($filePath)) {
            return;
        }

        // Variables expected by legacy module upgrade scripts.
        $pearDB = $this->configConnection->getNativeConnection();
        $pearDBStorage = $this->realtimeConnection->getNativeConnection();
        $centreon_path = $this->centreonPath;

        require_once $filePath;
    }

    private function executeModuleSqlFile(string $filePath): void
    {
        if (! is_readable($filePath)) {
            return;
        }

        $fileStream = fopen($filePath, 'r');
        if (! is_resource($fileStream)) {
            return;
        }

        $query = '';

        try {
            while (! feof($fileStream)) {
                $currentLine = fgets($fileStream);
                if ($currentLine === false) {
                    continue;
                }

                $trimmed = trim($currentLine);
                if ($trimmed === '') {
                    continue;
                }
                if (str_starts_with($trimmed, '--')) {
                    continue;
                }
                if (str_starts_with($trimmed, '#')) {
                    continue;
                }

                $query .= ' ' . $trimmed;

                if (preg_match('/;\s*$/', $query)) {
                    try {
                        $this->configConnection->executeStatement($query);
                    } catch (\Throwable $exception) {
                        $this->logger->error('Module SQL execution failed', [
                            'file' => $filePath,
                            'query' => $query,
                            'error' => $exception->getMessage(),
                        ]);

                        throw $exception;
                    }
                    $query = '';
                }
            }

            $remainingQuery = trim($query);
            if ($remainingQuery !== '') {
                try {
                    $this->configConnection->executeStatement($remainingQuery);
                } catch (\Throwable $exception) {
                    $this->logger->error('Module SQL execution failed', [
                        'file' => $filePath,
                        'query' => $remainingQuery,
                        'error' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }
            }
        } finally {
            fclose($fileStream);
        }
    }

    private function updateModuleVersion(int $moduleId, string $version): void
    {
        $this->configConnection->executeStatement(
            'UPDATE `modules_informations` SET `mod_release` = :version WHERE `id` = :id',
            ['version' => $version, 'id' => $moduleId]
        );
    }

    private function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
