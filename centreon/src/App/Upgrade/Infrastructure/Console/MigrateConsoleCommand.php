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

namespace App\Upgrade\Infrastructure\Console;

use App\Shared\Application\Command\CommandBus;
use App\Upgrade\Application\Command\MigrateCommand;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'centreon:migrations:migrate', description: 'Run the pending migrations of the Centreon Web sets, or of a single set')]
final readonly class MigrateConsoleCommand
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: MigrationSetOption::SET_DESCRIPTION . ', all Centreon Web sets when omitted')]
        ?string $set = null,
        #[Option(description: MigrationSetOption::MODULE_DESCRIPTION)]
        ?string $module = null,
    ): int {
        $command = new MigrateCommand(MigrationSetOption::resolve($set, $module));

        try {
            /** @var MigrationOutcome $outcome */
            $outcome = $this->commandBus->execute($command);
        } catch (\Throwable $exception) {
            if ($exception instanceof LockNotReleasedException && $exception->result instanceof MigrationOutcome) {
                $this->report($io, $exception->result);
            }

            $io->error($exception->getMessage());
            if ($io->isVerbose()) {
                $io->writeln((string) $exception);
            }

            return Command::FAILURE;
        }

        $this->report($io, $outcome);

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, MigrationOutcome $outcome): void
    {
        foreach ($outcome->warnings as $warning) {
            $io->warning($warning);
        }

        if ($outcome->executed === []) {
            $io->success('No migration to execute.');

            return;
        }

        $io->listing($outcome->executed);
        $io->success(sprintf('%d migration(s) executed.', count($outcome->executed)));
    }
}
