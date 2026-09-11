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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostSeverity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverity;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostSeverityCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostSeverity\HostSeverityCollectionOutput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<HostSeverityCollectionOutput>
 */
final readonly class ListHostSeveritiesProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<HostSeverity, HostSeverityCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostSeverityCollectionTransformer::class)]
        private TransformerInterface $transformer,
        private HostSeverityRepository $repository,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<HostSeverityCollectionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        // Super admins and Cloud tenant admins escape ACL scoping, matching legacy
        // FindHostSeverities::isUserAdmin (Contact::isAdmin OR customer_admin_acl on Cloud).
        $hasUnrestrictedResourceAccess = $credentialUser->credential->hasUnrestrictedResourceAccess();

        $criteria = new HostSeverityCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array{name?: mixed} $filters */
        $filters = $context['filters'] ?? [];
        $criteria = $this->handleNameFilter($filters['name'] ?? null, $criteria);

        // Unrestricted users see every host severity; others are scoped to their accessible severities.
        $criteria = $hasUnrestrictedResourceAccess
            ? $criteria
            : $criteria->withViewerId($credentialUser->credential->userId);

        $hostSeverities = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($hostSeverities as $hostSeverity) {
            $resources[] = $this->transformer->transform($hostSeverity);
        }

        if (! $hostSeverities instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $hostSeverities->getCurrentPage(),
            $hostSeverities->getItemsPerPage(),
            $hostSeverities->getTotalItems()
        );
    }

    private function handleNameFilter(mixed $nameFilter, HostSeverityCriteria $criteria): HostSeverityCriteria
    {
        if ($nameFilter === null) {
            return $criteria;
        }

        // a client sending "?name=foo" instead of "?name[lk]=foo" lands here as a plain string
        if (! is_array($nameFilter)) {
            throw new BadRequestHttpException('The "name" filter must use the "name[lk]=value" format.');
        }

        $likeValue = $nameFilter['lk'] ?? null;
        if (is_array($likeValue)) {
            $likeValue = reset($likeValue);
        }

        if (! is_string($likeValue) || $likeValue === '') {
            return $criteria;
        }

        return $criteria->withName($likeValue);
    }
}
