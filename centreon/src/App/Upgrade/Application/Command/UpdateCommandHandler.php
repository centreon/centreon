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

use App\Shared\Application\Command\AsCommandHandler;
use App\Upgrade\Application\CacheClearer;
use App\Upgrade\Application\DbmsVersionValidator;
use App\Upgrade\Application\EngineContextWriter;
use App\Upgrade\Application\UpdateLocker;
use App\Upgrade\Domain\Event\UpgradeCompleted;
use App\Upgrade\Domain\Event\UpgradeFailed;
use App\Upgrade\Domain\Event\UpgradeStarted;
use App\Upgrade\Domain\Event\UpgradeStepCompleted;
use App\Upgrade\Domain\Event\UpgradeStepFailed;
use App\Upgrade\Domain\Event\UpgradeStepStarted;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use App\Upgrade\Domain\Repository\WidgetRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

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
        private EventDispatcherInterface $events,
    ) {
    }

    /**
     * @throws \RuntimeException when another update is already in progress or the current version cannot be read
     * @throws \Throwable any failure thrown by validation, the repositories or the cache clearer (re-thrown after the lock is released and the failure event is dispatched)
     */
    public function __invoke(UpdateCommand $command): void
    {
        $startedAt = microtime(true);
        $currentVersion = null;
        $targetVersion = null;

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
                $targetVersion = $availableUpdates === [] ? $currentVersion : end($availableUpdates);

                $this->events->dispatch(new UpgradeStarted($currentVersion, $targetVersion));

                foreach ($availableUpdates as $version) {
                    $this->runStep($version, 'run_update', fn () => $this->updateRepository->runUpdate($version));
                }

                $this->runStep($currentVersion, 'post_update', fn () => $this->updateRepository->runPostUpdate($currentVersion));
                $this->runStep($currentVersion, 'modules_update', fn () => $this->moduleRepository->updateAll());
                $this->runStep($currentVersion, 'widgets_update', fn () => $this->widgetRepository->updateAll());
                $this->runStep($currentVersion, 'engine_context', fn () => $this->engineContextWriter->writeIfMissing());
                $this->runStep($currentVersion, 'cache_clear', fn () => $this->cacheClearer->clear());

                $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
                $this->events->dispatch(new UpgradeCompleted($currentVersion, $targetVersion, $durationMs));
            } finally {
                $this->updateLocker->unlock();
            }
        } catch (\Throwable $exception) {
            $this->events->dispatch(new UpgradeFailed($exception->getMessage(), $currentVersion, $targetVersion, $exception));

            throw $exception;
        }
    }

    /**
     * @throws \Throwable
     */
    private function runStep(string $version, string $step, callable $action): void
    {
        $this->events->dispatch(new UpgradeStepStarted($version, $step));
        $startedAt = microtime(true);
        try {
            $action();
        } catch (\Throwable $exception) {
            $this->events->dispatch(new UpgradeStepFailed($exception->getMessage(), $version, $step, $exception));

            throw $exception;
        }
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        $this->events->dispatch(new UpgradeStepCompleted($version, $step, $durationMs));
    }
}
