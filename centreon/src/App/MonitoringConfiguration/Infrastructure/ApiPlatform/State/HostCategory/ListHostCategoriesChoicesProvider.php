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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostCategory;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategory;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCategoryCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostCategory\HostCategoryChoicesOutput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<HostCategoryChoicesOutput>
 */
final readonly class ListHostCategoriesChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<HostCategory, HostCategoryChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostCategoryChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private HostCategoryRepository $repository,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<HostCategoryChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        $hasUnrestrictedResourceAccess = $credentialUser->credential->hasUnrestrictedResourceAccess();

        $criteria = new HostCategoryCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array{name?: mixed} $filters */
        $filters = $context['filters'] ?? [];
        if (($name = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withName($name);
        }

        // Unrestricted users see every host category; others are scoped to their accessible categories.
        $criteria = $hasUnrestrictedResourceAccess
            ? $criteria
            : $criteria->withViewerId($credentialUser->credential->userId);

        $hostCategories = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($hostCategories as $hostCategory) {
            $resources[] = $this->transformer->transform($hostCategory);
        }

        if (! $hostCategories instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $hostCategories->getCurrentPage(),
            $hostCategories->getItemsPerPage(),
            $hostCategories->getTotalItems()
        );
    }
}
