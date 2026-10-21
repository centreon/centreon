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

use App\Upgrade\Infrastructure\Doctrine\MigrationGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'dev')]
#[AsCommand(name: 'centreon:migrations:generate', description: 'Generate a blank migration in a set')]
final readonly class GenerateConsoleCommand
{
    public function __construct(
        private MigrationGenerator $generator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: MigrationSetOption::SET_DESCRIPTION)]
        ?string $set = null,
        #[Option(description: MigrationSetOption::MODULE_DESCRIPTION)]
        ?string $module = null,
        #[Option(description: 'Generate a schema migration: a single idempotent statement, outside of any transaction')]
        bool $ddl = false,
        #[Option(description: 'Generate a data migration, run in a single transaction')]
        bool $dml = false,
    ): int {
        if ($ddl === $dml) {
            $io->error('Choose exactly one of --ddl or --dml.');

            return Command::INVALID;
        }

        $io->success(\sprintf('Generated "%s".', $this->generator->generate(MigrationSetOption::require($set, $module), $ddl)));

        return Command::SUCCESS;
    }
}
