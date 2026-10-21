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
use App\Upgrade\Application\MigrationSetLocker;
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Application\UpdateLocker;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;

#[AsCommandHandler]
final readonly class MigrateCommandHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private UpdateLocker $updateLocker,
        private MigrationSetLocker $migrationSetLocker,
        private TargetVersionRecorder $targetVersionRecorder,
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
    ) {
    }

    /**
     * @throws \RuntimeException when another update, or another migration of the same module set, is in progress,
     *                           or when legacy update scripts must run before the core sets
     *
     * @return list<string> the executed versions
     */
    public function __invoke(MigrateCommand $command): array
    {
        if ($command->set?->isModule() === true) {
            return $this->migrateModule($command->set);
        }

        if (! $this->updateLocker->lock()) {
            throw new \RuntimeException('An update is already in progress');
        }

        try {
            $this->assertNoPendingLegacyUpdate();

            if ($command->set instanceof MigrationSet) {
                return $this->migrationRunner->migrate($command->set);
            }

            $executed = [];
            foreach (MigrationSet::coreSets() as $set) {
                $executed = [...$executed, ...$this->migrationRunner->migrate($set)];
            }

            $this->targetVersionRecorder->record();

            return $executed;
        } finally {
            $this->updateLocker->unlock();
        }
    }

    /**
     * The core migrations expect the schema of the legacy scripts, and recording the platform version would skip them.
     */
    private function assertNoPendingLegacyUpdate(): void
    {
        $pending = $this->updateScriptFinder->findOrderedAvailableUpdates((string) $this->updateRepository->findCurrentVersion());
        if ($pending !== []) {
            throw new \RuntimeException(\sprintf(
                'The update scripts of %s are pending: update the platform first, with the upgrade wizard or the API.',
                implode(', ', $pending),
            ));
        }
    }

    /**
     * Its own lock: the platform update holds the update one while it upgrades the modules.
     *
     * @return list<string>
     */
    private function migrateModule(MigrationSet $set): array
    {
        if (! $this->migrationSetLocker->lock($set)) {
            throw new \RuntimeException(\sprintf('The "%s" migration set is already being migrated', $set->name));
        }

        try {
            return $this->migrationRunner->migrate($set);
        } finally {
            $this->migrationSetLocker->unlock($set);
        }
    }
}
