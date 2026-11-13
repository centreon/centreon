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

namespace App\Upgrade\Application\Service;

use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Exception\MigrationSetBusyException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Service\MigrationSetLockerInterface;
use App\Upgrade\Domain\Service\UpdateLockerInterface;

/**
 * Runs the migration or rollback of a set under its lock: its own for an installed module, the update one for the Centreon Web sets.
 */
final readonly class MigrationSetGuard
{
    public function __construct(
        private UpdateLockerInterface $updateLocker,
        private MigrationSetLockerInterface $migrationSetLocker,
        private ModuleRepository $moduleRepository,
    ) {
    }

    /**
     * @template T
     *
     * @param MigrationSet|null $set null for all the Centreon Web sets
     * @param callable(): T $callback
     *
     * @throws ModuleNotInstalledException
     * @throws MigrationSetBusyException
     * @throws LockNotReleasedException when the callback succeeded but the lock could not be released
     *
     * @return T
     */
    public function run(?MigrationSet $set, callable $callback): mixed
    {
        if ($set?->module !== null) {
            if (! $this->moduleRepository->isInstalled($set->module)) {
                throw new ModuleNotInstalledException($set->module);
            }

            if (! $this->migrationSetLocker->lock($set)) {
                throw MigrationSetBusyException::setInProgress($set);
            }

            return $this->runLocked($callback, fn () => $this->migrationSetLocker->unlock($set));
        }

        if (! $this->updateLocker->lock()) {
            throw MigrationSetBusyException::updateInProgress();
        }

        return $this->runLocked($callback, $this->updateLocker->unlock(...));
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     * @param callable(): void $unlock
     *
     * @return T
     */
    private function runLocked(callable $callback, callable $unlock): mixed
    {
        try {
            $result = $callback();
        } catch (\Throwable $exception) {
            try {
                $unlock();
            } catch (\Throwable) {
                // The lock goes with the process anyway: the failure to report is the callback's.
            }

            throw $exception;
        }

        try {
            $unlock();
        } catch (\Throwable $exception) {
            throw new LockNotReleasedException($exception, $result);
        }

        return $result;
    }
}
