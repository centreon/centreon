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

namespace Core\ResourceAccess\Application\Repository;

use Core\ResourceAccess\Domain\Model\DatasetFilter;
use Core\ResourceAccess\Domain\Model\NewRule;

interface WriteRuleRepositoryInterface
{
    /**
     * @param NewRule $rule
     *
     * @return int
     */
    public function add(NewRule $rule): int;

    /**
     * @param int $ruleId
     * @param int[] $contactIds
     */
    public function linkContactsToRule(int $ruleId, array $contactIds): void;

    /**
     * @param int $ruleId
     * @param int[] $contactGroupIds
     */
    public function linkContactGroupsToRule(int $ruleId, array $contactGroupIds): void;

    /**
     * @param int $datasetId
     * @param int[] $hostIds
     */
    public function linkHostsToDataset(int $datasetId, array $hostIds): void;

    /**
     * @param int $datasetId
     * @param int[] $hostgroupIds
     */
    public function linkHostgroupsToDataset(int $datasetId, array $hostgroupIds): void;

    /**
     * @param int $datasetId
     * @param int[] $hostCategoryIds
     */
    public function linkHostCategoriesToDataset(int $datasetId, array $hostCategoryIds): void;

    /**
     * @param int $datasetId
     * @param int[] $servicegroupIds
     */
    public function linkServicegroupsToDataset(int $datasetId, array $servicegroupIds): void;

    /**
     * @param int $datasetId
     * @param int[] $serviceCategoryIds
     */
    public function linkServiceCategoriesToDataset(int $datasetId, array $serviceCategoryIds): void;

    /**
     * @param int $datasetId
     * @param int[] $serviceIds
     * @param array $metaServiceIds
     */
    // public function linkServicesToDataset(int $datasetId, array $serviceIds): void;

    /**
     * @param int $datasetId
     * @param int[] $metaServiceIds
     */
    public function linkMetaServicesToDataset(int $datasetId, array $metaServiceIds): void;

    /**
     * @param string $name
     *
     * @return int
     */
    public function addDataset(string $name): int;

    /**
     * @param int $ruleId
     * @param int $datasetId
     */
    public function linkDatasetToRule(int $ruleId, int $datasetId): void;

    /**
     * @param int $ruleId
     * @param int $datasetId
     * @param DatasetFilter $filter
     * @param null|int $parentFilterId
     *
     * @return int
     */
    public function addDatasetFilter(int $ruleId, int $datasetId, DatasetFilter $filter, ?int $parentFilterId): int;
}

