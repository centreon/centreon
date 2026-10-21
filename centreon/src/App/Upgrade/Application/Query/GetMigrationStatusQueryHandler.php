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

namespace App\Upgrade\Application\Query;

use App\Shared\Application\Query\AsQueryHandler;
use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Application\ModuleNotInstalledException;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;

#[AsQueryHandler]
final readonly class GetMigrationStatusQueryHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private ModuleRepository $moduleRepository,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException when the requested set is the one of a module not installed
     * @throws \Exception when the requested set breaks a check of migrate() or its database fails, reported per set otherwise
     *
     * @return list<MigrationSetStatus> every set when none is requested, but the modules not installed
     */
    public function __invoke(GetMigrationStatusQuery $query): array
    {
        if ($query->set instanceof MigrationSet) {
            if (! $this->isInstalled($query->set)) {
                throw new ModuleNotInstalledException((string) $query->set->module);
            }

            return [$this->migrationRunner->status($query->set)];
        }

        $statuses = [];
        // Left out without loading their migrations, which run once the module is installed.
        foreach (array_filter($this->migrationRunner->sets(), $this->isInstalled(...)) as $set) {
            try {
                $statuses[] = $this->migrationRunner->status($set);
            } catch (\Exception $exception) {
                // A check of migrate() or a database failure: the other sets are still worth showing.
                $statuses[] = new MigrationSetStatus($set, [], [], $exception->getMessage());
            }
        }

        return $statuses;
    }

    private function isInstalled(MigrationSet $set): bool
    {
        return $set->module === null || $this->moduleRepository->isInstalled($set->module);
    }
}
