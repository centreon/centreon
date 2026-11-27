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

namespace Core\RemoteChannel\Application;

use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\PollerTarget;

/**
 * Sends real-time external commands to pollers.
 */
interface PollerCommandSenderInterface
{
    /**
     * @param list<array{target: PollerTarget, commands: non-empty-list<string>}> $batches commands in the
     *                                                                                     centengine.cmd line format, "[timestamp] COMMAND;args"
     *
     * @throws RemoteChannelException when a poller did not apply its commands (the first failure; the message
     *                                names every poller that failed)
     */
    public function send(array $batches): void;
}
