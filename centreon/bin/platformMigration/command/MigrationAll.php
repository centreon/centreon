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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationAll extends Migration {
    use CommandsInfo;

    protected static $defaultName = 'migration:all';

    protected static $defaultDescription = 'Migrate all configuration data from the current platform to the defined target platform.';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('target-url');
        $token = $input->getArgument('target-token');

        foreach ($this->getCommands() as $info) {
            $command = new ArrayInput([
                'command' => $info['command'],
                'target-url' => $url,
                'target-token' => $token,
                '-v' => $output->isVerbose(),
                '-vv' => $output->isVeryVerbose(),
                '-vvv' => $output->isDebug(),
                '-q' => $output->isQuiet(),
                '--ansi' => $output->isDecorated(),
                '--no-ansi' => ! $output->isDecorated(),
            ]);
            $command->setInteractive(false);

            $returnCode = $this->getApplication()?->doRun(
                $command,
                $output
            );

            if ($returnCode !== 0) {

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
