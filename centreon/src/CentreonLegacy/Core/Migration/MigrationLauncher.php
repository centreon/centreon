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

namespace CentreonLegacy\Core\Migration;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class MigrationLauncher
{
    /**
     * @param string|null $module null for the core sets, which also records the platform version
     *
     * @throws \RuntimeException with the reason of the failure, extracted from the console output
     */
    public function migrate(?string $module = null): void
    {
        $command = [(new PhpExecutableFinder())->find(false) ?: 'php', 'bin/console.new', 'centreon:migrations:migrate', '--no-interaction'];
        if ($module !== null) {
            $command[] = '--module=' . $module;
        }

        // A wide terminal keeps the rendered exception message on a single line.
        $process = new Process($command, \dirname(__DIR__, 4), ['COLUMNS' => '1000'], null, null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(self::extractFailureReason($process->getErrorOutput() . $process->getOutput()));
        }
    }

    /**
     * The rendered exception message, without the log lines and the usage around it.
     */
    public static function extractFailureReason(string $output): string
    {
        $reason = preg_match('/^\s*In \S+ line \d+:[ \t]*\R(?:[ \t]*\R)*(.+?)\R[ \t]*\R/ms', $output, $matches) === 1
            ? $matches[1]
            : $output;

        return trim((string) preg_replace('/\s+/', ' ', $reason));
    }
}
