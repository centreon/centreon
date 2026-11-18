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

namespace Core\RemoteChannel\Infrastructure;

use Core\RemoteChannel\Application\PollerCommandSenderInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Infrastructure\Broker\BrokerPollerCommandSender;
use Core\RemoteChannel\Infrastructure\Gorgone\GorgonePollerCommandSender;

/**
 * Splits the commands by channel of their poller (nagios_server.remote_channel): Gorgone pollers keep the
 * historical path, broker pollers go through the central broker remote API.
 */
final class RoutingPollerCommandSender implements PollerCommandSenderInterface
{
    public function __construct(
        private readonly GorgonePollerCommandSender $gorgoneSender,
        private readonly BrokerPollerCommandSender $brokerSender,
    ) {
    }

    public function send(array $batches): void
    {
        $gorgone = [];
        $broker = [];
        foreach ($batches as $batch) {
            if ($batch['target']->isBroker()) {
                $broker[] = $batch;
            } else {
                $gorgone[] = $batch;
            }
        }

        $failure = null;
        // The Gorgone path is fire-and-forget: send it first so a broker failure does not hold it back
        if ($gorgone !== []) {
            try {
                $this->gorgoneSender->send($gorgone);
            } catch (RemoteChannelException $ex) {
                $failure = $ex;
            }
        }
        if ($broker !== []) {
            $this->brokerSender->send($broker);
        }
        if ($failure !== null) {
            throw $failure;
        }
    }
}
