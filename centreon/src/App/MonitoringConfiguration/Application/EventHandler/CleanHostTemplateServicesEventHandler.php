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

use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateServiceCommand;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Event\AsEventHandler;
use Psr\Log\LoggerInterface;

/**
 * Removes each service in its own command, so its own transaction: a failing service stays in place,
 * the others are still removed. Every failure is logged and swallowed, like
 * {@see DeployHostServicesEventHandler}: the host update is already committed.
 */
#[AsEventHandler]
final readonly class CleanHostTemplateServicesEventHandler
{
    public function __construct(
        private HostTemplateServicesCleaner $cleaner,
        private CommandBus $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostTemplateServicesCleanupRequested $event): void
    {
        try {
            $serviceIds = $this->cleaner->findServiceIdsToRemove($event->hostId, $event->previousTemplateIds, $event->templateIds);
        } catch (\Throwable $exception) {
            $this->logFailure('Unable to remove the services of the templates removed from the host', [
                'host_id' => $event->hostId->value,
                'exception' => $exception,
            ]);

            return;
        }

        foreach ($serviceIds as $serviceId) {
            $this->removeService($event, $serviceId);
        }
    }

    private function removeService(HostTemplateServicesCleanupRequested $event, ServiceId $serviceId): void
    {
        try {
            $this->commandBus->execute(new DeleteHostTemplateServiceCommand($serviceId, $event->hostId, $event->requestedBy));
        } catch (\Throwable $exception) {
            $this->logFailure('Unable to remove a service of a template removed from the host', [
                'host_id' => $event->hostId->value,
                'service_id' => $serviceId->value,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Delivered after the commit, so nothing can be rolled back: an escaping exception would turn
     * a saved host into a 5xx. A failing logger has to stop here too.
     *
     * @param array<string, mixed> $context
     */
    private function logFailure(string $message, array $context): void
    {
        try {
            $this->logger->error($message, $context);
        } catch (\Throwable) {
            // Nothing left to report it with.
        }
    }
}
