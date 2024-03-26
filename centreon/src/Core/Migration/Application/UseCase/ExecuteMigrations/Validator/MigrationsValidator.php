<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Migration\Application\UseCase\ExecuteMigrations\Validator;

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\MigrationsCollectorRepositoryInterface;

class MigrationsValidator
{
    use LoggerTrait;

    /**
     * Validate that provided user and contactgroup ids exists.
     *
     * @param string[] $names
     * @param MigrationsCollectorRepositoryInterface $migrationsCollectorRepository
     *
     * @throws \Throwable
     */
    public function validateMigration(
        array $names,
        MigrationsCollectorRepositoryInterface $migrationsCollectorRepository,
    ): void {
        $migrations = $migrationsCollectorRepository->findAll();

        foreach ($migrations as $index => $migration) {
            if ($index === 0 && $migration->getName() === $name) {
                return;
            }

            if ($migration->getName() === $name) {
                throw new \Exception(sprintf('%d migrations need to be execute before %s', $index + 1, $name));
            }
        }

        throw new \Exception(sprintf('Migration %s not found', $name));
    }
}
