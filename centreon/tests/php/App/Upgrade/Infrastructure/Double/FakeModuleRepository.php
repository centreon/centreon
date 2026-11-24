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

namespace Tests\App\Upgrade\Infrastructure\Double;

use App\Upgrade\Domain\Repository\ModuleRepository;

final class FakeModuleRepository implements ModuleRepository
{
    public int $updates = 0;

    public ?\Throwable $failure = null;

    public ?\Throwable $updateFailure = null;

    public ?\Throwable $pendingUpgradeFailure = null;

    public ?\Throwable $countFailure = null;

    /**
     * @param list<string> $installedModules
     * @param list<string> $pendingUpgrades the modules whose upgrade scripts are pending
     * @param array<string, int> $executedMigrations the executed migration count of each module
     */
    public function __construct(
        public array $installedModules = [],
        public array $pendingUpgrades = [],
        public array $executedMigrations = [],
    ) {
    }

    public function updateAll(): void
    {
        ++$this->updates;
        if ($this->updateFailure instanceof \Throwable) {
            throw $this->updateFailure;
        }
    }

    public function isInstalled(string $module): bool
    {
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        return in_array($module, $this->installedModules, true);
    }

    public function hasPendingLegacyUpgrade(string $module): bool
    {
        if ($this->pendingUpgradeFailure instanceof \Throwable) {
            throw $this->pendingUpgradeFailure;
        }

        return in_array($module, $this->pendingUpgrades, true);
    }

    public function findInstalledModules(): array
    {
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        return $this->installedModules;
    }

    public function countExecutedMigrations(string $module): int
    {
        if ($this->countFailure instanceof \Throwable) {
            throw $this->countFailure;
        }

        return $this->executedMigrations[$module] ?? 0;
    }
}
