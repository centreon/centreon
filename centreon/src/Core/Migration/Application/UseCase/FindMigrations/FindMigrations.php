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

namespace Core\Migration\Application\UseCase\FindMigrations;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Migration\Application\Exception\MigrationException;
use Core\Migration\Application\Repository\ReadAvailableMigrationRepositoryInterface;
use Core\Migration\Application\Repository\ReadExecutedMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;

final class FindMigrations
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAvailableMigrationRepositoryInterface $readAvailableMigrationRepository,
        private readonly ReadExecutedMigrationRepositoryInterface $readExecutedMigrationRepository,
    ) {
    }

    public function __invoke(FindMigrationsPresenterInterface $presenter): void
    {
        try {
            $migrations = $this->findMigrations();

            if (empty($migrations)) {
                $presenter->presentResponse(new FindMigrationsResponse());

                return;
            }

            $presenter->presentResponse(
                $this->createResponse($migrations)
            );
        } catch (\Throwable $ex) {
            $errorMessage = MigrationException::errorWhileRetrievingMigrations()->getMessage();
            $this->error($errorMessage, ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(_($errorMessage))
            );
        }
    }

    /**
     * @return NewMigration[]
     */
    private function findMigrations(): array
    {
        $this->info('Search for available migrations');
        $availableMigrations = $this->readAvailableMigrationRepository->findAll();

        $this->info('Search for executed migrations');
        $executedMigrations = $this->readExecutedMigrationRepository->findAll();

        return array_filter(
            $availableMigrations,
            function ($availableMigration) use (&$executedMigrations) {
                $availableMigrationName = $availableMigration->getName();
                $availableMigrationModuleName = $availableMigration->getModuleName();

                foreach ($executedMigrations as $executedMigrationKey => $executedMigration) {
                    if (
                        $availableMigrationName === $executedMigration->getName()
                        && $availableMigrationModuleName === $executedMigration->getModuleName()
                    ) {
                        unset($executedMigrations[$executedMigrationKey]);

                        return false;
                    }
                }

                return true;
            }
        );
    }

    /**
     * Create Response Object.
     *
     * @param NewMigration[] $migrations
     *
     * @return FindMigrationsResponse
     */
    private function createResponse(
        array $migrations
    ): FindMigrationsResponse {
        $response = new FindMigrationsResponse();

        $migrationDtos = [];
        foreach ($migrations as $migration) {
            $migrationDto = new MigrationDto();
            $migrationDto->name = $migration->getName();
            $migrationDto->moduleName = $migration->getModuleName();
            $migrationDto->description = $migration->getDescription();

            $migrationDtos[] = $migrationDto;
        }

        $response->migrations = $migrationDtos;

        return $response;
    }
}
