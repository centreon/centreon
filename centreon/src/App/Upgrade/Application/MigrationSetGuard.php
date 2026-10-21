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

namespace App\Upgrade\Application;

use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;

/**
 * Checked before migrating or rolling back a set. A module set has its own lock: the platform update holds the update
 * one while it upgrades the modules.
 */
final readonly class MigrationSetGuard
{
    public function __construct(
        private UpdateLocker $updateLocker,
        private MigrationSetLocker $migrationSetLocker,
        private ModuleRepository $moduleRepository,
    ) {
    }

    /**
     * @template T
     *
     * @param MigrationSet|null $set null for all the core sets
     * @param callable(): T $callback
     *
     * @throws ModuleNotInstalledException
     * @throws \RuntimeException when another update, or another migration of the same module set, is in progress
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
                throw new \RuntimeException(\sprintf('The %s is already being migrated', $set->label()));
            }

            try {
                return $callback();
            } finally {
                $this->migrationSetLocker->unlock($set);
            }
        }

        if (! $this->updateLocker->lock()) {
            throw new \RuntimeException('An update is already in progress');
        }

        try {
            return $callback();
        } finally {
            $this->updateLocker->unlock();
        }
    }
}
