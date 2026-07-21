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

namespace App\Upgrade\Infrastructure;

use App\Upgrade\Application\CacheClearer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final readonly class SymfonyCacheClearer implements CacheClearer
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        private LoggerInterface $logger,
    ) {
    }

    public function clear(): void
    {
        $this->logger->info('Clearing Symfony cache');

        foreach (['bin/console', 'bin/console.new'] as $console) {
            $process = new Process(['php', $console, 'cache:clear'], $this->projectDir);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->logger->warning('Cache clear returned a non-zero exit code', [
                    'console' => $console,
                    'output' => $process->getErrorOutput(),
                ]);
            }
        }
    }
}
