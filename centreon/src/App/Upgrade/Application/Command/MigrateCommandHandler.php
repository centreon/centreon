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
use App\Upgrade\Application\UpgradeLoggerInterface;
use App\Upgrade\Domain\MigrationSet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommandHandler]
final readonly class MigrateCommandHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private MigrationSetGuard $migrationSetGuard,
        private TargetVersionRecorder $targetVersionRecorder,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws \RuntimeException when another update, or another migration of the same module set, is in progress, or
     *                           when legacy update scripts must run before the Centreon Web sets
     *
     * @return list<string> the executed versions
     */
    public function __invoke(MigrateCommand $command): array
    {
        $set = $command->set;

        try {
            return $this->migrationSetGuard->run($set, fn (): array => $set?->isModule() === true ? $this->migrateSet($set) : $this->migrateWeb($set));
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error(
                $this->platformVersion,
                sprintf('Migration of the %s failed: %s', $set?->label() ?? 'Centreon Web migration sets', $exception->getMessage()),
                $exception,
            );

            throw $exception;
        }
    }

    /**
     * @param MigrationSet|null $set null for all the Centreon Web sets, then record the target version
     *
     * @return list<string>
     */
    private function migrateWeb(?MigrationSet $set): array
    {
        $legacyVersion = $this->targetVersionRecorder->legacyVersion();

        if ($set instanceof MigrationSet) {
            return $this->migrateSet($set);
        }

        $executed = [];
        foreach (MigrationSet::webSets() as $webSet) {
            $executed = [...$executed, ...$this->migrateSet($webSet)];
        }

        $this->targetVersionRecorder->record($legacyVersion);

        return $executed;
    }

    /**
     * @return list<string>
     */
    private function migrateSet(MigrationSet $set): array
    {
        $step = $set->migrationStep();
        $this->upgradeLogger->step($this->platformVersion, $step, "Starting step '{$step}'");
        $startedAt = hrtime(true);

        try {
            $executed = $this->migrationRunner->migrate($set);
        } catch (\Throwable $exception) {
            $this->upgradeLogger->stepFailure($this->platformVersion, $step, "Step '{$step}' failed: {$exception->getMessage()}", $exception);

            throw $exception;
        }

        $durationMs = intdiv(hrtime(true) - $startedAt, 1_000_000);
        $this->upgradeLogger->stepCompleted($this->platformVersion, $step, $durationMs, sprintf("Step '%s' completed in %dms: %d migration(s) executed", $step, $durationMs, count($executed)));

        return $executed;
    }
}
