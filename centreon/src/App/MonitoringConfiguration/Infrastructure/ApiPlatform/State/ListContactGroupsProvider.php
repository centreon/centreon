<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroup;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroupResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<ContactGroupResource>
 */
final readonly class ListContactGroupsProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<ContactGroup, ContactGroupResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceContactGroupTransformer::class)]
        private TransformerInterface $transformer,
        private ContactGroupRepository $repository,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<ContactGroupResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $criteria = new ContactGroupCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        foreach ($this->handleOperatorFilter($filters['name'] ?? null, 'name', ContactGroupCriteria::ALLOWED_OPERATORS) as $operator => $values) {
            foreach ($values as $value) {
                $criteria = $criteria->withName($value, $operator);
            }
        }
        foreach ($this->handleOperatorFilter($filters['id'] ?? null, 'id', ContactGroupCriteria::ALLOWED_OPERATORS) as $operator => $values) {
            foreach ($values as $value) {
                $criteria = $criteria->withId($this->handlePositiveIntFilter($value, 'id') ?? 0, $operator);
            }
        }

        // ACL data-scoping: an admin (unrestricted access) sees everything; anyone else is
        // scoped to the contact groups they may see, resolved by the repository from the viewer.
        $criteria = $credentialUser->credential->hasUnrestrictedResourceAccess()
            ? $criteria
            : $criteria->withViewerId($credentialUser->credential->userId);

        $contactGroups = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($contactGroups as $contactGroup) {
            $resources[] = $this->transformer->transform($contactGroup);
        }

        if (! $contactGroups instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $contactGroups->getCurrentPage(),
            $contactGroups->getItemsPerPage(),
            $contactGroups->getTotalItems()
        );
    }
}
