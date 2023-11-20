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

use Centreon\Domain\Log\LoggerTrait;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Platform\Application\Repository\ReadUpdateRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\Repository\UpdateNotFoundException;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;
use Core\Platform\Application\Validator\RequirementValidatorsInterface;
use Pimple\Container;

final class UpdateVersions
{
    use LoggerTrait;

    /** CentreonModuleService $moduleService */
    private CentreonModuleService $moduleService;

    /**
     * @param RequirementValidatorsInterface $requirementValidators
     * @param UpdateLockerRepositoryInterface $updateLocker
     * @param ReadVersionRepositoryInterface $readVersionRepository
     * @param ReadUpdateRepositoryInterface $readUpdateRepository
     * @param WriteUpdateRepositoryInterface $writeUpdateRepository
     * @param Container $dependencyInjector
     */
    public function __construct(
        private RequirementValidatorsInterface $requirementValidators,
        private UpdateLockerRepositoryInterface $updateLocker,
        private ReadVersionRepositoryInterface $readVersionRepository,
        private ReadUpdateRepositoryInterface $readUpdateRepository,
        private WriteUpdateRepositoryInterface $writeUpdateRepository,
        Container $dependencyInjector,
    ) {
        $this->moduleService = $dependencyInjector[ServiceProvider::CENTREON_MODULE];
    }

    /**
     * @param UpdateVersionsPresenterInterface $presenter
     */
    public function __invoke(
        UpdateVersionsPresenterInterface $presenter,
    ): void {
        $this->info('Updating versions');

        try {
            $this->validateRequirementsOrFail();

            $this->lockUpdate();

            $currentVersion = $this->getCurrentVersionOrFail();

            $availableUpdates = $this->getAvailableUpdatesOrFail($currentVersion);

            $this->runUpdates($availableUpdates);

            $this->unlockUpdate();

            $this->runPostUpdate($this->getCurrentVersionOrFail());
        } catch (UpdateNotFoundException $exception) {
            $this->error(
                $exception->getMessage(),
                ['trace' => $exception->getTraceAsString()],
            );

            $presenter->setResponseStatus(new NotFoundResponse('Updates'));

            return;
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
    private function getCurrentVersionOrFail(): string
    {
        $this->info('Getting current version');

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
    private function getAvailableUpdatesOrFail(string $currentVersion): array
    {
        try {
            $this->info(
                'Getting available updates',
                [
                    'current_version' => $currentVersion,
                ],
            );

            return $this->readUpdateRepository->findOrderedAvailableUpdates($currentVersion);
        } catch (UpdateNotFoundException $exception) {
            throw $exception;
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
                throw UpdateVersionsException::errorWhenApplyingUpdate($version, $exception->getMessage(), $exception);
            }
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
        $this->info('Running post update actions');

        try {
            $widgets = $this->moduleService->getList(null, true, null, ['widget']);
            foreach ($widgets['widget'] as $widget) {
                if ($widget->isInternal()) {
                    $this->moduleService->update($widget->getId(), 'widget');
                }
            }

            $this->writeUpdateRepository->runPostUpdate($currentVersion);
        } catch (\Throwable $exception) {
            throw UpdateVersionsException::errorWhenApplyingPostUpdate($exception);
        }
    }
}
