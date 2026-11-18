<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use Core\RemoteChannel\Application\PollerCommandSenderInterface;
use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;

/**
 * Real-time external commands (API v2: acknowledgement, downtime, check, comment, submit result) sent per poller
 * through its channel: Gorgone (historical) or the central broker remote API.
 *
 * EngineService formats each command as "EXTERNALCMD:<poller id or uid>:[timestamp] COMMAND;args".
 * A failure is raised as an EngineException whose code is the HTTP status matching the reason
 * (503 not connected, 409 poller too old, 422 rejected, 504 timeout, 502 broker API unreachable).
 */
final class EngineRepositoryRemoteChannel implements EngineRepositoryInterface
{
    private const LINE_PATTERN = '/^EXTERNALCMD:([^:]*):(.*)$/s';

    public function __construct(
        private readonly PollerCommandSenderInterface $commandSender,
        private readonly ReadPollerTargetRepositoryInterface $pollerTargetRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommands(array $commands): void
    {
        $batches = [];
        foreach ($commands as $line) {
            if (preg_match(self::LINE_PATTERN, (string) $line, $matches) !== 1) {
                throw new EngineException(sprintf(_('Invalid external command "%s"'), (string) $line));
            }
            $target = $this->pollerTargetRepository->findByIdOrUid($matches[1]);
            if ($target === null) {
                $ex = RemoteChannelException::unknownPoller($matches[1]);

                throw new EngineException($ex->getMessage(), $ex->getCode(), $ex);
            }
            $batches[$target->id] ??= ['target' => $target, 'commands' => []];
            $batches[$target->id]['commands'][] = $matches[2];
        }
        if ($batches === []) {
            return;
        }

        try {
            $this->commandSender->send(array_values($batches));
        } catch (RemoteChannelException $ex) {
            throw new EngineException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommand(string $command): void
    {
        $this->sendExternalCommands([$command]);
    }
}
