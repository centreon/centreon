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
use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'centreon:migrations:status', description: 'Show the executed and pending migrations of every set')]
final readonly class StatusConsoleCommand
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Restrict the status to a single migration set')]
        ?string $set = null,
    ): int {
        /** @var list<MigrationSetStatus> $statuses */
        $statuses = $this->queryBus->ask(new GetMigrationStatusQuery($set));

        foreach ($statuses as $status) {
            $io->section($status->set);
            $io->definitionList(
                ['Executed' => (string) \count($status->executed)],
                ['Last executed' => $status->executed === [] ? '-' : $status->executed[array_key_last($status->executed)]],
                ['Pending' => $status->pending === [] ? '-' : implode("\n", $status->pending)],
            );
        }

        return Command::SUCCESS;
    }
}
