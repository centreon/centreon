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
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Domain\MigrationSet;

#[AsCommandHandler]
final readonly class MigrateCommandHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private MigrationSetGuard $migrationSetGuard,
        private TargetVersionRecorder $targetVersionRecorder,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws \RuntimeException when another update, or another migration of the same module set, is in progress, or
     *                           when legacy update scripts must run before the core sets
     *
     * @return list<string> the executed versions
     */
    public function __invoke(MigrateCommand $command): array
    {
        $set = $command->set;

        return $this->migrationSetGuard->run($set, fn (): array => $set?->isModule() === true ? $this->migrationRunner->migrate($set) : $this->migrateCore($set));
    }

    /**
     * @param MigrationSet|null $set null for all the core sets, then record the target version
     *
     * @return list<string>
     */
    private function migrateCore(?MigrationSet $set): array
    {
        $legacyVersion = $this->targetVersionRecorder->legacyVersion();

        if ($set instanceof MigrationSet) {
            return $this->migrationRunner->migrate($set);
        }

        $executed = [];
        foreach (MigrationSet::coreSets() as $coreSet) {
            $executed = [...$executed, ...$this->migrationRunner->migrate($coreSet)];
        }

        $this->targetVersionRecorder->record($legacyVersion);

        return $executed;
    }
}
