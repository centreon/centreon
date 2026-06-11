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

use Centreon\Domain\Gorgone\GorgoneTransport;
use Centreon\Domain\MonitoringServer\Interfaces\PollerCommandRepositoryInterface;

/**
 * Routes poller commands either to the Gorgone REST API or to the local centcore pipe, depending on
 * the "gorgone_command_transport" option (see {@see GorgoneTransport}). Default is the legacy
 * centcore transport, so existing collocated/monolithic setups are unaffected; set the option to
 * "gorgone" for a web tier running separately from the collection stack.
 */
final class PollerCommandRepositorySelector implements PollerCommandRepositoryInterface
{
    public function __construct(
        private readonly GorgoneTransport $transport,
        private readonly PollerCommandRepositoryFile $fileRepository,
        private readonly PollerCommandRepositoryGorgone $gorgoneRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendConfigFiles(int $pollerId): void
    {
        $this->repository()->sendConfigFiles($pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadEngine(int $pollerId): void
    {
        $this->repository()->reloadEngine($pollerId);
    }

    /**
     * @inheritDoc
     */
    public function restartEngine(int $pollerId): void
    {
        $this->repository()->restartEngine($pollerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadBroker(int $pollerId): void
    {
        $this->repository()->reloadBroker($pollerId);
    }

    private function repository(): PollerCommandRepositoryInterface
    {
        return $this->transport->useGorgone() ? $this->gorgoneRepository : $this->fileRepository;
    }
}
