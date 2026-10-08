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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;

#[AsCommandHandler]
final readonly class PatchHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private CommandRepository $commandRepository,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PatchHostCommand $command): Host
    {
        $host = $this->getHost($command);
        $this->assertNameIsAvailable($command, $host);

        $updatedHost = $this->applyChanges($host, $command);
        $this->saveAndNotify($host, $updatedHost, $command->updatedBy);

        return $updatedHost;
    }

    private function getHost(PatchHostCommand $command): Host
    {
        // Viewer-scoped: findOne returns null for a host outside the viewer's ACL scope, the same as
        // a nonexistent one, so a restricted viewer can never tell them apart.
        $host = $this->repository->findOne($command->id, $command->viewerId);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$command->id->value], 'id');
        }

        return $host;
    }

    private function assertNameIsAvailable(PatchHostCommand $command, Host $host): void
    {
        if (
            ! $command->name instanceof NoValue
            && $command->name->value !== $host->name->value
            && $this->repository->isNameUsedByHostOrTemplate($command->name)
        ) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }
    }

    private function applyChanges(Host $host, PatchHostCommand $command): Host
    {
        return $host->with(
            name: $command->name,
            address: $command->address,
            pollerId: $command->pollerId,
            alias: $command->alias,
            snmpVersion: $command->snmpVersion,
            timezoneId: $command->timezoneId,
            severityId: $command->severityId,
            extendedInformations: $command->extendedInformations instanceof NoValue
                ? new NoValue()
                : $command->extendedInformations->applyTo($host->extendedInformations),
            schedulingOptions: $command->schedulingOptions instanceof NoValue
                ? new NoValue()
                : $command->schedulingOptions->applyTo($host->schedulingOptions),
            dataProcessing: $this->dataProcessing($host, $command),
            checkOptions: $command->checkOptions instanceof NoValue
                ? new NoValue()
                : $command->checkOptions->applyTo($host->checkOptions),
            notifications: $command->notifications instanceof NoValue
                ? new NoValue()
                : $this->additiveInheritanceModeApplier->apply($command->notifications->applyTo($host->notifications)),
            activated: $command->activated,
        );
    }

    private function dataProcessing(Host $host, PatchHostCommand $command): NoValue|DataProcessing
    {
        $dataProcessing = $command->dataProcessing instanceof NoValue
            ? $host->dataProcessing
            : $command->dataProcessing->applyTo($host->dataProcessing);

        if ($this->providedCheckCommand($command)?->isCentreonMonitoringAgent() === true) {
            return $dataProcessing->withCentreonMonitoringAgentFreshness();
        }

        return $command->dataProcessing instanceof NoValue ? new NoValue() : $dataProcessing;
    }

    /**
     * The check command the update gives the host, null when it provides none or removes the current
     * one. Its existence has already been checked with the other references.
     */
    private function providedCheckCommand(PatchHostCommand $command): ?Command
    {
        if (! $command->checkOptions instanceof CheckOptionsChanges || ! $command->checkOptions->checkCommandId instanceof CommandId) {
            return null;
        }

        return $this->commandRepository->getById($command->checkOptions->checkCommandId);
    }

    /**
     * Nothing is written when nothing changed. A plain enable/disable keeps the narrow write and its
     * own event; anything else is a mass change, which carries the previous poller so that it is
     * flagged too.
     */
    private function saveAndNotify(Host $hostBefore, Host $hostAfter, int $updatedBy): void
    {
        if (! $hostAfter->hasSameConfigurationAs($hostBefore)) {
            $this->repository->update($hostAfter);
            $this->eventBus->fire(new HostMassChanged(
                $hostAfter,
                $updatedBy,
                previousPollerId: $hostAfter->pollerId->value !== $hostBefore->pollerId->value ? $hostBefore->pollerId : null,
            ));

            return;
        }

        if ($hostAfter->activated !== $hostBefore->activated) {
            $this->repository->updateActivationStatus($hostAfter->id(), $hostAfter->activated);
            $this->eventBus->fire(
                $hostAfter->activated
                    ? new HostEnabled($hostAfter, $updatedBy)
                    : new HostDisabled($hostAfter, $updatedBy),
            );
        }
    }
}
