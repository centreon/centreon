<?php

declare(strict_types=1);

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
 */

namespace App\ResourceConfiguration\Application\Command;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Exception\ServiceCategoryAlreadyExistException;
use App\ResourceConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\Shared\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class CreateServiceCateogoryCommandHandler
{
    public function __construct(
        private ServiceCategoryRepository $repository,
    ) {
    }

    public function __invoke(CreateServiceCateogoryCommand $command): ServiceCategory
    {
        $serviceCategory = new ServiceCategory(
            name: $command->name,
            alias: $command->alias,
            activated: $command->activated,
        );

        if ($this->repository->findOneByName($serviceCategory->name())) {
            throw new ServiceCategoryAlreadyExistException(['name' => $serviceCategory->name()->value]);
        }

        $this->repository->add($serviceCategory);

        return $serviceCategory;
    }
}
