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

use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\ResourceAccess\Domain\Model\Rule;

interface WriteResourceAccessRepositoryInterface
{
    /**
     * @param NewRule $rule
     *
     * @return int
     */
    public function add(NewRule $rule): int;

    /**
     * @param Rule $rule
     */
    public function update(Rule $rule): void;

    /**
     * @param int $ruleId
     * @param int[] $contactIds
     */
    public function linkContactsToRule(int $ruleId, array $contactIds): void;

    /**
     * @param int $ruleId
     */
    public function deleteContactRuleRelations(int $ruleId): void;

    /**
     * @param int $ruleId
     * @param int[] $contactGroupIds
     */
    public function linkContactGroupsToRule(int $ruleId, array $contactGroupIds): void;

    /**
     * @param int $ruleId
     */
    public function deleteContactGroupRuleRelations(int $ruleId): void;

    /**
     * @param string $name
     * @param bool $accessAllHosts
     * @param bool $accessAllHostGroups
     * @param bool $accessAllServiceGroups
     *
     * @return int
     */
    public function addDataset(
        string $name,
        bool $accessAllHosts,
        bool $accessAllHostGroups,
        bool $accessAllServiceGroups
    ): int;

    /**
     * @param int $ruleId
     * @param int $datasetId
     * @param string $resourceType (possible values: hostgroups, servicegroups, hosts)
     * @param bool $fullAccess
     */
    public function updateDatasetAccess(int $ruleId, int $datasetId, string $resourceType, bool $fullAccess): void;

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

    /**
     * @param int $datasetId
     * @param int[] $resourceIds
     * @param string $resourceType
     * @param int $ruleId
     */
    public function linkResourcesToDataset(int $ruleId, int $datasetId, string $resourceType, array $resourceIds): void;

    /**
     * @param int $ruleId
     */
    public function deleteRuleAndDatasets(int $ruleId): void;

    /**
     * @param int[] $ids
     */
    public function deleteDatasets(array $ids): void;
}

