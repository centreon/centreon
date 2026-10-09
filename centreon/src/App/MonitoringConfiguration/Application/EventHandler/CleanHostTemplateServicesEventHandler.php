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

use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\MonitoringConfiguration\Domain\Service\ServiceDeployer;
use App\Shared\Domain\Event\AsEventHandler;
use Psr\Log\LoggerInterface;

/**
 * A failure is logged and swallowed, like {@see DeployHostServicesEventHandler}: the host update is
 * already committed.
 */
#[AsEventHandler]
final readonly class CleanHostTemplateServicesEventHandler
{
    public function __construct(
        private ServiceDeployer $serviceDeployer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostTemplateServicesCleanupRequested $event): void
    {
        try {
            $this->serviceDeployer->removeFromRemovedTemplates(
                $event->hostId,
                $event->previousTemplateIds,
                $event->templateIds,
                $event->requestedBy,
            );
        } catch (\Throwable $exception) {
            $this->logFailure($event, $exception);
        }
    }

    /**
     * Delivered after the commit, so nothing can be rolled back: an escaping exception would turn
     * a saved host into a 5xx. A failing logger has to stop here too.
     */
    private function logFailure(HostTemplateServicesCleanupRequested $event, \Throwable $exception): void
    {
        try {
            $this->logger->error('Unable to remove the services of the templates removed from the host', [
                'host_id' => $event->hostId->value,
                'exception' => $exception,
            ]);
        } catch (\Throwable) {
            // Nothing left to report it with.
        }
    }
}
