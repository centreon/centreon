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
use App\Upgrade\Application\Service\MigrationApplier;
use App\Upgrade\Application\Service\MigrationSetGuard;
use App\Upgrade\Application\UpgradeLoggerInterface;
use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationInterruptedException;
use App\Upgrade\Domain\Exception\MigrationSetBusyException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\ModuleUpgradePendingException;
use App\Upgrade\Domain\Exception\PlatformVersionNotRecordedException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommandHandler]
final readonly class MigrateCommandHandler
{
    public function __construct(
        private MigrationSetGuard $migrationSetGuard,
        private MigrationApplier $migrationApplier,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws MigrationSetBusyException
     * @throws LockNotReleasedException
     * @throws LegacyUpdatesPendingException
     * @throws ModuleUpgradePendingException
     * @throws MigrationSetRefusedException
     * @throws MigrationFailedException
     * @throws MigrationInterruptedException
     * @throws PlatformVersionNotRecordedException
     * @throws PlatformVersionUnknownException
     * @throws \LogicException
     */
    public function __invoke(MigrateCommand $command): MigrationOutcome
    {
        $set = $command->set;

        try {
            return $this->migrationSetGuard->run(
                $set,
                fn (): MigrationOutcome => $set instanceof MigrationSet ? $this->migrationApplier->applySet($set) : $this->migrationApplier->applyWebSets(),
            );
        } catch (ModuleNotInstalledException|MigrationSetBusyException|LegacyUpdatesPendingException|ModuleUpgradePendingException|PlatformVersionUnknownException $refusal) {
            $this->upgradeLogger->warning($this->platformVersion, sprintf('Migration of the %s refused: %s', $this->label($set), $refusal->getMessage()));

            throw $refusal;
        } catch (LockNotReleasedException $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Migration of the %s: %s', $this->label($set), $exception->getMessage()), $exception);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Migration of the %s failed: %s', $this->label($set), $exception->getMessage()), $exception);

            throw $exception;
        }
    }

    private function label(?MigrationSet $set): string
    {
        return $set?->label() ?? 'Centreon Web migration sets';
    }
}
