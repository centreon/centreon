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

namespace Centreon\Infrastructure\Service;

use Centreon\Domain\Entity\Command;
use Centreon\Infrastructure\Gorgone\LegacyCommandException;
use Centreon\Infrastructure\Gorgone\LegacyCommandSender;

/**
 * Sends the remote server commands (STARTWORKER, SENDEXPORTFILE) to Gorgone through LegacyCommandSender:
 * its HTTP API for containerized pollers, the centcore files otherwise.
 */
class CentcoreCommandService
{
    public function __construct(private ?LegacyCommandSender $legacyCommandSender = null)
    {
    }

    /**
     * @param Command $command
     * @return bool true if the command has been handed over to Gorgone
     */
    public function sendCommand(Command $command)
    {
        $sender = $this->legacyCommandSender ??= LegacyCommandSender::create();
        $commandLine = $command->getCommandLine();

        try {
            if (str_starts_with($commandLine, Command::COMMAND_TRANSFER_EXPORT_FILES)) {
                // SENDEXPORTFILE:<remote server id>:<task id>
                [, $remoteServerId, $taskId] = array_pad(explode(':', $commandLine, 3), 3, '0');
                $sender->sendExportFiles((int) $remoteServerId, (int) $taskId);
            } else {
                $sender->sendLines($commandLine);
            }
        } catch (LegacyCommandException) {
            return false;
        }

        return true;
    }
}
