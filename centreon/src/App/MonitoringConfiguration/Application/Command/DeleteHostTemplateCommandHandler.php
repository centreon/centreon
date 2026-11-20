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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Event\HostTemplateDeleted;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DeleteHostTemplateCommandHandler
{
    public function __construct(
        private HostTemplateRepository $repository,
        private PollerRepository $pollerRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DeleteHostTemplateCommand $command): void
    {
        $hostTemplate = $this->repository->findOne($command->id);
        if (! $hostTemplate instanceof HostTemplate) {
            throw new HostTemplateNotFoundException([$command->id->value], 'id');
        }

        $hostTemplate->assertUnlocked();

        // Read before the deletion: the inheritance relations cascade with it.
        $pollerIds = $this->repository->findPollerIdsOfInheritingHosts($command->id);

        $this->repository->remove($hostTemplate);

        foreach ($pollerIds as $pollerId) {
            $this->pollerRepository->flagIdAsChanged($pollerId);
        }

        $this->eventBus->fire(new HostTemplateDeleted($hostTemplate, $command->actor->userId->value));
        $this->eventBus->fire(new HostTemplateVaultPurgeRequested($hostTemplate));
    }
}
