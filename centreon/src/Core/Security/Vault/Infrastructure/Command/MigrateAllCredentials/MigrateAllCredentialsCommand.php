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

declare(strict_types = 1);

namespace Core\Security\Vault\Infrastructure\Command\MigrateAllCredentials;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentials;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'credentials:migrate-vault',
    description: 'Migrate passwords to vault',
)]
final class MigrateAllCredentialsCommand extends Command
{
    use LoggerTrait;

    public function __construct(
        readonly private MigrateAllCredentials $useCase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            ($this->useCase)(new MigrateAllCredentialsPresenter($output));

        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $output->writeln("<error>{(string) {$ex}}</error>");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}