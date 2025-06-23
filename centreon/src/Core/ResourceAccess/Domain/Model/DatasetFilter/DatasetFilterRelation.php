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

namespace Core\ResourceAccess\Domain\Model\DatasetFilter;

final class DatasetFilterRelation
{
    /**
     * @param int $datasetFilterId
     * @param string $datasetFilterType
     * @param null|int $parentId
     * @param int $resourceAccessGroupId
     * @param int $aclGroupId
     * @param int[] $resourceIds
     */
    public function __construct(
        private readonly int $datasetFilterId,
        private readonly string $datasetFilterType,
        private readonly ?int $parentId,
        private readonly int $resourceAccessGroupId,
        private readonly int $aclGroupId,
        private readonly array $resourceIds
    ) {
    }

    public function getDatasetFilterId(): int
    {
        return $this->datasetFilterId;
    }

    public function getDatasetFilterType(): string
    {
        return $this->datasetFilterType;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getResourceAccessGroupId(): int
    {
        return $this->resourceAccessGroupId;
    }

    public function getAclGroupId(): int
    {
        return $this->aclGroupId;
    }

    /**
     * @return int[]
     */
    public function getResourceIds(): array
    {
        return $this->resourceIds;
    }
}