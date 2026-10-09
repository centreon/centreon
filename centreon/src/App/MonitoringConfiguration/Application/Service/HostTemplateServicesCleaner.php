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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;

/**
 * Deletes the services a host got from the templates it no longer has, like a deleted host takes its
 * services with it: each one is announced, and its vault entry is purged once the update is committed.
 *
 * A service goes when a template the host lost provided it, directly or through its ancestors, and no
 * template the host keeps (nor their ancestors) provides it too.
 */
final readonly class HostTemplateServicesCleaner
{
    public function __construct(
        private HostTemplateRepository $templateRepository,
        private ServiceRepository $serviceRepository,
        private EventBus $eventBus,
    ) {
    }

    public function cleanUp(Host $hostBefore, Host $hostAfter, int $deletedBy): void
    {
        $keptIds = array_map(static fn (HostTemplateId $id): int => $id->value, $hostAfter->templateIds->toArray());
        $lostTemplates = array_values(array_filter(
            $hostBefore->templateIds->toArray(),
            static fn (HostTemplateId $id): bool => ! in_array($id->value, $keptIds, true),
        ));
        if ($lostTemplates === []) {
            return;
        }

        $serviceTemplateIds = array_values(array_diff(
            $this->templateRepository->findServiceTemplateIds(new Collection($lostTemplates, HostTemplateId::class)),
            $this->templateRepository->findServiceTemplateIds($hostAfter->templateIds),
        ));
        if ($serviceTemplateIds === []) {
            return;
        }

        foreach ($this->serviceRepository->findFromServiceTemplates($hostAfter->id(), $serviceTemplateIds) as $service) {
            $this->serviceRepository->remove($service);
            $this->eventBus->fire(new ServiceDeleted($service, $deletedBy));
            $this->eventBus->fire(new ServiceVaultPurgeRequested($service));
        }
    }
}
