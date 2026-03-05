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
use App\Upgrade\Application\ModuleUpdater;
use App\Upgrade\Domain\Repository\UpdateLocker;
use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use Psr\Log\LoggerInterface;

#[AsCommandHandler]
final readonly class UpdateCommandHandler
{
    public function __construct(
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
        private UpdateLocker $updateLocker,
        private DbmsVersionValidator $dbmsVersionValidator,
        private ModuleUpdater $moduleUpdater,
        private EngineContextWriter $engineContextWriter,
        private CacheClearer $cacheClearer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateCommand $command): void
    {
        $this->dbmsVersionValidator->validateOrFail();
        if (! $this->updateScriptFinder->isInstallsDirWritable()) {
            throw new \RuntimeException(
                'The installs backup directory does not exist or is not writable. '
                . 'Please create it with write permissions for the web server user.'
            );
        }
        if (! $this->updateLocker->lock()) {
            throw new \RuntimeException('An update is already in progress');
        }
        try {
            $currentVersion = $this->updateRepository->findCurrentVersion();

            if ($currentVersion === null) {
                throw new \RuntimeException('Cannot retrieve the current platform version');
            }

            $availableUpdates = $this->updateScriptFinder->findOrderedAvailableUpdates($currentVersion);

            if ($availableUpdates !== []) {
                $this->logger->info('Available updates found', ['updates' => $availableUpdates]);

                foreach ($availableUpdates as $version) {
                    $this->logger->info('Running update', ['version' => $version]);
                    $this->updateRepository->runUpdate($version);
                }
            } else {
                $this->logger->info('No available updates to perform');
            }

            // Must always run whether there are updates or not.
            $this->updateRepository->runPostUpdate($currentVersion);

            $this->moduleUpdater->updateModules();
            $this->moduleUpdater->updateWidgets();
        } finally {
            $this->updateLocker->unlock();
        }
        $this->engineContextWriter->writeIfMissing();
        $this->cacheClearer->clear();
    }
}
