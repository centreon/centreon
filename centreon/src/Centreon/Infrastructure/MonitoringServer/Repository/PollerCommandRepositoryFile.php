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

namespace Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Engine\EngineException;
use Centreon\Domain\MonitoringServer\Interfaces\PollerCommandRepositoryInterface;

/**
 * Legacy implementation: writes poller commands (SENDCFGFILE/RELOAD/RESTART/RELOADBROKER) to the
 * local centcore pipe, read by the Gorgone legacycmd module running on the same host. This is the
 * default transport, preserving the behavior of collocated/monolithic setups.
 */
final class PollerCommandRepositoryFile implements PollerCommandRepositoryInterface
{
    public function __construct(
        private readonly string $centcoreDirectory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendConfigFiles(int $pollerId): void
    {
        $this->write('SENDCFGFILE', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadEngine(int $pollerId): void
    {
        $this->write('RELOAD', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function restartEngine(int $pollerId): void
    {
        $this->write('RESTART', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadBroker(int $pollerId): void
    {
        $this->write('RELOADBROKER', $pollerId);
    }

    /**
     * @throws EngineException
     */
    private function write(string $command, int $pollerId): void
    {
        // Same destination as the historical centcore writers: a per-command file in the centcore
        // directory when it exists, otherwise the legacy centcore.cmd pipe.
        if (is_dir($this->centcoreDirectory)) {
            $file = $this->centcoreDirectory . DIRECTORY_SEPARATOR . microtime(true) . '-externalcommand.cmd';
        } else {
            $file = dirname($this->centcoreDirectory) . DIRECTORY_SEPARATOR . 'centcore.cmd';
        }

        if (@file_put_contents($file, $command . ':' . $pollerId . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new EngineException(
                sprintf(_('Could not write the "%s" command into the centcore pipe (%s)'), $command, $file)
            );
        }
    }
}
