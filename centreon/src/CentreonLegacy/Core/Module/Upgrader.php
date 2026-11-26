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

namespace CentreonLegacy\Core\Module;

use Adaptation\Log\LoggerUpgrade;
use CentreonLegacy\Core\Utils\ExecutedQueries;
use CentreonLegacy\Core\Utils\ExecutedQueriesChangedException;
use CentreonLegacy\ServiceProvider;
use CentreonLog;
use Core\Common\Domain\Exception\RepositoryException;
use Symfony\Component\Finder\Finder;

class Upgrader extends Module
{
    private bool $recordingProgress = false;

    /**
     * @return int
     */
    public function upgrade()
    {
        $this->assertMigrationsCanRun();
        $this->upgradeModuleConfiguration();

        $moduleInstalledInformation = $this->informationObj->getInstalledInformation($this->moduleName);

        // Process all directories within the /upgrade/ path.
        // Entry name should be a version.
        $upgradesPath = $this->getModulePath($this->moduleName) . '/upgrade/';
        $orderedUpgrades = [];
        // Absent from a module shipping doctrine migrations only.
        if ($this->services->get('filesystem')->exists($upgradesPath)) {
            /** @var Finder $upgrades */
            $upgrades = $this->services->get('finder');
            $upgrades
                ->directories()
                ->depth('== 0')
                ->in($upgradesPath);
            foreach ($upgrades as $upgrade) {
                $orderedUpgrades[] = $upgrade->getBasename();
            }
        }
        usort($orderedUpgrades, 'version_compare');
        foreach ($orderedUpgrades as $upgradeName) {
            $upgradePath = $upgradesPath . $upgradeName;
            if (! preg_match('/^(\d+\.\d+\.\d+)/', $upgradeName, $matches)) {
                continue;
            }

            if (version_compare($moduleInstalledInformation['mod_release'], $upgradeName) >= 0) {
                continue;
            }

            // Beyond the code version (a pre-release, a forgotten conf.php bump): it would run again once released.
            if (version_compare($upgradeName, $this->moduleConfiguration['mod_release']) > 0) {
                LoggerUpgrade::create()->warning(
                    $upgradeName,
                    sprintf(
                        'The upgrade of the "%s" module stops before "%s": beyond the version %s of its conf.php',
                        $this->moduleName,
                        $upgradeName,
                        $this->moduleConfiguration['mod_release']
                    )
                );

                break;
            }

            CentreonLog::create()->info(
                CentreonLog::TYPE_UPGRADE,
                sprintf('Starting upgrade process for %s module', $moduleInstalledInformation['name']),
                [
                    'from' => $moduleInstalledInformation['mod_release'],
                    'to' => $upgradeName,
                ]
            );

            $this->upgradeVersionScripts($upgradePath, $upgradeName);

            // Recorded once its scripts passed: a failed version is resumed, not skipped.
            $this->upgradeVersion($upgradeName);
            $moduleInstalledInformation['mod_release'] = $upgradeName;
            $this->clearUpgradeProgress($upgradeName);
        }

        // After every upgrade/<v>: migrate refuses a module whose upgrade scripts are pending.
        $this->migrate();

        // Never downgrade: a package rollback would replay the upgrades in between.
        if (version_compare($this->moduleConfiguration['mod_release'], $moduleInstalledInformation['mod_release']) > 0) {
            $this->upgradeVersion($this->moduleConfiguration['mod_release']);
        }

        // Clearing symfony cache to properly enable API routes.
        $this->services->get(ServiceProvider::CENTREON_LEGACY_CACHE_CLEARER)->clear();

        return $this->moduleId;
    }

    /**
     * Upgrade module information except version.
     *
     * @throws \Exception
     *
     * @return mixed
     */
    private function upgradeModuleConfiguration()
    {
        $configurationFile = $this->getModulePath($this->moduleName) . '/conf.php';
        if (! $this->services->get('filesystem')->exists($configurationFile)) {
            throw new \Exception('Module configuration file not found.');
        }

        $query = 'UPDATE modules_informations SET '
            . '`name` = :name , '
            . '`rname` = :rname , '
            . '`is_removeable` = :is_removeable , '
            . '`infos` = :infos , '
            . '`author` = :author , '
            . '`svc_tools` = :svc_tools , '
            . '`host_tools` = :host_tools '
            . 'WHERE id = :id';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindValue(':name', $this->moduleConfiguration['name'], \PDO::PARAM_STR);
        $sth->bindValue(':rname', $this->moduleConfiguration['rname'], \PDO::PARAM_STR);
        $sth->bindValue(':is_removeable', $this->moduleConfiguration['is_removeable'], \PDO::PARAM_STR);
        $sth->bindValue(':infos', $this->moduleConfiguration['infos'], \PDO::PARAM_STR);
        $sth->bindValue(':author', $this->moduleConfiguration['author'], \PDO::PARAM_STR);
        $sth->bindValue(':svc_tools', $this->moduleConfiguration['svc_tools'] ?? '0', \PDO::PARAM_STR);
        $sth->bindValue(':host_tools', $this->moduleConfiguration['host_tools'] ?? '0', \PDO::PARAM_STR);
        $sth->bindValue(':id', $this->moduleId, \PDO::PARAM_INT);

        $sth->execute();

        return $this->moduleId;
    }

