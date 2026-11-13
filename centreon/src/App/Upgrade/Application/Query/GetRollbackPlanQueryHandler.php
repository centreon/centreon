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
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;
use App\Upgrade\Domain\Service\UpgradeLoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Takes no lock: the rollback refuses to run if the plan changed meanwhile.
 */
#[AsQueryHandler]
final readonly class GetRollbackPlanQueryHandler
{
    public function __construct(
        private MigrationRunnerInterface $migrationRunner,
        private ModuleRepository $moduleRepository,
        private UpgradeLoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @throws ModuleNotInstalledException
     * @throws MigrationSetRefusedException
     * @throws RollbackTargetNotFoundException
     *
     * @return list<string> the versions to revert, in the order they would be reverted
     */
    public function __invoke(GetRollbackPlanQuery $query): array
    {
        $set = $query->set;

        try {
            if ($set->module !== null && ! $this->moduleRepository->isInstalled($set->module)) {
                throw new ModuleNotInstalledException($set->module);
            }

            return $this->migrationRunner->planRollback($set, $query->version);
        } catch (ModuleNotInstalledException|RollbackTargetNotFoundException $refusal) {
            $this->upgradeLogger->warning($this->platformVersion, sprintf('Rollback plan of the %s refused: %s', $set->label(), $refusal->getMessage()));

            throw $refusal;
        } catch (\Throwable $exception) {
            $this->upgradeLogger->error($this->platformVersion, sprintf('Rollback plan of the %s failed: %s', $set->label(), $exception->getMessage()), $exception);

            throw $exception;
        }
    }
}
