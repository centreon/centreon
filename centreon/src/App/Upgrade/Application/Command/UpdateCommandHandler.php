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

namespace App\Upgrade\Application\Command;

use Adaptation\Log\LoggerUpgrade;
use App\Shared\Application\Command\AsCommandHandler;
use App\Upgrade\Application\CacheClearer;
use App\Upgrade\Application\DbmsVersionValidator;
use App\Upgrade\Application\EngineContextWriter;
use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Application\UpdateLocker;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use App\Upgrade\Domain\Repository\WidgetRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommandHandler]
final readonly class UpdateCommandHandler
{
    public function __construct(
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
        private UpdateLocker $updateLocker,
        private DbmsVersionValidator $dbmsVersionValidator,
        private ModuleRepository $moduleRepository,
        private WidgetRepository $widgetRepository,
        private EngineContextWriter $engineContextWriter,
        private CacheClearer $cacheClearer,
        private MigrationRunner $migrationRunner,
        private TargetVersionRecorder $targetVersionRecorder,
        #[Autowire(param: 'centreon.version')]
        private string $targetVersion,
    ) {
    }

    /**
     * @throws \RuntimeException when another update is already in progress or the current version cannot be read
     * @throws \Throwable any failure thrown by validation, the repositories or the cache clearer (re-thrown after the lock is released and the failure is logged)
     */
    public function __invoke(UpdateCommand $command): void
    {
        $startedAt = microtime(true);
        $currentVersion = null;
        $startEmitted = false;

        try {
            $this->dbmsVersionValidator->validateOrFail();

            if (! $this->updateLocker->lock()) {
                throw new \RuntimeException('An update is already in progress');
            }

            try {
                $currentVersion = $this->updateRepository->findCurrentVersion();
                if ($currentVersion === null || trim($currentVersion) === '') {
                    throw new \RuntimeException('Cannot retrieve the current platform version');
                }

                $availableUpdates = $this->updateScriptFinder->findOrderedAvailableUpdates($currentVersion);

                LoggerUpgrade::create()->start($currentVersion, $this->targetVersion);
                $startEmitted = true;

                // The handler owns step sequencing and logging; the repository only runs each operation.
                foreach ($availableUpdates as $version) {
                    $this->runStep($version, 'monitoring_sql', fn () => $this->updateRepository->runMonitoringSql($version));
                    $this->runStep($version, 'php_script', fn () => $this->updateRepository->runScript($version));
                    $this->runStep($version, 'configuration_sql', fn () => $this->updateRepository->runConfigurationSql($version));
                    $this->runStep($version, 'php_post_script', fn () => $this->updateRepository->runPostScript($version));
                    $this->runStep($version, 'update_version_information', fn () => $this->updateRepository->updateVersionInformation($version));
                }

                foreach (MigrationSet::coreSets() as $set) {
                    $this->runStep($this->targetVersion, 'migrations_' . $set->name, fn (): array => $this->migrationRunner->migrate($set));
                }

                $this->runStep($this->targetVersion, 'update_version_information', fn () => $this->targetVersionRecorder->record());

                if ($this->updateRepository->installDirectoryExists()) {
                    $this->runStep($currentVersion, 'backup_install_directory', fn () => $this->updateRepository->backupInstallDirectory($currentVersion));
                    $this->runStep($currentVersion, 'remove_install_directory', fn () => $this->updateRepository->removeInstallDirectory());
                }

                // Reported once the upgrade ended: the modules upgraded before it need the cache clear.
                $moduleFailure = null;
                try {
                    $this->runStep($currentVersion, 'modules_update', fn () => $this->moduleRepository->updateAll());
                } catch (\Throwable $exception) {
                    $moduleFailure = $exception;
                }
                $this->runStep($currentVersion, 'widgets_update', fn () => $this->widgetRepository->updateAll());
                $this->runStep($currentVersion, 'engine_context', fn () => $this->engineContextWriter->writeIfMissing());
                $this->runStep($currentVersion, 'cache_clear', fn () => $this->cacheClearer->clear());
                if ($moduleFailure instanceof \Throwable) {
                    throw $moduleFailure;
                }

                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                LoggerUpgrade::create()->success($currentVersion, $this->targetVersion, $durationMs);
            } finally {
                $this->updateLocker->unlock();
            }
        } catch (\Throwable $exception) {
            if ($startEmitted) {
                // The lifecycle is open: close it with a balanced upgrade.failure.
                LoggerUpgrade::create()->failure($currentVersion, $this->targetVersion, $exception->getMessage(), $exception);
            } else {
                // The failure happened before start() (validation / lock / version read), so there is no
                // start to balance: emit a standalone upgrade.error instead of a dangling failure, keeping
                // the attempt visible in the upgrade channel.
                LoggerUpgrade::create()->error($currentVersion ?? 'unknown', $exception->getMessage(), $exception);
            }

            throw $exception;
        }
    }

    private function runStep(string $version, string $step, callable $action): void
    {
        LoggerUpgrade::create()->step($version, $step, "Starting step '{$step}'");
        $startedAt = microtime(true);
        try {
            $action();
        } catch (\Throwable $exception) {
            LoggerUpgrade::create()->stepFailure($version, $step, $exception->getMessage(), $exception);

            throw $exception;
        }
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        LoggerUpgrade::create()->stepCompleted($version, $step, $durationMs, "Step '{$step}' completed in {$durationMs}ms");
    }
}
