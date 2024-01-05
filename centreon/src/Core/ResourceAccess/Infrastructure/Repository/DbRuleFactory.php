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
use Core\ResourceAccess\Domain\Model\Rule;

/**
 * @phpstan-import-type _DatasetFilter from DbReadRuleRepository
 * @phpstan-import-type _Rule from DbReadRuleRepository
 */
class DbRuleFactory
{
    use DbFactoryUtilitiesTrait;

    /**
     * @param _Rule $record
     * @param non-empty-array<_DatasetFilter> $datasetFiltersRecord
     *
     * @return Rule
     */
    public static function createFromRecord(array $record, array $datasetFiltersRecord): Rule
    {
        $datasets = [];

        // gather filters by dataset
        foreach ($datasetFiltersRecord as $datasetFilterRecord) {
            $datasets[$datasetFilterRecord['dataset_name']][] = $datasetFilterRecord;
        }

        // and order the datasets by name
        ksort($datasets);

        $datasetFilters = [];
        foreach ($datasets as $dataset) {
            $datasetFilters[] = DbDatasetFilterFactory::createFromRecord($dataset);
        }

        return new Rule(
            id: $record['id'],
            name: $record['name'],
            description: (string) $record['description'],
            linkedContacts: self::fromStringToArrayOfInts($record['contact_ids']),
            linkedContactGroups: self::fromStringToArrayOfInts($record['contact_group_ids']),
            datasets: $datasetFilters,
            isEnabled: (bool) $record['status']
        );
    }
}
