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

namespace Core\ResourceAccess\Domain\Model\DatasetFilter;

class DatasetFilterValidator
{
    /** @var array<string, array<string>> */
    private array $hiearchy = [];

    /**
     * @param \Traversable<DatasetFilterTypeInterface> $datasetFilterTypes
     */
    public function __construct(\Traversable $datasetFilterTypes)
    {
        foreach (iterator_to_array($datasetFilterTypes) as $datasetFilterType) {
            $this->hiearchy[$datasetFilterType->getName()] = $datasetFilterType->getPossibleChildren();
        }

        if ([] === $this->hiearchy) {
            throw new \InvalidArgumentException('You must add at least one dataset filter type provider');
        }
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isDatasetFilterTypeValid(string $type): bool
    {
        return \in_array($type, array_keys($this->hiearchy), true);
    }

    /**
     * @param string $parentType
     * @param string $childType
     *
     * @return bool
     */
    public function isDatasetFilterHierarchyValid(string $parentType, string $childType): bool
    {
        return \in_array($childType, $this->hiearchy[$parentType], true);
    }
}
