<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Log\LoggerTrait;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\ResourceNamesById;

final class HostGroupProvider implements DatasetProviderInterface
{
    use LoggerTrait;

    /**
     * @param ReadHostGroupRepositoryInterface $repository
     */
    public function __construct(private readonly ReadHostGroupRepositoryInterface $repository)
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
        return HostGroupFilterType::TYPE_NAME === $type;
    }

    /**
     * @inheritDoc
     */
    public function areResourcesValid(array $resourceIds): array
    {
        return $this->repository->exist($resourceIds);
    }
}
