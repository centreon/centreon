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

namespace Core\ResourceAccess\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;

class DatasetFilter
{
    /** @var array<string, DatasetFilterType[]> */
    private array $datasetFilterHierarchy = [];

    private ?self $datasetFilter = null;

    /**
     * @param string $type
     * @param int[] $resourceIds
     *
     * @throws AssertionFailedException
     * @throws AssertionException
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $type,
        private readonly array $resourceIds,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::notEmptyString($type, "{$shortName}::type");

        // try to convert string type to Enum to validate that type is supported
        DatasetFilterTypeConverter::fromString($type);

        Assertion::notEmpty($this->resourceIds, "{$shortName}::resourceIds");
        Assertion::arrayOfTypeOrNull('int', $this->resourceIds, "{$shortName}::resourceIds");
        $this->createDatasetFilterHierarchy();
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
            $this->assertHierarchyIsValid($datasetFilter);
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

    private function createDatasetFilterHierarchy(): void
    {
        $this->datasetFilterHierarchy = [
            DatasetFilterTypeConverter::toString(DatasetFilterType::Host) => [
                DatasetFilterType::Servicegroup,
                DatasetFilterType::ServiceCategory,
                DatasetFilterType::Service,
            ],
            DatasetFilterTypeConverter::toString(DatasetFilterType::Hostgroup) => [
                DatasetFilterType::Host,
                DatasetFilterType::HostCategory,
                DatasetFilterType::Servicegroup,
                DatasetFilterType::ServiceCategory,
                DatasetFilterType::Service,
            ],
            DatasetFilterTypeConverter::toString(DatasetFilterType::HostCategory) => [
                DatasetFilterType::Hostgroup,
                DatasetFilterType::Host,
                DatasetFilterType::Servicegroup,
                DatasetFilterType::ServiceCategory,
                DatasetFilterType::Service,
            ],
            DatasetFilterTypeConverter::toString(DatasetFilterType::Servicegroup) => [
                DatasetFilterType::ServiceCategory,
                DatasetFilterType::Service,
            ],
            DatasetFilterTypeConverter::toString(DatasetFilterType::ServiceCategory) => [
                DatasetFilterType::Service,
                DatasetFilterType::Servicegroup,
            ],
            DatasetFilterTypeConverter::toString(DatasetFilterType::MetaService) => [],
            DatasetFilterTypeConverter::toString(DatasetFilterType::Service) => [],
        ];
    }

    /**
     * @param DatasetFilter $dataFilter
     */
    private function assertHierarchyIsValid(self $dataFilter): void
    {
        $possibleFilterTypeAsChild = $this->datasetFilterHierarchy[$this->type];

        if ($possibleFilterTypeAsChild === []) {
            throw new \InvalidArgumentException(sprintf('%s filter type cannot have sub-filter set', $this->type));
        }

        // assert that the type of the dataFilter provided is a child of the current dataFilter
        if (
            ! in_array(
                DatasetFilterTypeConverter::fromString($dataFilter->getType()),
                $possibleFilterTypeAsChild,
                true
            )
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Dataset filter hierarchy assertion failed (%s not a sub-filter of %s)',
                    $dataFilter->getType(),
                    $this->type
                )
            );
        }
    }
}
