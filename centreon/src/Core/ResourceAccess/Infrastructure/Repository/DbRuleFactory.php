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
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\Rule;

/**
 * @phpstan-import-type _DatasetFilter from DbReadResourceAccessRepository
 * @phpstan-import-type _TinyRule from DbReadResourceAccessRepository
 */
class DbRuleFactory
{
    use DbFactoryUtilitiesTrait;

    /**
     * @param _TinyRule $record
     * @param int[] $linkedContactIds
     * @param int[] $linkedContactGroupIds
     * @param non-empty-array<_DatasetFilter> $datasetFiltersRecord
     * @param DatasetFilterValidator $datasetValidator
     *
     * @return Rule
     */
    public static function createFromRecord(
        array $record,
        array $linkedContactIds,
        array $linkedContactGroupIds,
        array $datasetFiltersRecord,
        DatasetFilterValidator $datasetValidator
    ): Rule {
        $datasets = [];

        // gather filters by dataset
        foreach ($datasetFiltersRecord as $datasetFilterRecord) {
            $datasets[$datasetFilterRecord['dataset_name']][] = $datasetFilterRecord;
        }

        // and order the datasets by name
        ksort($datasets);

        $datasetFilters = [];
        foreach ($datasets as $dataset) {
            $datasetFilters[] = DbDatasetFilterFactory::createFromRecord($dataset, $datasetValidator);
        }

        return new Rule(
            id: $record['id'],
            name: $record['name'],
            description: (string) $record['description'],
            applyToAllContacts: $record['all_contacts'] === 1,
            linkedContacts: $linkedContactIds,
            applyToAllContactGroups: $record['all_contact_groups'] === 1,
            linkedContactGroups: $linkedContactGroupIds,
            datasets: $datasetFilters,
            isEnabled: (bool) $record['is_enabled']
        );
    }
}
