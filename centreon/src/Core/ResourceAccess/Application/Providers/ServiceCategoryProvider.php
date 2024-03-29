<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ResourceAccess\Application\Providers;

use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\ResourceNamesById;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;

final class ServiceCategoryProvider implements DatasetProviderInterface
{
    /**
     * @param ReadServiceCategoryRepositoryInterface $repository
     */
    public function __construct(private readonly ReadServiceCategoryRepositoryInterface $repository)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function findResourceNamesByIds(array $ids): ResourceNamesById
    {
        $names = $this->repository->findNames($ids);

        return (new ResourceNamesById())->setNames($names->getNames());
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $type): bool
    {
        return ServiceCategoryFilterType::TYPE_NAME === $type;
    }

    /**
     * @inheritDoc
     */
    public function areResourcesValid(array $resourceIds): array
    {
        return $this->repository->exist($resourceIds);
    }
}
