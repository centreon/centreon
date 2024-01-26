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

namespace Core\ResourceAccess\Infrastructure\Repository;

use Core\Infrastructure\Common\Repository\DbFactoryUtilitiesTrait;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;

/**
 * @phpstan-import-type _DatasetFilter from DbReadResourceAccessRepository
 */
class DbDatasetFilterFactory
{
    use DbFactoryUtilitiesTrait;

    /**
     * @param non-empty-array<_DatasetFilter> $record
     * @param DatasetFilterValidator $datasetValidator
     *
     * @throws \InvalidArgumentException
     *
     * @return DatasetFilter
     */
    public static function createFromRecord(array $record, DatasetFilterValidator $datasetValidator): DatasetFilter
    {
        $datasetFilter = null;

        $buildDatasetFilterWithHierarchy = function (
            array $data,
            ?int $parentId,
            ?DatasetFilter $parentDatasetFilter
        ) use (&$datasetFilter, &$buildDatasetFilterWithHierarchy, $datasetValidator): void {
            if ($datasetFilter === null) {
                /** @var non-empty-array<_DatasetFilter> $data */
                $rootData = self::findRootFilter($data);
                $datasetFilter = new DatasetFilter(
                    $rootData['dataset_filter_type'],
                    self::fromStringToArrayOfInts($rootData['dataset_filter_resources']),
                    $datasetValidator
                );
                $buildDatasetFilterWithHierarchy($data, $rootData['dataset_filter_id'], $datasetFilter);
            } elseif (
                $parentId !== null
                && $parentDatasetFilter !== null
            ) {
                /** @var non-empty-array<_DatasetFilter> $data */
                $childrenData = self::findSubFilter($parentId, $data);

                if ($childrenData !== []) {
                    $childrenFilter = new DatasetFilter(
                        $childrenData['dataset_filter_type'],
                        self::fromStringToArrayOfInts($childrenData['dataset_filter_resources']),
                        $datasetValidator
                    );
                    $parentDatasetFilter->setDatasetFilter($childrenFilter);

                    $buildDatasetFilterWithHierarchy($data, $childrenData['dataset_filter_id'], $parentDatasetFilter->getDatasetFilter());
                }
            }
        };

        $buildDatasetFilterWithHierarchy($record, null, null);

        /** @var DatasetFilter $datasetFilter */
        return $datasetFilter;
    }

    /**
     * @param int $parentId
     * @param non-empty-array<_DatasetFilter> $data
     *
     * @return _DatasetFilter|array{}
     */
    private static function findSubFilter(int $parentId, array $data): array
    {
        $subFilter = array_values(
            array_filter(
                $data,
                static fn (array $data): bool => $data['dataset_filter_parent_id'] === $parentId
            )
        );

        return $subFilter[0] ?? [];
    }

    /**
     * @param non-empty-array<_DatasetFilter> $filters
     *
     * @return _DatasetFilter
     */
    private static function findRootFilter(array $filters): array
    {
        $rootData = array_values(array_filter($filters, static fn (array $data): bool => $data['dataset_filter_parent_id'] === null));

        return $rootData[0];
    }
}
