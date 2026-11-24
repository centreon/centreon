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

use CentreonLegacy\Core\Process\PhpConsole;

class MigrationLauncher
{
    /**
     * @param string|null $module null for the Centreon Web sets, which also records the platform version
     *
     * @throws \RuntimeException with the reason of the failure, extracted from the console output
     */
    public function migrate(?string $module = null): void
    {
        $command = ['bin/console.new', 'centreon:migrations:migrate', '--no-interaction'];
        if ($module !== null) {
            $command[] = '--module=' . $module;
        }

        // A wide terminal keeps the rendered exception message on a single line.
        $process = PhpConsole::process($command, ['COLUMNS' => '1000']);
        $process->run();

        if (! $process->isSuccessful()) {
            $reason = self::extractFailureReason($process->getErrorOutput() . $process->getOutput());

            throw new \RuntimeException($reason !== '' ? $reason : sprintf('Exit code %s (%s)', $process->getExitCode(), $process->getExitCodeText()));
        }
    }

    /**
     * The error the command reports, or the exception the console renders, without the lines around it.
     */
    public static function extractFailureReason(string $output): string
    {
        $patterns = [
            '/^ ?\[ERROR\] (.+?)(?:\R[ \t]*\R|\s*\z)/ms',
            // Thrown before the command runs, e.g. on an invalid option.
            '/^\s*In \S+ line \d+:[ \t]*\R(?:[ \t]*\R)*(.+?)\R[ \t]*\R/ms',
        ];

        $reason = $output;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output, $matches) === 1) {
                $reason = $matches[1];

                break;
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', $reason));
    }
}
