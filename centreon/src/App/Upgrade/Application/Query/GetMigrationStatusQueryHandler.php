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
     * @return list<MigrationSetStatus>
     */
    public function __invoke(GetMigrationStatusQuery $query): array
    {
        $statuses = [];
        foreach ($query->set instanceof MigrationSet ? [$query->set] : $this->migrationRunner->sets() as $set) {
            // The migrations of a module not installed run when it is installed: their classes are not even loaded.
            if ($set->module === null || $this->moduleRepository->isInstalled($set->module)) {
                $statuses[] = $this->migrationRunner->status($set);
            }
        }

        return $statuses;
    }
}
