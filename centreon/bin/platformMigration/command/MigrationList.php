<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CloudMigration;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationList extends Command
{
    use CommandsInfo;

    protected static $defaultName = 'migration:list';

    protected static $defaultDescription = 'List all elements available for migration, their commands and their requirements.';

    protected function configure(): void
    {
        $this->addArgument(
            'names',
            InputArgument::IS_ARRAY,
            'Name of the elements you want information about.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $specifiedNames */
        $specifiedNames = $input->getArgument('names') ?? [];

        if ($specifiedNames === []) {
            $output->writeln('Elements available for migration:');
        }

        foreach ($this->getCommands() as $name => $info) {
            if ($specifiedNames === [] || in_array($name, $specifiedNames, true)) {
                $output->writeln(sprintf(
                    "<fg=yellow>%s</>\n    command: <fg=green>%s</>\n    requirements: %s",
                    $name,
                    $info['command'],
                    isset($info['requirements'])
                        ? implode(', ', $info['requirements'])
                        : 'N/A'
                ));
            }
        }

        if ($specifiedNames === []) {
            $output->writeln("\nOr run <fg=green>migrate:all</> to migrate all elements.");
        }

        return self::SUCCESS;
    }
}