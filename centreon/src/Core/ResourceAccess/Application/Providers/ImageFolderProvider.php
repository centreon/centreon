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

namespace Core\ResourceAccess\Application\Providers;

use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ImageFolderFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\ResourceNamesById;

final class ImageFolderProvider implements DatasetProviderInterface
{
    public function __construct(
        private readonly ReadImageFolderRepositoryInterface $repository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isValidFor(string $type): bool
    {
        return $type === ImageFolderFilterType::TYPE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function areResourcesValid(array $resourceIds): array
    {
        return $this->repository->findExistingFolderIds($resourceIds);
    }

    /**
     * @inheritDoc
     */
    public function findResourceNamesByIds(array $ids): ResourceNamesById
    {
        return $this->repository->findFolderNames($ids);
    }
}
