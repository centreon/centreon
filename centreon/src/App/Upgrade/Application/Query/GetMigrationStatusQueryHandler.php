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
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Application\UpgradeLoggerInterface;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsQueryHandler]
final readonly class GetMigrationStatusQueryHandler
{
    public function __construct(
        private MigrationRunner $migrationRunner,
        private ModuleRepository $moduleRepository,
        private TargetVersionRecorder $targetVersionRecorder,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException when the requested set is the one of a module not installed
     * @throws \Throwable when the requested set cannot be read, reported per set otherwise
     *
     * @return list<MigrationSetStatus> every set when none is requested, but the modules not installed
     */
    public function __invoke(GetMigrationStatusQuery $query): array
    {
        if ($query->set instanceof MigrationSet) {
            if (! $this->isInstalled($query->set)) {
                throw new ModuleNotInstalledException((string) $query->set->module);
            }

            return [$this->status($query->set, $query->set->isModule() ? null : $this->legacyRefusal())];
        }

        $legacyRefusal = $this->legacyRefusal();
        $statuses = [];
        foreach ($this->migrationRunner->sets() as $set) {
            try {
                // Left out without loading their migrations, which run once the module is installed.
                if (! $this->isInstalled($set)) {
                    continue;
                }

                $statuses[] = $this->status($set, $legacyRefusal);
            } catch (\Throwable $exception) {
                // Report the failure on this set and keep listing the others.
                $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the status of the %s: %s', $set->label(), $exception->getMessage()), $exception);
                $statuses[] = MigrationSetStatus::unreadable($set, $exception->getMessage());
            }
        }

        return $statuses;
    }

    /**
     * @param string|null $legacyRefusal why migrate refuses the Centreon Web sets
     */
    private function status(MigrationSet $set, ?string $legacyRefusal): MigrationSetStatus
    {
        $status = $this->migrationRunner->status($set);

        return $set->isModule() || $legacyRefusal === null
            ? $status
            : MigrationSetStatus::read($set, $status->executed, $status->pending, $legacyRefusal);
    }

    /**
     * Why migrate() refuses the Centreon Web sets, null when it does not.
     */
    private function legacyRefusal(): ?string
    {
        try {
            $this->targetVersionRecorder->legacyVersion();
        } catch (\RuntimeException $exception) {
            return $exception->getMessage();
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the platform version: %s', $exception->getMessage()), $exception);

            return $exception->getMessage();
        }

        return null;
    }

    private function isInstalled(MigrationSet $set): bool
    {
        return $set->module === null || $this->moduleRepository->isInstalled($set->module);
    }
}
