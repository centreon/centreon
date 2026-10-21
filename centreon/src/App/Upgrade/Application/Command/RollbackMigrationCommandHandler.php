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

namespace App\Upgrade\Application\Command;

use App\Shared\Application\Command\AsCommandHandler;
use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Application\MigrationSetGuard;
use App\Upgrade\Application\ModuleNotInstalledException;

#[AsCommandHandler]
final readonly class RollbackMigrationCommandHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private MigrationSetGuard $migrationSetGuard,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws \RuntimeException when another update, or another migration of the same module set, is in progress
     *
     * @return list<string> the reverted versions
     */
    public function __invoke(RollbackMigrationCommand $command): array
    {
        return $this->migrationSetGuard->run($command->set, fn (): array => $this->migrationRunner->rollback($command->set, $command->version));
    }
}
