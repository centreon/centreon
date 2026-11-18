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

namespace Core\RemoteChannel\Infrastructure\Gorgone;

use Centreon\Infrastructure\Gorgone\LegacyCommandException;
use Centreon\Infrastructure\Gorgone\LegacyCommandSender;
use Core\RemoteChannel\Application\PollerCommandSenderInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;

/**
 * Historical behaviour: EXTERNALCMD lines handed to Gorgone legacycmd (centcore files, or its API for
 * containerized pollers). Gorgone sanitises the commands itself.
 */
final class GorgonePollerCommandSender implements PollerCommandSenderInterface
{
    public function __construct(private readonly LegacyCommandSender $legacyCommandSender)
    {
    }

    public function send(array $batches): void
    {
        $lines = [];
        foreach ($batches as ['target' => $target, 'commands' => $commands]) {
            foreach ($commands as $command) {
                $lines[] = 'EXTERNALCMD:' . $target->id . ':' . $command;
            }
        }
        if ($lines === []) {
            return;
        }

        try {
            $this->legacyCommandSender->sendLines($lines);
        } catch (LegacyCommandException $ex) {
            throw new RemoteChannelException($ex->getMessage(), null, $ex);
        }
    }
}
