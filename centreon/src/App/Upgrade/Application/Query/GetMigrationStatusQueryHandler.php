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
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Application\UpgradeLoggerInterface;
use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\ModuleUpgradePendingException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsQueryHandler]
final readonly class GetMigrationStatusQueryHandler
{
    public function __construct(
        private MigrationRunnerInterface $migrationRunner,
        private ModuleRepository $moduleRepository,
        private TargetVersionRecorder $targetVersionRecorder,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * A set that cannot be read is reported as such, the requested one included.
     *
     * @throws ModuleNotInstalledException when the requested set is the one of a module not installed
     * @throws \Throwable when the installed modules cannot be listed while every set could be read
     *
     * @return list<MigrationSetStatus> the requested set, or every set except the uninstalled modules
     */
    public function __invoke(GetMigrationStatusQuery $query): array
    {
        if ($query->set instanceof MigrationSet) {
            try {
                $isInstalled = $this->isInstalled($query->set);
            } catch (\Throwable $exception) {
                return [$this->unreadable($query->set, $exception)];
            }

            if (! $isInstalled) {
                $refusal = new ModuleNotInstalledException((string) $query->set->module);
                $this->upgradeLogger->warning($this->platformVersion, sprintf('Status of the %s refused: %s', $query->set->label(), $refusal->getMessage()));

                throw $refusal;
            }

            return [$this->status($query->set, $query->set->module === null ? $this->legacyCheck() : $this->moduleUpgradeCheck($query->set->module))];
        }

        $legacyCheck = $this->legacyCheck();
        $statuses = [];
        $sets = $this->migrationRunner->sets();
        foreach ($sets as $set) {
            try {
                // Uninstalled modules are skipped before their migration classes are loaded.
                if (! $this->isInstalled($set)) {
                    continue;
                }
            } catch (\Throwable $exception) {
                $statuses[] = $this->unreadable($set, $exception);

                continue;
            }

            $statuses[] = $this->status($set, $set->module === null ? $legacyCheck : $this->moduleUpgradeCheck($set->module));
        }

        return [...$statuses, ...$this->vanishedModuleSets($sets, $statuses)];
    }

    /**
     * An installed module whose migrations directory is gone, while some of them ran, is reported unreadable.
     *
     * @param list<MigrationSet> $scannedSets
     * @param list<MigrationSetStatus> $scannedStatuses
     *
     * @throws \Throwable when the installed modules cannot be listed while no other set reports a read error
     *
     * @return list<MigrationSetStatus>
     */
    private function vanishedModuleSets(array $scannedSets, array $scannedStatuses): array
    {
        try {
            $installedModules = $this->moduleRepository->findInstalledModules();
        } catch (\Throwable $exception) {
            // Never a success without the check: a read error already fails the status.
            foreach ($scannedStatuses as $status) {
                if ($status->readError !== null) {
                    $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot list the installed modules: %s', $exception->getMessage()), $exception);

                    return [];
                }
            }

            throw $exception;
        }

        $scannedModules = array_map(static fn (MigrationSet $set): ?string => $set->module, $scannedSets);
        $statuses = [];
        foreach ($installedModules as $module) {
            if (in_array($module, $scannedModules, true)) {
                continue;
            }
            if (! MigrationSet::isModuleName($module)) {
                continue;
            }
            $set = MigrationSet::module($module);
            try {
                $executed = $this->moduleRepository->countExecutedMigrations($module);
            } catch (\Throwable $exception) {
                $statuses[] = $this->unreadable($set, $exception);

                continue;
            }

            if ($executed > 0) {
                $reason = sprintf('No migration set was found for the "%s" module, while %d migrations of it were executed: check its migrations directory.', $module, $executed);
                $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the status of the %s: %s', $set->label(), $reason));
                $statuses[] = MigrationSetStatus::unreadable($set, $reason);
            }
        }

        return $statuses;
    }

    /**
     * @param \Throwable|null $legacyCheck the failure of the legacy check: of the platform version for a Centreon Web set, of the module upgrade for a module set
     */
    private function status(MigrationSet $set, ?\Throwable $legacyCheck): MigrationSetStatus
    {
        // Only pending legacy scripts are a refusal: any other failure is a broken platform.
        $isRefusal = $legacyCheck instanceof LegacyUpdatesPendingException || $legacyCheck instanceof ModuleUpgradePendingException;
        if ($legacyCheck instanceof \Throwable && ! $isRefusal) {
            return MigrationSetStatus::unreadable($set, $legacyCheck->getMessage());
        }

        try {
            $status = $this->migrationRunner->status($set);
        } catch (\Throwable $exception) {
            return $this->unreadable($set, $exception);
        }

        return $isRefusal ? MigrationSetStatus::read($set, $status->executed, $status->pending, $legacyCheck->getMessage()) : $status;
    }

    private function unreadable(MigrationSet $set, \Throwable $exception): MigrationSetStatus
    {
        $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the status of the %s: %s', $set->label(), $exception->getMessage()), $exception);

        return MigrationSetStatus::unreadable($set, $exception->getMessage());
    }

    /**
     * The failure of the legacy version check, null when it passes.
     */
    private function legacyCheck(): ?\Throwable
    {
        try {
            $this->targetVersionRecorder->legacyVersion();
        } catch (LegacyUpdatesPendingException $exception) {
            return $exception;
        } catch (PlatformVersionUnknownException $exception) {
            $this->upgradeLogger->warning($this->platformVersion, $exception->getMessage());

            return $exception;
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the platform version: %s', $exception->getMessage()), $exception);

            return $exception;
        }

        return null;
    }

    /**
     * The failure of the module upgrade check, null when it passes.
     */
    private function moduleUpgradeCheck(string $module): ?\Throwable
    {
        try {
            return $this->moduleRepository->hasPendingLegacyUpgrade($module) ? new ModuleUpgradePendingException($module) : null;
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Cannot read the upgrade state of the "%s" module: %s', $module, $exception->getMessage()), $exception);

            return $exception;
        }
    }

    private function isInstalled(MigrationSet $set): bool
    {
        return $set->module === null || $this->moduleRepository->isInstalled($set->module);
    }
}
