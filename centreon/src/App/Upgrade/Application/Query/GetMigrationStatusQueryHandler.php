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
        // The migrations of a module not installed run when it is installed.
        return array_values(array_filter(
            $this->migrationRunner->status($query->set),
            fn (MigrationSetStatus $status): bool => $status->set->module === null || $this->moduleRepository->isInstalled($status->set->module),
        ));
    }
}
