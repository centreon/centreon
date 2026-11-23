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

use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\DispatchStatus;

/**
 * MON-215450: when a poller stops, the central broker disables its hosts and services in the real-time database
 * (unified_sql _clean_tables on the poller event). The resource endpoints then do not find the resource and skip
 * it silently, answering success. For a poller on the broker channel, a resource whose poller is stopped or
 * disconnected is an explicit error instead (decision: disconnected poller = NOT_CONNECTED, never queued).
 */
final readonly class StoppedPollerGuard
{
    public function __construct(private ReadPollerTargetRepositoryInterface $pollerTargetRepository)
    {
    }

    /**
     * @throws RemoteChannelException 503 naming the poller
     */
    public function assertHostPollerRunning(?int $hostId): void
    {
        if ($hostId === null) {
            return;
        }
        $pollerName = $this->pollerTargetRepository->findStoppedBrokerPollerNameOfHost($hostId);
        if ($pollerName !== null) {
            throw RemoteChannelException::forPoller(
                $pollerName,
                DispatchStatus::NotConnected,
                'the poller is stopped, its resources are disabled'
            );
        }
    }
}
