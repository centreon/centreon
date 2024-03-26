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
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Migration\Application\Repository\WriteMigrationRepositoryInterface;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;
use Core\Platform\Application\Repository\UpdateLockerRepositoryInterface;
use Core\Migration\Application\UseCase\ExecuteMigrations\Validator\MigrationsValidator;
use Core\Platform\Application\UseCase\UpdateVersions\UpdateVersionsException;

final class ExecuteMigrations
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private ReadVersionRepositoryInterface $readVersionRepository,
        private readonly UpdateLockerRepositoryInterface $updateLocker,
        private readonly WriteMigrationRepositoryInterface $writeMigrationRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly UpdateLockerRepositoryInterface $updateLockerRepository
    ) {
    }

    public function __invoke(
        ExecuteMigrationsRequest $request,
        ExecuteMigrationsPresenterInterface $presenter
    ): void
    {
        try {
            /*
            $validator = new MigrationsValidator();
            $validator->validateMigration(
                $request->names,
                $this->migrationsCollectorRepository,
            );
            */

            $this->lockUpdate();
            foreach ($request->names as $name) {
                $this->writeMigrationRepository->executeMigration($name);
            }

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            dump($ex->getMessage());
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
}
