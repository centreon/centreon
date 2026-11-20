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
use Centreon\Infrastructure\Gorgone\LegacyCommandException;
use Centreon\Infrastructure\Gorgone\LegacyCommandSender;

/**
 * Sends the external commands (EXTERNALCMD:<poller id>:[timestamp] COMMAND;...) to Gorgone through its API.
 */
final class EngineRepositoryGorgone implements EngineRepositoryInterface
{
    public function __construct(private readonly LegacyCommandSender $legacyCommandSender)
    {
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommands(array $commands): void
    {
        try {
            $this->legacyCommandSender->sendLines(array_values($commands));
        } catch (LegacyCommandException $ex) {
            throw new EngineException($ex->getMessage(), 0, $ex);
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
