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
use App\Upgrade\Application\ModuleNotInstalledException;
use App\Upgrade\Domain\Repository\ModuleRepository;

/**
 * Takes no lock: RollbackMigrationCommand refuses to run if the plan changed meanwhile.
 */
#[AsQueryHandler]
final readonly class GetRollbackPlanQueryHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private ModuleRepository $moduleRepository,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws \RuntimeException when the target is unknown or not executed
     *
     * @return list<string> the versions to revert, in the order they would be reverted
     */
    public function __invoke(GetRollbackPlanQuery $query): array
    {
        $module = $query->set->module;
        if ($module !== null && ! $this->moduleRepository->isInstalled($module)) {
            throw new ModuleNotInstalledException($module);
        }

        return $this->migrationRunner->planRollback($query->set, $query->version);
    }
}
