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
use App\Shared\Application\Query\QueryBus;
use App\Upgrade\Application\Command\RollbackMigrationCommand;
use App\Upgrade\Application\Query\GetRollbackPlanQuery;
use App\Upgrade\Domain\Exception\LockNotReleasedException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'centreon:migrations:rollback', description: 'Revert the executed migrations of a set versioned after the given version')]
final readonly class RollbackConsoleCommand
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The version to roll back to, kept applied: as listed by status -v, its class name or its timestamp ("prev" reverts the last migration, "first" reverts them all)')]
        string $version,
        #[Option(description: MigrationSetOption::SET_DESCRIPTION)]
        ?string $set = null,
        #[Option(description: MigrationSetOption::MODULE_DESCRIPTION)]
        ?string $module = null,
    ): int {
        $migrationSet = MigrationSetOption::require($set, $module);

        try {
            return $this->rollback($input, $io, $migrationSet, $version);
        } catch (\Throwable $exception) {
            if ($exception instanceof LockNotReleasedException && $exception->result instanceof MigrationOutcome) {
                $io->listing($exception->result->executed);
                $this->report($io, $exception->result);
            }

            $io->error($exception->getMessage());
            if ($io->isVerbose()) {
                $io->writeln((string) $exception);
            }

            return Command::FAILURE;
        }
    }

    private function rollback(InputInterface $input, SymfonyStyle $io, MigrationSet $migrationSet, string $version): int
    {
        /** @var list<string> $toRevert */
        $toRevert = $this->queryBus->ask(new GetRollbackPlanQuery($migrationSet, $version));
        if ($toRevert === []) {
            $io->success('No migration to revert.');

            return Command::SUCCESS;
        }

        $io->listing($toRevert);
        $question = sprintf('Revert these %d migration(s) of the %s? The platform version is not changed.', count($toRevert), $migrationSet->label());
        if ($input->isInteractive() && ! $io->confirm($question, false)) {
            $io->warning('Rollback cancelled.');

            return Command::SUCCESS;
        }

        // Refused if another run changed the set meanwhile.
        /** @var MigrationOutcome $outcome */
        $outcome = $this->commandBus->execute(new RollbackMigrationCommand($migrationSet, $version, expected: $toRevert));
        $this->report($io, $outcome);

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, MigrationOutcome $outcome): void
    {
        foreach ($outcome->warnings as $warning) {
            $io->warning($warning);
        }

        $io->success(sprintf('%d migration(s) reverted.', count($outcome->executed)));
    }
}
