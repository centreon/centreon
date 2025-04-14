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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterRelation;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\ResourceAccess\Domain\Model\TinyRule;

interface ReadResourceAccessRepositoryInterface
{
    /**
     * List all rules.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @return TinyRule[]
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * List all rules the user is linked to.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $userId
     *
     * @return TinyRule[]
     */
    public function findAllByRequestParametersAndUserId(RequestParametersInterface $requestParameters, int $userId): array;

    /**
     * Checks if the rule name provided as been already used for a rule.
     *
     * @param string $name
     *
     * @return bool
     */
    public function existsByName(string $name): bool;

    /**
     * @param int $ruleId
     *
     * @return null|Rule
     */
    public function findById(int $ruleId): ?Rule;

    /**
     * @param int $ruleId
     *
     * @return int[]
     */
    public function findDatasetIdsByRuleId(int $ruleId): array;

    /**
     * Checks if the rule identified by ruleId exists.
     *
     * @param int $ruleId
     *
     * @return bool
     */
    public function exists(int $ruleId): bool;

    /**
     * Retrieve Datasets by Host Group ID.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     *
     * @return array<int, array<int>> [datasetId => [ResourceId1,ResourceId2, ...]]
     */
    public function findDatasetResourceIdsByHostGroupId(int $hostGroupId): array;

    /**
     * Return the list of rule ids that exist from the given rule IDs.
     *
     * @param int[] $ruleIds
     * @return int[]
     */
    public function exist(array $ruleIds): array;

    /**
     * Check if a rule exists by contact.
     * Existing rules are linked to the user ID or has "all_contacts" flag.
     *
     * @param int[] $ruleIds
     * @param int $userId
     *
     * @return int[]
     */
    public function existByContact(array $ruleIds, int $userId): array;

    /**
     * Check if a rule exists by contact groups.
     * Existing rules are linked to the contact group IDs or has "all_contact_groups" flag.
     *
     * @param int[] $ruleIds
     * @param ContactGroup[] $contactGroups
     *
     * @return int[]
     */
    public function existByContactGroup(array $ruleIds, array $contactGroups): array;

    /**
     * Retrieve Datasets by Rule IDs and Dataset type where there is no parent dataset.
     *
     * @param int[] $ruleIds
     * @param string $type
     *
     * @throws \Throwable
     *
     * @return DatasetFilterRelation[]
     */
    public function findLastLevelDatasetFilterByRuleIdsAndType(array $ruleIds, string $type): array;

    /**
     * Check if rules exists by type and resource ID.
     *
     * @param string $type
     * @param int $resourceId
     * @return int[] array of Rule IDs
     */
    public function existByTypeAndResourceId(string $type, int $resourceId): array;

    /**
     * Retrieve rules by host group ID.
     *
     * @param string $type dataset filter type
     * @param int $resourceId
     *
     * @throws \Throwable
     *
     * @return TinyRule[]
     */
    public function findRuleByResourceId(string $type, int $resourceId): array;

    /**
     * Retrieve rules by resource ID and user ID for a specified type.
     * Exclude rules that have dataset with type "All resources" and with "all [type]" checked.
     *
     * @param string $type dataset filter type
     * @param int $resourceId
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return TinyRule[]
     */
    public function findRuleByResourceIdAndContactId(string $type, int $resourceId, int $userId): array;

    /**
     * Retrieve rules by resource ID and contact groups for a specified type.
     * Exclude rules that have dataset with type "All resources" and with "all [type]" checked.
     *
     * @param string $type dataset filter type
     * @param ContactGroup[] $contactGroups
     * @param int $resourceId
     *
     * @throws \Throwable
     *
     * @return TinyRule[]
     */
    public function findRuleByResourceIdAndContactGroups(string $type, int $resourceId, array $contactGroups): array;
}
