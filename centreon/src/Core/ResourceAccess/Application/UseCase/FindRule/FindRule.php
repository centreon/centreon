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

namespace Core\ResourceAccess\Application\UseCase\FindRule;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Providers\DatasetProviderInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindRule
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /** @var DatasetProviderInterface[] */
    private array $repositoryProviders = [];

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadResourceAccessRepositoryInterface $repository
     * @param ReadContactRepositoryInterface $contactRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param DatasetFilterValidator $datasetFilterValidator
     * @param bool $isCloudPlatform
     * @param \Traversable<DatasetProviderInterface> $repositoryProviders
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $repository,
        private readonly ReadContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly DatasetFilterValidator $datasetFilterValidator,
        private readonly bool $isCloudPlatform,
        \Traversable $repositoryProviders
    ) {
        $this->repositoryProviders = iterator_to_array($repositoryProviders);
    }

    /**
     * @param int $ruleId
     * @param FindRulePresenterInterface $presenter
     */
    public function __invoke(int $ruleId, FindRulePresenterInterface $presenter): void
    {
        try {
            $response = $this->isAuthorized()
                ? $this->findRule($ruleId)
                : new ForbiddenResponse(RuleException::notAllowed()->getMessage());

            if ($response instanceof FindRuleResponse) {
                $this->info('Finding resource access rule detail', ['rule_id' => $ruleId]);
            } elseif ($response instanceof NotFoundResponse) {
                $this->warning('Resource Access Rule (%s) not found', ['rule_id' => $ruleId]);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to list resource access rules",
                    [
                        'user_id' => $this->user->getId(),
                    ]
                );
            }

            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(RuleException::errorWhileSearchingRules()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $ruleId
     *
     * @return FindRuleResponse|NotFoundResponse
     */
    private function findRule(int $ruleId): FindRuleResponse|NotFoundResponse
    {
        $rule = $this->repository->findById($ruleId);

        if (null === $rule) {
            return new NotFoundResponse('Resource Access Rule');
        }

        return $this->createResponse($rule);
    }

    /**
     * @param Rule $rule
     *
     * @return FindRuleResponse
     */
    private function createResponse(Rule $rule): FindRuleResponse
    {
        $response = new FindRuleResponse();
        $response->id = $rule->getId();
        $response->name = $rule->getName();
        $response->description = $rule->getDescription();
        $response->isEnabled = $rule->isEnabled();
        $response->applyToAllContacts = $rule->doesApplyToAllContacts();
        $response->applyToAllContactGroups = $rule->doesApplyToAllContactGroups();

        // retrieve names of linked contact IDs
        $response->contacts = array_values(
            $this->contactRepository->findNamesByIds(...$rule->getLinkedContactIds())
        );

        // retrieve names of linked contact group IDs
        $response->contactGroups = array_values(
            $this->contactGroupRepository->findNamesByIds(...$rule->getLinkedContactGroupIds())
        );

       // convert recursively DatasetFilter entities to array
        $datasetFilterToArray = function (DatasetFilter $datasetFilter) use (&$datasetFilterToArray): array
        {
            $data['type'] = $datasetFilter->getType();

            if (
                $datasetFilter->getResourceIds() === []
                && $this->datasetFilterValidator->canResourceIdsBeEmpty($data['type'])
            ) {
                $data['resources'] = [];

                // special 'ALL' type dataset_filter type case
                if ($data['type'] === DatasetFilterValidator::ALL_RESOURCES_FILTER) {
                    $data['dataset_filter'] = null;

                    return $data;
                }
            } else {
                $resourcesNamesById = null;
                foreach ($this->repositoryProviders as $provider) {
                    if ($provider->isValidFor($data['type'])) {
                        $resourcesNamesById = $provider->findResourceNamesByIds($datasetFilter->getResourceIds());
                    }
                }

                if ($resourcesNamesById === null) {
                    throw new \InvalidArgumentException('No repository providers found');
                }

                $data['resources'] = array_map(
                    static fn (int $resourceId): array => ['id' => $resourceId, 'name' => $resourcesNamesById->getName($resourceId)],
                    $datasetFilter->getResourceIds()
                );
            }

            $data['dataset_filter'] = null;

            if ($datasetFilter->getDatasetFilter() !== null) {
                $data['dataset_filter'] = $datasetFilterToArray($datasetFilter->getDatasetFilter());
            }

            return $data;
        };

        foreach ($rule->getDatasetFilters() as $datasetFilter) {
            $response->datasetFilters[] = $datasetFilterToArray($datasetFilter);
        }

        return $response;
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

        /**
         * User must be
         *     - An admin (belongs to the centreon_admin_acl ACL group)
         *     - authorized to reach the Resource Access Management page.
         */
        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW)
            && $this->isCloudPlatform;
    }
}
