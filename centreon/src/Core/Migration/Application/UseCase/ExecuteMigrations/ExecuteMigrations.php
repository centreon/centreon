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

namespace Core\Migration\Application\UseCase\ExecuteMigrations;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Migration\Application\Repository\ReadMigrationRepositoryInterface;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Platform\Application\Repository\WriteUpdateRepositoryInterface;
use Core\Platform\Application\UseCase\UpdateVersions\UpdateVersionsException;

final class ExecuteMigrations
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadMigrationRepositoryInterface $readMigrationRepository,
        private readonly ReadVersionRepositoryInterface $readVersionRepository,
        private readonly WriteUpdateRepositoryInterface $writeUpdateRepository,
        private readonly UpdateLockerRepositoryInterface $updateLocker,
        private readonly WriteMigrationRepositoryInterface $writeMigrationRepository,
        private readonly UpdateLockerRepositoryInterface $updateLockerRepository
    ) {
    }

    public function __invoke(
        ExecuteMigrationsPresenterInterface $presenter
    ): void {
        try {
            $migrations = $this->readMigrationRepository->findNewMigrations();

            $this->lockUpdate();

            foreach ($migrations as $migration) {
                $this->writeMigrationRepository->executeMigration($migration);
            }

            $this->updateVersions();

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $errorMessage = 'An error occurred while executing migration';
            $this->error($errorMessage, ['trace' => (string) $ex]);
            $presenter->setResponseStatus(
                new ErrorResponse(_($errorMessage))
            );
        }

        $this->unlockUpdate();
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
     * @throws \Exception
     * @throws UpdateVersionsException
     * @throws \Throwable
     */
    private function updateVersions(): void
    {
        $installedVersion = $this->getInstalledVersion();
        $availableVersion = $this->getAvailableVersion();

        if ($installedVersion !== $availableVersion) {
            $this->info(sprintf('Updating centreon-web from %s to %s', $installedVersion, $availableVersion));
            $this->writeUpdateRepository->updateVersionInformation($availableVersion);
        }
    }

    /**
     * Get installed version or fail.
     *
     * @throws \Exception
     *
     * @return string
     */
    private function getInstalledVersion(): string
    {
        $this->debug('Finding centreon-web installed version');

        try {
            $installedVersion = $this->readVersionRepository->findCurrentVersion();
        } catch (\Exception $exception) {
            throw UpdateVersionsException::errorWhenRetrievingCurrentVersion($exception);
        }

        if ($installedVersion === null) {
            throw UpdateVersionsException::cannotRetrieveCurrentVersion();
        }

        return $installedVersion;
    }

    /**
     * Get available version or fail.
     *
     * @throws \Exception
     *
     * @return string
     */
    private function getAvailableVersion(): string
    {
        $this->debug('Finding centreon-web available version');

        // @todo get version from environment file

        return '24.04.0';
    }
}
