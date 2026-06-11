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

use Centreon\Domain\Engine\Interfaces\EngineRepositoryInterface;
use Centreon\Domain\Gorgone\GorgoneTransport;

/**
 * Routes engine external commands either to the local centcore pipe (legacy, default) or to the
 * Gorgone REST API, depending on the "gorgone_command_transport" option (see {@see GorgoneTransport}).
 * Default keeps the historical centcore behavior, so existing collocated/monolithic setups are
 * unaffected; set the option to "gorgone" for a web tier running separately from the collection stack.
 */
final class EngineRepositorySelector implements EngineRepositoryInterface
{
    public function __construct(
        private readonly GorgoneTransport $transport,
        private readonly EngineRepositoryFile $fileRepository,
        private readonly EngineRepositoryGorgone $gorgoneRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommand(string $command): void
    {
        $this->repository()->sendExternalCommand($command);
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommands(array $commands): void
    {
        $this->repository()->sendExternalCommands($commands);
    }

    private function repository(): EngineRepositoryInterface
    {
        return $this->transport->useGorgone() ? $this->gorgoneRepository : $this->fileRepository;
    }
}
