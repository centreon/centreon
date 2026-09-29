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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommand;
use App\MonitoringConfiguration\Domain\Event\HostServicesDuplicationRequested;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Event\AsEventHandler;
use Psr\Log\LoggerInterface;

/**
 * A failure is logged and swallowed: the copy is already committed, and legacy behaves the same,
 * service duplication being a separate step there.
 */
#[AsEventHandler]
final readonly class DuplicateHostServicesEventHandler
{
    public function __construct(
        private CommandBus $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostServicesDuplicationRequested $event): void
    {
        try {
            $this->commandBus->execute(new DuplicateHostServicesCommand($event->sourceHostId, $event->newHostId));
        } catch (\Throwable $exception) {
            $this->logFailure($event, $exception);
        }
    }

    /**
     * Delivered after the commit, so nothing can be rolled back: an escaping exception would turn a
     * duplicated host into a 5xx. A failing logger has to stop here too.
     */
    private function logFailure(HostServicesDuplicationRequested $event, \Throwable $exception): void
    {
        try {
            $this->logger->error('Unable to duplicate the services of the duplicated host', [
                'source_host_id' => $event->sourceHostId->value,
                'new_host_id' => $event->newHostId->value,
                'exception' => $exception,
            ]);
        } catch (\Throwable) {
            // Nothing left to report it with.
        }
    }
}
