<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Platform\Application\UseCase\UpdateVersions;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Platform\Application\Repository\ReadUpdateRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;
use Core\Platform\Application\Validator\RequirementValidatorsInterface;
use Pimple\Container;

final class UpdateVersions
{
    use LoggerTrait;

    /** @var CentreonModuleService */
    private CentreonModuleService $moduleService;

    /**
     * @param RequirementValidatorsInterface $requirementValidators
     * @param UpdateLockerRepositoryInterface $updateLocker
     * @param ReadVersionRepositoryInterface $readVersionRepository
     * @param ReadUpdateRepositoryInterface $readUpdateRepository
     * @param WriteUpdateRepositoryInterface $writeUpdateRepository
     * @param Container $dependencyInjector
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly RequirementValidatorsInterface $requirementValidators,
        private readonly UpdateLockerRepositoryInterface $updateLocker,
        private readonly ReadVersionRepositoryInterface $readVersionRepository,
        private readonly ReadUpdateRepositoryInterface $readUpdateRepository,
        private readonly WriteUpdateRepositoryInterface $writeUpdateRepository,
        Container $dependencyInjector,
        private readonly ContactInterface $user
    ) {
        /** @var CentreonModuleService $service */
        $service = $dependencyInjector[ServiceProvider::CENTREON_MODULE];
        $this->moduleService = $service;
    }

    /**
     * @param UpdateVersionsPresenterInterface $presenter
     */
    public function __invoke(UpdateVersionsPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->isAdmin()) {
                $presenter->setResponseStatus(new ForbiddenResponse('Only admin user can perform upgrades'));

                return;
            }
            $this->validateRequirementsOrFail();
            $this->lockUpdate();
            $this->updateCentreonWeb();
            $this->updateInstalledModules();
            $this->updateInstalledWidgets();
            $this->unlockUpdate();
        } catch (\Throwable $exception) {
            $this->error(
                $exception->getMessage(),
                ['trace' => $exception->getTraceAsString()],
            );

            $presenter->setResponseStatus(new ErrorResponse($exception->getMessage()));

            return;
        }
        $presenter->setResponseStatus(new NoContentResponse());
    }

    /**
     * @throws \Exception
     * @throws UpdateVersionsException
     * @throws \Throwable
     */
    private function updateCentreonWeb(): void
    {
        $this->info('Starting centreon-web update process');
        $availableUpdates = $this->getAvailableUpdates($this->getCurrentVersion());

        if ([] !== $availableUpdates) {
            $this->info('Available updates found for centreon-web', ['updates' => $availableUpdates]);
            $this->runUpdates($availableUpdates);
        } else {
            $this->info('No available updates to perform for centreon-web');
        }
        // Must always be run whether there is an update to execute or not.
        $this->runPostUpdate($this->getCurrentVersion());
    }

    /**
     * Validate platform requirements or fail.
     *
     * @throws \Exception
     */
    private function validateRequirementsOrFail(): void
    {
        $this->info('Validating platform requirements');
        $this->requirementValidators->validateRequirementsOrFail();
    }

    /**
     * Lock update process.
     */
    private function lockUpdate(): void
    {
        $this->info('Locking centreon update process...');
        if (! $this->updateLocker->lock()) {
            throw UpdateVersionsException::updateAlreadyInProgress();
        }
    }

    /**
     * Unlock update process.
     */
    private function unlockUpdate(): void
    {
        $this->info('Unlocking centreon update process...');

        $this->updateLocker->unlock();
    }

    /**
     * Get current version or fail.
     *
     * @throws \Exception
     *
     * @return string
     */
    private function getCurrentVersion(): string
    {
        $this->debug('Finding centreon-web current version');
        try {
            $currentVersion = $this->readVersionRepository->findCurrentVersion();
        } catch (\Exception $exception) {
            throw UpdateVersionsException::errorWhenRetrievingCurrentVersion($exception);
        }

        if ($currentVersion === null) {
            throw UpdateVersionsException::cannotRetrieveCurrentVersion();
        }

        return $currentVersion;
    }

    /**
     * Get available updates.
     *
     * @param string $currentVersion
     *
     * @return string[]
     */
    private function getAvailableUpdates(string $currentVersion): array
    {
        try {
            $this->info(
                'Getting available updates',
                [
                    'current_version' => $currentVersion,
                ],
            );

            return $this->readUpdateRepository->findOrderedAvailableUpdates($currentVersion);
        } catch (\Throwable $exception) {
            throw UpdateVersionsException::errorWhenRetrievingAvailableUpdates($exception);
        }
    }

    /**
     * Run given version updates.
     *
     * @param string[] $versions
     *
     * @throws \Throwable
     */
    private function runUpdates(array $versions): void
    {
        foreach ($versions as $version) {
            try {
                $this->info("Running update {$version}");
                $this->writeUpdateRepository->runUpdate($version);
            } catch (\Throwable $exception) {
                throw UpdateVersionsException::errorWhenApplyingUpdateToVersion($version, $exception->getMessage(), $exception);
            }
        }
    }

    /**
     * @throws UpdateVersionsException
     */
    private function updateInstalledWidgets(): void
    {
        $this->info('Updating installed widgets');
        try {
            $widgets = $this->moduleService->getList(
                search: null,
                installed: true,
                updated: false,
                typeList: ['widget']
            );

            foreach ($widgets['widget'] as $widget) {
                $this->debug('Updating widget', ['name' => $widget->getName()]);
                $this->moduleService->update($widget->getId(), 'widget');
            }
        } catch (\Throwable $exception) {
            throw UpdateVersionsException::errorWhenApplyingUpdate($exception->getMessage(), $exception);
        }
    }

    /**
     * @throws UpdateVersionsException
     */
    private function updateInstalledModules(): void
    {
        $this->info('Updating installed modules');
        try {
            $modules = $this->moduleService->getList(
                search: null,
                installed: true,
                updated: null,
                typeList: ['module']
            );

            foreach ($modules['module'] as $module) {
                $this->debug('Updating module', ['name' => $module->getId()]);
                $this->moduleService->update($module->getId(), 'module');
            }
        } catch (\Throwable $exception) {
            throw UpdateVersionsException::errorWhenApplyingUpdate($exception->getMessage(), $exception);
        }
    }

    /**
     * Run post update actions.
     *
     * @param string $currentVersion
     *
     * @throws UpdateVersionsException
     */
    private function runPostUpdate(string $currentVersion): void
    {
        $this->info('Running post update actions for centreon-web');
        try {
            $this->writeUpdateRepository->runPostUpdate($currentVersion);
        } catch (\Throwable $exception) {
            throw UpdateVersionsException::errorWhenApplyingPostUpdate($exception);
        }
    }
}
