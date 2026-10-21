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

use App\Shared\Application\Query\QueryBus;
use App\Upgrade\Application\Query\GetUpgradeTargetVersionQuery;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For the upgrade wizard, which announces the version before the legacy scripts run.
 */
#[AsCommand(name: 'centreon:upgrade:target-version', description: 'Print the version the platform update reaches', hidden: true)]
final readonly class TargetVersionConsoleCommand
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The version the legacy scripts reach')]
        string $legacyVersion,
    ): int {
        try {
            /** @var string $targetVersion */
            $targetVersion = $this->queryBus->ask(new GetUpgradeTargetVersionQuery($legacyVersion));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            if ($io->isVerbose()) {
                $io->writeln((string) $exception);
            }

            return Command::FAILURE;
        }

        $io->writeln($targetVersion);

        return Command::SUCCESS;
    }
}
