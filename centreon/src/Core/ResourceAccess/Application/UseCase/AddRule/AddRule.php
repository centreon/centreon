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

namespace Core\ResourceAccess\Application\UseCase\AddRule;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;
use Core\ResourceAccess\Domain\Model\NewRule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class AddRule
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ReadResourceAccessRepositoryInterface $readRepository
     * @param WriteResourceAccessRepositoryInterface $writeRepository
     * @param ContactInterface $user
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param AddRuleValidation $validator
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param DatasetFilterValidator $datasetValidator
     */
    public function __construct(
        private readonly ReadResourceAccessRepositoryInterface $readRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeRepository,
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly AddRuleValidation $validator,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly DatasetFilterValidator $datasetValidator
    ) {
    }

    /**
     * @param AddRuleRequest $request
     * @param AddRulePresenterInterface $presenter
     */
    public function __invoke(
        AddRuleRequest $request,
        AddRulePresenterInterface $presenter
    ): void {
        try {
            if (! $this->isAuthorized()) {
                $this->error(
                    "User doesn't have sufficient rights to create a resource access rule",
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
                $this->info('Starting resource access rule creation process');
                $this->debug('Starting resource access rule transaction');
                $this->dataStorageEngine->startTransaction();

                /**
                 * Validate that data provided for name if valid (not already used)
                 * Validate that ids provided for contact and contactgroups are valid (exist).
                 */
                $this->validator->assertIsValidName($request->name);

                // At least one ID must be provided for contact or contactgroup
                $this->validator->assertContactsAndContactGroupsAreNotEmpty(
                    $request->contactIds,
                    $request->contactGroupIds,
                    $request->applyToAllContacts,
                    $request->applyToAllContactGroups
                );

                /**
                 * Contact and ContactGroup IDs need validation only if IDs are provided and that all property is not
                 * set to true.
                 */
                if (
                    ! $request->applyToAllContacts
                    && $request->contactIds !== []
                ) {
                    $this->validator->assertContactIdsAreValid($request->contactIds);
                }

                if (
                    ! $request->applyToAllContactGroups
                    && $request->contactGroupIds !== []
                ) {
                    $this->validator->assertContactGroupIdsAreValid($request->contactGroupIds);
                }

                $datasetFilters = $this->validateAndCreateDatasetFiltersFromRequest($request);

                $rule = $this->createRuleFromRequest($request, $datasetFilters);

                $ruleId = $this->addRule($rule);

                // add relations
                $this->linkContacts($ruleId, $rule);
                $this->linkContactGroups($ruleId, $rule);
                $this->linkResources($ruleId, $rule);

                $this->info('New resource access rule created', ['id' => $ruleId]);
                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $exception) {
                $this->error("Rollback of 'Add Resource Access Rule' transaction");
                $this->dataStorageEngine->rollbackTransaction();

                throw $exception;
            }
            $presenter->presentResponse($this->createResponse($ruleId));
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
                new ErrorResponse(RuleException::addRule())
            );
            $this->error((string) $exception);
        }
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
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW);
    }

    /**
     * @param int $ruleId
     * @param int $datasetId
     * @param DatasetFilter $filter
     */
    private function saveDatasetFiltersHierarchy(int $ruleId, int $datasetId, DatasetFilter $filter): void
    {
        $parentFilterId = null;

        $saveDatasetFiltersHierarchy = function (int $ruleId, int $datasetId, DatasetFilter $filter) use (&$parentFilterId, &$saveDatasetFiltersHierarchy): void {
            // First iteration we save the root filter
            $parentFilterId = $this->writeRepository->addDatasetFilter($ruleId, $datasetId, $filter, $parentFilterId);

            // if there is a next level then save next level until final level reached
            if ($filter->getDatasetFilter() !== null) {
                $saveDatasetFiltersHierarchy($ruleId, $datasetId, $filter->getDatasetFilter());
            }
        };

        $saveDatasetFiltersHierarchy($ruleId, $datasetId, $filter);
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
     * @param int $ruleId
     * @param NewRule $rule
     *
     * @throws \InvalidArgumentException
     */
    private function linkResources(int $ruleId, NewRule $rule): void
    {
        $index = 0;

        foreach ($rule->getDatasetFilters() as $datasetFilter) {
            // create formatted name for dataset
            $datasetName = 'dataset_for_rule_' . $ruleId . '_' . $index;

            if ($datasetFilter->getType() === DatasetFilterValidator::ALL_RESOURCES_FILTER) {
                $this->createFullAccessDatasetFilter(
                    ruleId: $ruleId,
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
                $this->writeRepository->linkDatasetToRule(ruleId: $ruleId, datasetId: $datasetId);

                // dedicated table used in order to keep filters hierarchy for GET matters
                $this->saveDatasetFiltersHierarchy(ruleId: $ruleId, datasetId: $datasetId, filter: $datasetFilter);

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
                            $this->updateDatasetAccesses(
                                datasetId: $datasetId,
                                resourceType: $parentApplicableFilter->getType()
                            );
                        } else {
                            $this->writeRepository->linkResourcesToDataset(
                                ruleId: $ruleId,
                                datasetId: $datasetId,
                                resourceType: $parentApplicableFilter->getType(),
                                resourceIds: $parentApplicableFilter->getResourceIds()
                            );
                        }
                    }
                }

                if ($this->shouldUpdateDatasetAccesses($applicableFilter)) {
                    $this->updateDatasetAccesses(
                        datasetId: $datasetId,
                        resourceType: $applicableFilter->getType()
                    );
                } else {
                    $this->writeRepository->linkResourcesToDataset(
                        ruleId: $ruleId,
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
     * @param DatasetFilter $datasetFilter
     *
     * @return bool
     */
    private function shouldUpdateDatasetAccesses(DatasetFilter $datasetFilter): bool
    {
        return $datasetFilter->getResourceIds() === []
            && in_array(
                $datasetFilter->getType(),
                [
                    HostGroupFilterType::TYPE_NAME,
                    ServiceGroupFilterType::TYPE_NAME,
                    HostFilterType::TYPE_NAME,
                ], true
            );
    }

    /**
     * @param int $datasetId
     * @param string $resourceType
     */
    private function updateDatasetAccesses(int $datasetId, string $resourceType): void
    {
        switch ($resourceType) {
            case HostGroupFilterType::TYPE_NAME:
                $this->writeRepository->updateDatasetAccess(
                    datasetId: $datasetId,
                    resourceType: 'hostgroups',
                    fullAccess: true
                );
                break;
            case ServiceGroupFilterType::TYPE_NAME:
                $this->writeRepository->updateDatasetAccess(
                    datasetId: $datasetId,
                    resourceType: 'servicegroups',
                    fullAccess: true
                );
                break;
            case HostFilterType::TYPE_NAME:
                $this->writeRepository->updateDatasetAccess(
                    datasetId: $datasetId,
                    resourceType: 'hosts',
                    fullAccess: true
                );
                break;
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
     * @param NewRule $rule
     *
     * @return int
     */
    private function addRule(NewRule $rule): int
    {
        $this->debug('Adding new rule with basic information');

        return $this->writeRepository->add($rule);
    }

    /**
     * @param int $ruleId
     * @param NewRule $rule
     */
    private function linkContacts(int $ruleId, NewRule $rule): void
    {
        $this->debug(
            'AddRule: Linking contacts to the resource access rule',
            ['ruleId' => $ruleId, 'contact_ids' => $rule->getLinkedContactIds()]
        );

        $this->writeRepository->linkContactsToRule($ruleId, $rule->getLinkedContactIds());
    }

    /**
     * @param int $ruleId
     * @param NewRule $rule
     */
    private function linkContactGroups(int $ruleId, NewRule $rule): void
    {
        $this->debug(
            'AddRule: Linking contact groups to the resource access rule',
            ['ruleId' => $ruleId, 'contact_group_ids' => $rule->getLinkedContactGroupIds()]
        );

        $this->writeRepository->linkContactGroupsToRule($ruleId, $rule->getLinkedContactGroupIds());
    }

    /**
     * This method will ensure that the ids provided for the dataset filters are valid
     * and that the structure of the dataset is correct regarding the hierarchy defined in the DatasetFilter entity.
     *
     * @param AddRuleRequest $request
     *
     * @throws RuleException
     * @throws \InvalidArgumentException
     *
     * @return DatasetFilter[]
     */
    private function validateAndCreateDatasetFiltersFromRequest(AddRuleRequest $request): array
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
     * @param AddRuleRequest $request
     * @param DatasetFilter[] $datasets
     *
     * @return NewRule
     */
    private function createRuleFromRequest(AddRuleRequest $request, array $datasets): NewRule
    {
        return new NewRule(
            name: $request->name,
            description: $request->description,
            applyToAllContacts: $request->applyToAllContacts,
            linkedContactIds: $request->contactIds,
            applyToAllContactGroups: $request->applyToAllContactGroups,
            linkedContactGroupIds: $request->contactGroupIds,
            datasetFilters: $datasets,
            isEnabled: $request->isEnabled
        );
    }

    /**
     * @param int $ruleId
     *
     * @throws RuleException
     *
     * @return AddRuleResponse
     */
    private function createResponse(int $ruleId): AddRuleResponse
    {
        $this->debug('Fetching information post creation', ['rule_id' => $ruleId]);
        $rule = $this->readRepository->findById($ruleId);

        if (! $rule) {
            throw RuleException::errorWhileRetrievingARule();
        }

        // convert recursively DatasetFilter entities to array
        $datasetFilterToArray = function (DatasetFilter $datasetFilter) use (&$datasetFilterToArray): array {
            $data['type'] = $datasetFilter->getType();
            $data['resources'] = $datasetFilter->getResourceIds();
            $data['dataset_filter'] = null;

            if ($datasetFilter->getDatasetFilter() !== null) {
                $data['dataset_filter'] = $datasetFilterToArray($datasetFilter->getDatasetFilter());
            }

            return $data;
        };

        $response = new AddRuleResponse();
        $response->id = $rule->getId();
        $response->name = $rule->getName();
        $response->description = $rule->getDescription();
        $response->isEnabled = $rule->isEnabled();
        $response->contactIds = $rule->getLinkedContactIds();
        $response->contactGroupIds = $rule->getLinkedContactGroupIds();
        $response->applyToAllContacts = $rule->doesApplyToAllContacts();
        $response->applyToAllContactGroups = $rule->doesApplyToAllContactGroups();

        foreach ($rule->getDatasetFilters() as $datasetFilter) {
            $response->datasetFilters[] = $datasetFilterToArray($datasetFilter);
        }

        return $response;
    }
}
