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

use App\Upgrade\Application\TargetVersionRecorder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For the upgrade wizard, which announces the version before the legacy scripts run.
 */
#[AsCommand(name: 'centreon:upgrade:target-version', description: 'Print the version the platform update reaches', hidden: true)]
final readonly class TargetVersionConsoleCommand
{
    public function __construct(
        private TargetVersionRecorder $targetVersionRecorder,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The version the legacy scripts reach')]
        string $legacyVersion,
    ): int {
        $output->writeln($this->targetVersionRecorder->resolve($legacyVersion));

        return Command::SUCCESS;
    }
}
