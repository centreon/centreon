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

namespace CentreonLegacy\Core\Cache;

use CentreonLegacy\Core\Process\PhpConsole;
use CentreonLog;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * A batch runs through suspended() then clears once: concurrent clears of the same directory fail.
 */
class CacheClearer
{
    private int $suspensions = 0;

    /**
     * @throws RuntimeException when the cache of the legacy kernel cannot be cleared
     */
    public function clear(): void
    {
        if ($this->suspensions > 0) {
            return;
        }

        // Both are attempted. Module upgrades only ever cleared the legacy one: a failure of the new one must not fail them.
        $legacyFailure = null;
        try {
            $this->run(['bin/console', 'cache:clear']);
        } catch (RuntimeException $exception) {
            $legacyFailure = $exception;
        }

        try {
            $this->run(['bin/console.new', 'cache:clear']);
        } catch (RuntimeException $exception) {
            CentreonLog::create()->warning(CentreonLog::TYPE_UPGRADE, 'Clearing the cache of the new kernel failed', [], $exception);
        }

        if ($legacyFailure instanceof RuntimeException) {
            throw $legacyFailure;
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function suspended(callable $callback): mixed
    {
        ++$this->suspensions;

        try {
            return $callback();
        } finally {
            --$this->suspensions;
        }
    }

    /**
     * @param list<string> $command
     *
     * @throws RuntimeException when the command fails or times out
     */
    protected function run(array $command): void
    {
        $process = PhpConsole::process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
