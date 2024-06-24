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

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;

class DatasetFilter
{
    public const ERROR_NOT_A_DATASET_FILTER_TYPE = 1;
    public const ERROR_DATASET_FILTER_HIERARCHY_NOT_VALID = 2;

    private ?self $datasetFilter = null;

    /**
     * @param string $type
     * @param int[] $resourceIds
     * @param DatasetFilterValidator $validator
     *
     * @throws AssertionFailedException
     * @throws AssertionException
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $type,
        private readonly array $resourceIds,
        private readonly DatasetFilterValidator $validator
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::notEmptyString($type, "{$shortName}::type");
        $this->assertTypeIsValid($type);

        // if it can be empty it does not mean that it is empty.
        // So we need to check if not empty that we do receive an array of ints.
        if ($this->validator->canResourceIdsBeEmpty($type)) {
            if ($resourceIds !== []) {
                Assertion::arrayOfTypeOrNull('int', $this->resourceIds, "{$shortName}::resourceIds");
            }
        } else {
            Assertion::notEmpty($this->resourceIds, "{$shortName}::resourceIds");
            Assertion::arrayOfTypeOrNull('int', $this->resourceIds, "{$shortName}::resourceIds");
        }
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int[]
     */
    public function getResourceIds(): array
    {
        return $this->resourceIds;
    }

    /**
     * @param DatasetFilter|null $datasetFilter
     *
     * @throws \InvalidArgumentException
     */
    public function setDatasetFilter(?self $datasetFilter): void
    {
        if ($datasetFilter !== null) {
            $this->assertDatasetFilterHierarchyIsValid($datasetFilter->getType());
        }
        $this->datasetFilter = $datasetFilter;
    }

    /**
     * @return null|DatasetFilter
     */
    public function getDatasetFilter(): ?self
    {
        return $this->datasetFilter;
    }

    /**
     * @param DatasetFilter $filter
     *
     * @return array{parent: DatasetFilter|null, last: DatasetFilter}
     */
    public static function findApplicableFilters(self $filter): array
    {
        $lastLevelFilter = null;
        $parentLastLevelFilter = null;

        /**
         * Recursive method to find the last 'stage' of filters (descending filters) that should be applied
         * and its parent.
         * Uses $findApplicableFilters for recursivity, lastLevelFilter and parentLastLevelFilter
         * which are DatasetFilters entities (or null for parent).
         */
        $findApplicableFilters = function (DatasetFilter $filter) use (&$findApplicableFilters, &$lastLevelFilter, &$parentLastLevelFilter): array {
            // initialize the $applicableFilter which is initially NULL
            if ($lastLevelFilter === null) {
                $lastLevelFilter = $filter;
                $findApplicableFilters($filter);
            }
            // if there is a level then keep digging
            elseif ($filter->getDatasetFilter() !== null) {
                $parentLastLevelFilter = $filter;
                $lastLevelFilter = $filter->getDatasetFilter();
                $findApplicableFilters($lastLevelFilter);
            }

            return ['parent' => $parentLastLevelFilter, 'last' => $lastLevelFilter];
        };

        return $findApplicableFilters($filter);
    }

    /**
     * @param DatasetFilter $filter
     *
     * @return bool
     */
    public static function isGroupOrCategoryFilter(self $filter): bool
    {
        return in_array(
            $filter->getType(),
            [
                HostGroupFilterType::TYPE_NAME,
                HostCategoryFilterType::TYPE_NAME,
                ServiceCategoryFilterType::TYPE_NAME,
                ServiceGroupFilterType::TYPE_NAME,
            ],
            true
        );
    }

    /**
     * @param string $type
     *
     * @throws \InvalidArgumentException
     */
    private function assertTypeIsValid(string $type): void
    {
        if (! $this->validator->isDatasetFilterTypeValid($type)) {
            $message = sprintf('Value provided is not supported for dataset filter type (was: %s)', $type);

            throw new \InvalidArgumentException($message, self::ERROR_NOT_A_DATASET_FILTER_TYPE);
        }
    }

    /**
     * @param string $childType
     *
     * @throws \InvalidArgumentException
     */
    private function assertDatasetFilterHierarchyIsValid(string $childType): void
    {
        if (! $this->validator->isDatasetFilterHierarchyValid($this->type, $childType)) {
            $message = sprintf('Dataset filter hierarchy assertion failed (%s not a sub-filter of %s)', $childType, $this->type);

            throw new \InvalidArgumentException($message, self::ERROR_DATASET_FILTER_HIERARCHY_NOT_VALID);
        }
    }
}
