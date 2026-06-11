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
use Centreon\Domain\Gorgone\Command\LegacyCmdCommand;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\PollerCommandRepositoryInterface;

/**
 * Sends legacy poller commands (SENDCFGFILE/RELOAD/RESTART/RELOADBROKER) through the central Gorgone
 * "legacycmd" module (POST centreon/legacycmd/command), instead of writing the local centcore pipe.
 * legacycmd dispatches each command to its target poller (config push over ZMQ, per-poller engine
 * reload, broker reload), so the web tier no longer copies config files, runs "systemctl" locally,
 * nor writes centcore.
 *
 * The action body is a JSON array of {"command": "<TYPE>", "target": <pollerId>} (Gorgone exposes
 * the whole POST body as the action "content", which legacycmd requires to be an array).
 */
final class PollerCommandRepositoryGorgone implements PollerCommandRepositoryInterface
{
    public function __construct(
        private readonly CommandRepositoryInterface $commandRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendConfigFiles(int $pollerId): void
    {
        $this->send('SENDCFGFILE', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadEngine(int $pollerId): void
    {
        $this->send('RELOAD', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function restartEngine(int $pollerId): void
    {
        $this->send('RESTART', $pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadBroker(int $pollerId): void
    {
        $this->send('RELOADBROKER', $pollerId);
    }

    /**
     * @throws EngineException
     */
    private function send(string $command, int $pollerId): void
    {
        $body = json_encode([['command' => $command, 'target' => $pollerId]]);
        if ($body === false) {
            throw new EngineException(_('Unable to encode the legacy command to send to Gorgone'));
        }

        try {
            $this->commandRepository->send(new LegacyCmdCommand($pollerId, $body));
        } catch (\Throwable $ex) {
            throw new EngineException(
                sprintf(
                    _('Error while sending the "%s" command for poller %d through Gorgone: %s'),
                    $command,
                    $pollerId,
                    $ex->getMessage()
                ),
                (int) $ex->getCode(),
                $ex
            );
        }
    }
}
