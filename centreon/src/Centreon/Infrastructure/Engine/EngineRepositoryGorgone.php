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

namespace Centreon\Infrastructure\Engine;

use Centreon\Domain\Engine\EngineException;
use Centreon\Domain\Engine\Interfaces\EngineRepositoryInterface;
use Centreon\Domain\Gorgone\Command\EngineCommand;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;

/**
 * Sends Engine external commands through the Gorgone REST API instead of the local centcore command
 * file, so the web/API tier can run on a separate host from the collection stack (centengine).
 *
 * {@see \Centreon\Domain\Engine\EngineService} prefixes every command with the legacy centcore
 * header "EXTERNALCMD:<pollerId>:[<ts>] " (consumed by Gorgone's legacycmd module when reading the
 * command file). The Gorgone "engine" module instead expects the bare Engine pipe line
 * "[<ts>] <COMMAND>;...", the poller being targeted through the request URI. We therefore split the
 * header off, group the remaining lines by poller and send one request per poller.
 */
final class EngineRepositoryGorgone implements EngineRepositoryInterface
{
    /**
     * Matches the header produced by EngineService::createCommandHeader():
     * "EXTERNALCMD:<pollerId>:<engineCommand>" where <engineCommand> already starts with "[<ts>] ".
     */
    private const COMMAND_HEADER_PATTERN = '/^EXTERNALCMD:(\d+):(.+)$/s';

    public function __construct(
        private readonly CommandRepositoryInterface $commandRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommand(string $command): void
    {
        $this->sendExternalCommands([$command]);
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommands(array $commands): void
    {
        foreach ($this->groupCommandsByPoller($commands) as $pollerId => $engineCommands) {
            $this->sendToPoller($pollerId, $engineCommands);
        }
    }

    /**
     * @param string[] $commands
     *
     * @throws EngineException
     *
     * @return array<int, string[]> Engine command lines indexed by poller id
     */
    private function groupCommandsByPoller(array $commands): array
    {
        $commandsByPoller = [];
        foreach ($commands as $command) {
            if (preg_match(self::COMMAND_HEADER_PATTERN, $command, $matches) !== 1) {
                throw new EngineException(
                    sprintf(_('Unable to extract the poller id from the external command: %s'), $command)
                );
            }
            $commandsByPoller[(int) $matches[1]][] = $matches[2];
        }

        return $commandsByPoller;
    }

    /**
     * @param string[] $engineCommands
     *
     * @throws EngineException
     */
    private function sendToPoller(int $pollerId, array $engineCommands): void
    {
        // Gorgone's HTTP server passes the whole decoded POST body as the action "content", and the
        // engine module reads content.commands - so the body must be {"commands":[...]} (not wrapped
        // in an extra "content" key).
        $body = json_encode(['commands' => array_values($engineCommands)]);
        if ($body === false) {
            throw new EngineException(_('Unable to encode the external commands to send to Gorgone'));
        }

        try {
            $this->commandRepository->send(new EngineCommand($pollerId, $body));
        } catch (\Throwable $ex) {
            throw new EngineException(
                sprintf(
                    _('Error while sending external commands to the poller %d through Gorgone: %s'),
                    $pollerId,
                    $ex->getMessage()
                ),
                (int) $ex->getCode(),
                $ex
            );
        }
    }
}