    /**
     * @param string $version
     *
     * @return int
     */
    private function upgradeVersion($version)
    {
        $query = 'UPDATE modules_informations SET '
            . '`mod_release` = :mod_release '
            . 'WHERE id = :id';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindValue(':mod_release', $version, \PDO::PARAM_STR);
        $sth->bindValue(':id', $this->moduleId, \PDO::PARAM_INT);

        $sth->execute();

        return $this->moduleId;
    }

    /**
     * The progress of an older version left behind is ignored.
     */
    private function clearUpgradeProgress(string $version): void
    {
        try {
            $this->services->get(ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS)->clear($this->moduleName);
        } catch (RepositoryException $exception) {
            LoggerUpgrade::create()->warning(
                $version,
                sprintf('Cannot clear the upgrade progress of the "%s" module', $this->moduleName),
                $exception
            );
        }
    }

    /**
     * Resumes a failed attempt: upgrade.php, run last, is the only step replayed whole.
     */
    private function upgradeVersionScripts(string $path, string $version): void
    {
        /** @var ModuleUpgradeProgressRepository $progress */
        $progress = $this->services->get(ServiceProvider::CENTREON_LEGACY_MODULE_UPGRADE_PROGRESS);
        $state = $progress->get($this->moduleName, $version);
        if ($state['pre'] || $state['sql'] > 0) {
            LoggerUpgrade::create()->info(
                $version,
                sprintf('Resuming the upgrade of the "%s" module after %d queries of upgrade.sql', $this->moduleName, $state['sql'])
            );
        }

        $this->recordingProgress = false;
        $record = function () use ($progress, $version, &$state): void {
            $this->recordingProgress = true;
            $progress->save($this->moduleName, $version, $state);
            $this->recordingProgress = false;
        };

        $step = $path . '/php/upgrade.pre.php';
        try {
            if (! $state['pre']) {
                $this->upgradePhpFiles($path, true);
                $state['pre'] = true;
                $record();
            }

            $step = $path . '/sql/upgrade.sql';
            // After each query: a timeout or a killed process must not replay it.
            $this->upgradeSqlFiles(
                $path,
                new ExecutedQueries($state['sql'], $state['checksum']),
                function (ExecutedQueries $executed) use ($record, &$state): void {
                    $state['sql'] = $executed->count;
                    $state['checksum'] = $executed->checksum;
                    $record();
                }
            );

            $step = $path . '/php/upgrade.php';
            $this->upgradePhpFiles($path, false);
        } catch (ExecutedQueriesChangedException $exception) {
            $refusal = new \RuntimeException(sprintf(
                '%s: restore the file, or delete the "%s" option to replay it whole',
                $exception->getMessage(),
                ModuleUpgradeProgressRepository::KEY_PREFIX . $this->moduleName
            ), 0, $exception);
            LoggerUpgrade::create()->error(
                $version,
                sprintf(
                    'Restore the upgrade.sql of the "%s" module, or delete the "%s" option to replay it whole',
                    $this->moduleName,
                    ModuleUpgradeProgressRepository::KEY_PREFIX . $this->moduleName
                ),
                $exception
            );

            throw $refusal;
        } catch (\Throwable $exception) {
            $isSql = str_ends_with($step, '.sql');
            if ($this->recordingProgress) {
                LoggerUpgrade::create()->error(
                    $version,
                    sprintf(
                        'The upgrade of the "%s" module could not record its progress in %s%s: it will be replayed',
                        $this->moduleName,
                        $step,
                        $isSql ? sprintf(' after query %d', $state['sql']) : ''
                    ),
                    $exception
                );

                throw $exception;
            }

            $failure = sprintf(
                'The upgrade of the "%s" module failed in %s%s',
                $this->moduleName,
                $step,
                $isSql ? sprintf(' at query %d', $state['sql'] + 1) : ''
            );
            LoggerUpgrade::create()->error($version, $failure, $exception);

            throw new \RuntimeException($failure . ': ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param string $path
     * @param callable(ExecutedQueries): void|null $onQueryExecuted
     *
     * @return bool
     */
    private function upgradeSqlFiles($path, ?ExecutedQueries $alreadyExecuted = null, ?callable $onQueryExecuted = null)
    {
        $installed = false;

        $sqlFile = $path . '/sql/upgrade.sql';
        if ($this->services->get('filesystem')->exists($sqlFile)) {
            $this->utils->executeSqlFile($sqlFile, [], false, $alreadyExecuted, $onQueryExecuted);
            $installed = true;
        }

        return $installed;
    }

    /**
     * @param string $path
     * @param bool $pre
     *
     * @return bool
     */
    private function upgradePhpFiles($path, $pre = false)
    {
        $installed = false;

        $phpFile = $path . '/php/upgrade';
        $phpFile = $pre ? $phpFile . '.pre.php' : $phpFile . '.php';

        if ($this->services->get('filesystem')->exists($phpFile)) {
            $this->utils->executePhpFile($phpFile);
            $installed = true;
        }

        return $installed;
    }
}
