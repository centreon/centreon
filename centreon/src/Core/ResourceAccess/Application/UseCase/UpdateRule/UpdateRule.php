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

namespace Core\ResourceAccess\Application\UseCase\UpdateRule;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class UpdateRule
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadResourceAccessRepositoryInterface $readRepository
     * @param WriteResourceAccessRepositoryInterface $writeRepository
     * @param UpdateRuleValidation $validator
     * @param DatasetFilterValidator $datasetValidator
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $readRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeRepository,
        private readonly UpdateRuleValidation $validator,
        private readonly DatasetFilterValidator $datasetValidator,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param UpdateRuleRequest $request
     * @param UpdateRulePresenterInterface $presenter
     */
    public function __invoke(UpdateRuleRequest $request, UpdateRulePresenterInterface $presenter): void
    {
        if (! $this->isAuthorized()) {
            $this->error(
                "User doesn't have sufficient rights to update a resource access rule",
                [
                    'user_id' => $this->user->getId(),
                ]
            );
            $presenter->presentResponse(
                new ForbiddenResponse(RuleException::notAllowed()->getMessage())
            );

            return;
        }

        try {
            $this->info('Start resource access rule update process');
            $this->debug('Find resource access rule to update', ['id' => $request->id]);
            $rule = $this->readRepository->findById($request->id);

            if ($rule === null) {
                $presenter->presentResponse(new NotFoundResponse('Resource access rule'));

                return;
            }

            $this->updateInTransaction($rule, $request);
            $presenter->presentResponse(new NoContentResponse());
        } catch (AssertionFailedException|\ValueError $exception) {
            $presenter->presentResponse(new InvalidArgumentResponse($exception));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        } catch (RuleException $exception) {
            $presenter->presentResponse(
                match ($exception->getCode()) {
                    RuleException::CODE_CONFLICT => new ConflictResponse($exception),
                    default => new ErrorResponse($exception),
                }
            );
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        } catch (\Throwable $exception) {
            $presenter->presentResponse(
                new ErrorResponse(RuleException::updateRule())
            );
            $this->error((string) $exception);
        }
    }

    /**
     * @param Rule $rule
     * @param UpdateRuleRequest $request
     *
     * @throws \Throwable
     */
    private function updateInTransaction(Rule $rule, UpdateRuleRequest $request): void
    {
        try {
            $this->debug('Starting resource access rule update transaction process');
            $this->dataStorageEngine->startTransaction();
            $this->updateBasicInformation($rule, $request);

            // At least one ID must be provided for contact or contactgroup
            $this->validator->assertContactsAndContactGroupsAreNotEmpty(
                $request->contactIds,
                $request->contactGroupIds,
                $request->applyToAllContacts,
                $request->applyToAllContactGroups
            );

            $this->updateLinkedContacts($rule, $request);
            $this->updateLinkedContactGroups($rule, $request);
            $this->updateResourceLinks($request);

            $this->debug('Commit resource access rule update transaction process');
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $exception) {
            $this->error("Rollback of 'Update resource access rule' transaction");
            $this->dataStorageEngine->rollbackTransaction();

            throw $exception;
        }
    }

    /**
     * This method will ensure that the ids provided for the dataset filters are valid
     * and that the structure of the dataset is correct regarding the hierarchy defined in the DatasetFilter entity.
     *
     * @param UpdateRuleRequest $request
     *
     * @throws RuleException
     * @throws \InvalidArgumentException
     *
     * @return DatasetFilter[]
     */
    private function validateAndCreateDatasetFiltersFromRequest(UpdateRuleRequest $request): array
    {
        $datasetFilters = [];

        $validateAndBuildDatasetFilter = function (
            array $data,
            ?DatasetFilter $parentDatasetFilter
        ) use (&$validateAndBuildDatasetFilter, &$datasetFilter): void {
            /**
             * In any case we want to make sure that
             *     - resources provided are valid (exist) if not in case of all, all_servicegroups, all_hostgroups, all_hosts
             *     identified by the fact that $data['resources'] is empty for those types
             *     - the datasetfilter type provided is valid (validated by entity)
             *     - that the dataset filter hierarchy is valid (validated by entity).
             */
            if ($data['resources'] !== []) {
                $this->validator->assertIdsAreValid($data['type'], $data['resources']);
            }

            // first iteration we want to create the root filter
            if ($datasetFilter === null) {
                $datasetFilter = new DatasetFilter(
                    type: $data['type'],
                    resourceIds: $data['resources'],
                    validator: $this->datasetValidator
                );
                if ($data['dataset_filter'] !== null) {
                    $validateAndBuildDatasetFilter($data['dataset_filter'], null);
                }
            } else {
                // we want to create the first children
                if ($parentDatasetFilter === null) {
                    $filter = new DatasetFilter(
                        type: $data['type'],
                        resourceIds: $data['resources'],
                        validator: $this->datasetValidator
                    );
                    $datasetFilter->setDatasetFilter($filter);
                    if ($data['dataset_filter'] !== null) {
                        $validateAndBuildDatasetFilter($data['dataset_filter'], $datasetFilter->getDatasetFilter());
                    }
                } else {
                    $childrenDatasetFilter = new DatasetFilter(
                        type: $data['type'],
                        resourceIds: $data['resources'],
                        validator: $this->datasetValidator
                    );

                    $parentDatasetFilter->setDatasetFilter($childrenDatasetFilter);

                    if ($data['dataset_filter'] !== null) {
                        $validateAndBuildDatasetFilter($data['dataset_filter'], $childrenDatasetFilter);
                    }
                }
            }
        };

        foreach ($request->datasetFilters as $dataset) {
            $datasetFilter = null;
            $validateAndBuildDatasetFilter($dataset, $datasetFilter);

            /** @var DatasetFilter $datasetFilter */
            $datasetFilters[] = $datasetFilter;
        }

        return $datasetFilters;
    }

    /**
     * @param int $ruleId
     * @param int $datasetId
     * @param DatasetFilter $filter
     */
    private function saveDatasetFiltersHierarchy(int $ruleId, int $datasetId, DatasetFilter $filter): void
    {
        $parentFilterId = null;

        $saveDatasetFiltersHierarchy = function (
            int $ruleId,
            int $datasetId,
            DatasetFilter $filter
        ) use (&$parentFilterId, &$saveDatasetFiltersHierarchy): void {
            // First iteration we save the root filter
            $this->debug(
                'Add dataset filter',
                [
                    'type' => $filter->getType(),
                    'resource_ids' => $filter->getResourceIds(),
                ]
            );
            $parentFilterId = $this->writeRepository->addDatasetFilter($ruleId, $datasetId, $filter, $parentFilterId);

            // if there is a next level then save next level until final level reached
            if ($filter->getDatasetFilter() !== null) {
                $saveDatasetFiltersHierarchy($ruleId, $datasetId, $filter->getDatasetFilter());
            }
        };

        $saveDatasetFiltersHierarchy($ruleId, $datasetId, $filter);
    }

    /**
     * @param UpdateRuleRequest $updateRequest
     */
    private function updateResourceLinks(UpdateRuleRequest $updateRequest): void
    {
        // validate the updated datasets sent before doing anything
        $this->debug('Validating updated datasets', $updateRequest->datasetFilters);
        $datasetFilters = $this->validateAndCreateDatasetFiltersFromRequest($updateRequest);

        /*
         * At this point we've made sure that the updated dataset filters are valid. Update can start...
         * Update will consist in a delete / add actions (replace)
         */
        $this->debug('Find datasets linked to the resource access rule', ['id' => $updateRequest->id]);
        $datasetIds = $this->readRepository->findDatasetIdsByRuleId($updateRequest->id);

        /* Deleting datasets found. Constraint on the database tables will automatically
         * - delete the dataset_filters associated to the dataset
         * - delete the relations between datasets and the rule
         */
        $this->debug('Deleting datasets linked to the resource access rule', ['id' => $updateRequest->id]);
        $this->writeRepository->deleteDatasets($datasetIds);

        $index = 0;

        $this->debug('Creating new datasets linked to resource access rule', ['id' => $updateRequest->id]);
        foreach ($datasetFilters as $datasetFilter) {
            // create formatted name for dataset
            $datasetName = 'dataset_for_rule_' . $updateRequest->id . '_' . $index;

            if ($datasetFilter->getType() === DatasetFilterValidator::ALL_RESOURCES_FILTER) {
                $this->createFullAccessDatasetFilter(
                    ruleId: $updateRequest->id,
                    datasetName: $datasetName,
                    datasetFilter: $datasetFilter
                );
            } else {
                // create dataset
                $datasetId = $this->writeRepository->addDataset(
                    name: $datasetName,
                    accessAllHosts: false,
                    accessAllHostGroups: false,
                    accessAllServiceGroups: false
                );

                // And link it to the rule
                $this->writeRepository->linkDatasetToRule(ruleId: $updateRequest->id, datasetId: $datasetId);

                // dedicated table used in order to keep filters hierarchy for GET matters
                $this->saveDatasetFiltersHierarchy(ruleId: $updateRequest->id, datasetId: $datasetId, filter: $datasetFilter);

                // Extract from the DatasetFilter the final filter level and its parent.
                [
                    'parent' => $parentApplicableFilter,
                    'last' => $applicableFilter
                ] = DatasetFilter::findApplicableFilters($datasetFilter);

                /* Specific behaviour when the last level of filtering is of type
                 * *Category|*Group and that the parent of this filter is also of the same type.
                 * Then we need to save both types as those are on the same hierarchy level.
                 *
                 * Important also to mention a specific behaviour
                 * When the type matches hostgroup / servicegroup or host and that no
                 * resource IDs were provided it means 'all_type'. The specific behaviour describe
                 * above also applies.
                 */
                if ($parentApplicableFilter !== null) {
                    if ($this->shouldBothFiltersBeSaved($parentApplicableFilter, $applicableFilter)) {
                        if ($this->shouldUpdateDatasetAccesses($parentApplicableFilter)) {
                            $this->writeRepository->updateDatasetAccess(
                                ruleId: $updateRequest->id,
                                datasetId: $datasetId,
                                resourceType: $parentApplicableFilter->getType(),
                                fullAccess: true
                            );
                        } else {
                            $this->writeRepository->linkResourcesToDataset(
                                ruleId: $updateRequest->id,
                                datasetId: $datasetId,
                                resourceType: $parentApplicableFilter->getType(),
                                resourceIds: $parentApplicableFilter->getResourceIds()
                            );
                        }
                    }
                }

                if ($this->shouldUpdateDatasetAccesses($applicableFilter)) {
                    $this->writeRepository->updateDatasetAccess(
                        ruleId: $updateRequest->id,
                        datasetId: $datasetId,
                        resourceType: $applicableFilter->getType(),
                        fullAccess: true
                    );
                } else {
                    $this->writeRepository->linkResourcesToDataset(
                        ruleId: $updateRequest->id,
                        datasetId: $datasetId,
                        resourceType: $applicableFilter->getType(),
                        resourceIds: $applicableFilter->getResourceIds()
                    );
                }
            }

            $index++;
        }
    }

    /**
     * @param DatasetFilter $parent
     * @param DatasetFilter $child
     */
    private function shouldBothFiltersBeSaved(DatasetFilter $parent, DatasetFilter $child): bool
    {
        return DatasetFilter::isGroupOrCategoryFilter($child)
            && DatasetFilter::isGroupOrCategoryFilter($parent);
    }

    /**
     * @param DatasetFilter $datasetFilter
     *
     * @return bool
     */
    private function shouldUpdateDatasetAccesses(DatasetFilter $datasetFilter): bool
    {
        return $datasetFilter->getResourceIds() === []
            && $this->datasetValidator->canResourceIdsBeEmpty($datasetFilter->getType());
    }

    /**
     * @param int $ruleId
     * @param string $datasetName
     * @param DatasetFilter $datasetFilter
     */
    private function createFullAccessDatasetFilter(int $ruleId, string $datasetName, DatasetFilter $datasetFilter): void
    {
        $datasetId = $this->writeRepository->addDataset(
            name: $datasetName,
            accessAllHosts: true,
            accessAllHostGroups: true,
            accessAllServiceGroups: true
        );

        // And link it to the rule
        $this->writeRepository->linkDatasetToRule($ruleId, $datasetId);

        // dedicated table used in order to keep filters hierarchy for GET matters
        $this->saveDatasetFiltersHierarchy($ruleId, $datasetId, $datasetFilter);
    }

    /**
     * @param Rule $rule
     * @param UpdateRuleRequest $updateRequest
     *
     * @throws RuleException
     */
    private function updateLinkedContactGroups(Rule $rule, UpdateRuleRequest $updateRequest): void
    {
        /**
         * Do not do uneccessary database calls if nothing has changed
         * if all contact groups are linked to this rule.
         */
        if (
            $this->shouldUpdateContactOrContactGroupRelations(
                $rule->getLinkedContactGroupIds(),
                $updateRequest->contactGroupIds
            )
            && ! $updateRequest->applyToAllContactGroups
        ) {
            $this->validator->assertContactGroupIdsAreValid($updateRequest->contactGroupIds);

            $this->debug(
                'Deleting contact groups - resource access rule relations',
                ['id' => $updateRequest->id, 'contact_group_ids' => $rule->getLinkedContactGroupIds()]
            );
            $this->writeRepository->deleteContactGroupRuleRelations($updateRequest->id);

            $this->debug(
                'Creating contact groups - resource access rule relations',
                ['id' => $updateRequest->id, 'contact_group_ids' => $updateRequest->contactGroupIds]
            );
            $this->writeRepository->linkContactGroupsToRule($updateRequest->id, $updateRequest->contactGroupIds);
        }
    }

    /**
     * @param Rule $rule
     * @param UpdateRuleRequest $updateRequest
     *
     * @throws RuleException
     */
    private function updateLinkedContacts(Rule $rule, UpdateRuleRequest $updateRequest): void
    {
        /**
         * Do not do uneccessary database calls if nothing has changed
         * if all contacts are linked to this rule.
         */
        if (
            $this->shouldUpdateContactOrContactGroupRelations(
                $rule->getLinkedContactIds(),
                $updateRequest->contactIds
            )
            && ! $updateRequest->applyToAllContacts
        ) {
            $this->validator->assertContactIdsAreValid($updateRequest->contactIds);

            $this->debug(
                'Deleting contacts - resource access rule relations',
                ['id' => $updateRequest->id, 'contact_ids' => $rule->getLinkedContactIds()]
            );
            $this->writeRepository->deleteContactRuleRelations($updateRequest->id);

            $this->debug(
                'Creating contacts - resource access rule relations',
                ['id' => $updateRequest->id, 'contact_ids' => $updateRequest->contactIds]
            );
            $this->writeRepository->linkContactsToRule($updateRequest->id, $updateRequest->contactIds);
        }
    }

    /**
     * @param Rule $rule
     * @param UpdateRuleRequest $updateRequest
     *
     * @throws RuleException
     * @throws AssertionFailedException
     */
    private function updateBasicInformation(Rule $rule, UpdateRuleRequest $updateRequest): void
    {
        // Do not do uneccessary database calls if nothing has changed
        if ($this->shouldUpdateBasicInformation($rule, $updateRequest)) {
            if ($rule->getName() !== NewRule::formatName($updateRequest->name)) {
                $this->validator->assertIsValidName($updateRequest->name);
                $rule->setName($updateRequest->name);
            }

            $rule->setIsEnabled($updateRequest->isEnabled);
            $rule->setDescription($updateRequest->description);
            $rule->setApplyToAllContacts($updateRequest->applyToAllContacts);
            $rule->setApplyToAllContactGroups($updateRequest->applyToAllContactGroups);

            $this->debug(
                'Updating basic resource access rule information',
                [
                    'id' => $updateRequest->id,
                    'name' => $rule->getName(),
                    'description' => $rule->getDescription() ?? '',
                    'is_enabled' => $rule->isEnabled(),
                    'all_contacts' => $rule->doesApplyToAllContacts(),
                    'all_contact_groups' => $rule->doesApplyToAllContactGroups(),
                ]
            );
            $this->writeRepository->update($rule);
        }
    }

    /**
     * @param int[] $current
     * @param int[] $update
     *
     * @return bool
     */
    private function shouldUpdateContactOrContactGroupRelations(array $current, array $update): bool
    {
        sort($current);
        sort($update);

        return $current !== $update;
    }

    /**
     * @param Rule $current
     * @param UpdateRuleRequest $updateRequest
     *
     * @return bool
     */
    private function shouldUpdateBasicInformation(Rule $current, UpdateRuleRequest $updateRequest): bool
    {
        if ($current->getName() !== NewRule::formatName($updateRequest->name)) {
            return true;
        }
        if ($current->getDescription() !== $updateRequest->description) {
            return true;
        }
        if ($current->isEnabled() !== $updateRequest->isEnabled) {
            return true;
        }
        if ($current->doesApplyToAllContactGroups() !== $updateRequest->applyToAllContactGroups) {
            return true;
        }
        return $current->doesApplyToAllContacts() !== $updateRequest->applyToAllContacts;
    }

    /**
     * Check if current user is authorized to perform the action.
     * Only users linked to AUTHORIZED_ACL_GROUPS acl_group and having access in Read/Write rights on the page
     * are authorized to add a Resource Access Rule.
     *
     * @return bool
     */
    private function isAuthorized(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW)
            && $this->isCloudPlatform;
    }
}
