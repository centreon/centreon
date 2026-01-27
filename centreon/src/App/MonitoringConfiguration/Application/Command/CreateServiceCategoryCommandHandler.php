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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Event\ServiceCategoryCreated;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreateServiceCategoryCommandHandler
{
    public function __construct(
        private ServiceCategoryRepository $repository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateServiceCategoryCommand $command): ServiceCategory
    {
        $serviceCategory = new ServiceCategory(
            id: null,
            name: $command->name,
            alias: $command->alias,
            activated: $command->activated,
        );

        if ($this->repository->findOneByName($serviceCategory->name) instanceof ServiceCategory) {
            throw new ServiceCategoryAlreadyExistsException(['name' => $serviceCategory->name->value]);
        }

        $this->repository->add($serviceCategory);

        // Ensure the repository assigned an ID
        $serviceCategory->id();

        $this->eventBus->fire(new ServiceCategoryCreated($serviceCategory, $command->creatorId));

        return $serviceCategory;
    }
}
