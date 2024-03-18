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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Migration\Application\Repository\ReadAvailableMigrationRepositoryInterface;
use Core\Migration\Application\Repository\ReadExecutedMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;
use Core\Platform\Application\Repository\ReadVersionRepositoryInterface;

final class FindMigrations
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private ReadVersionRepositoryInterface $readVersionRepository,
        private readonly ReadAvailableMigrationRepositoryInterface $readAvailableMigrationRepository,
        private readonly ReadExecutedMigrationRepositoryInterface $readExecutedMigrationRepository,
        private readonly RequestParametersInterface $requestParameters,
    ) {
    }

    public function __invoke(FindMigrationsPresenterInterface $presenter): void
    {
        try {
            $this->info('Search for available migrations');
            $availableMigrations = $this->readAvailableMigrationRepository->findAll();

            $this->info('Search for executed migrations');
            $executedMigrations = $this->readExecutedMigrationRepository->findAll($this->requestParameters);

            $migrations = array_filter(
                $availableMigrations,
                function($availableMigration) use (&$executedMigrations) {
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

            if (empty($migrations)) {
                $presenter->presentResponse(new FindMigrationsResponse());

                return;
            }

            $presenter->presentResponse(
                $this->createResponse($migrations)
            );
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error('An error occurred while retrieving the migrations listing', ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(_('An error occurred while retrieving the migrations listing'))
            );
        }
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
            // @todo manage properly exception
            throw new \Exception('Cannot retrieve centreon web version');
        }

        if ($currentVersion === null) {
            // @todo manage properly exception
            throw new \Exception('Cannot retrieve centreon web version');
        }

        return $currentVersion;
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
