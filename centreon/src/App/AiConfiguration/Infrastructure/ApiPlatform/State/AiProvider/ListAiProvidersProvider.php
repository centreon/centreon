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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;
use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Domain\Usage\UsageSummaryProvider;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\ListAiProviderResource;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\ApiPlatform\State\PaginationResolver;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<ListAiProviderResource>
 */
final readonly class ListAiProvidersProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;
    use SortAwareProviderTrait;

    public function __construct(
        private ResourceListAiProviderTransformer $transformer,
        private AiProviderRepository $repository,
        private PaginationResolver $paginationResolver,
        private Security $security,
        private AiFeatureAvailability $availability,
        private UsageSummaryProvider $usageSummaryProvider,
    ) {
    }

    /**
     * @throws AccessDeniedException when AI features are not available or the user may not read the configurations
     * @throws BadRequestHttpException when a filter is malformed
     *
     * @return iterable<ListAiProviderResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $this->availability->assertAvailable();

        if (! $this->security->isGranted(AiProviderActionEnum::Read->value)) {
            throw new AccessDeniedException('You are not allowed to access AI provider configurations');
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        $criteria = new AiProviderCriteria();
        $pagination = $this->paginationResolver->resolve($operation, $context);
        if ($pagination instanceof Pagination) {
            $criteria = $criteria->withPagination($pagination);
        }

        $criteria = $this->handleNameFilter($filters['name'] ?? null, $criteria);
        $criteria = $this->handleTypeFilter($filters['type'] ?? null, $criteria);

        $isActivated = $this->handleBoolFilter($filters['is_activated'] ?? null, 'is_activated');
        if ($isActivated !== null) {
            $criteria = $criteria->withIsActivated($isActivated);
        }
        /** @var AiProviderCriteria $criteria */
        $criteria = $this->handleSort($filters, $criteria);

        $aiProviders = $this->repository->findAll($criteria);

        $resources = [];
        foreach ($aiProviders as $aiProvider) {
            $resources[] = $this->transformer->transformWithUsage(
                $aiProvider,
                $this->usageSummaryProvider->forProvider($aiProvider)
            );
        }

        if (! $aiProviders instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $aiProviders->getCurrentPage(),
            $aiProviders->getItemsPerPage(),
            $aiProviders->getTotalItems()
        );
    }

    /**
     * @throws BadRequestHttpException when the filter is malformed or uses an unknown operator
     */
    private function handleNameFilter(mixed $nameFilter, AiProviderCriteria $criteria): AiProviderCriteria
    {
        // the shared parser drops unknown operators: reject them, so a typo does not list everything
        if (is_array($nameFilter)) {
            foreach (array_keys($nameFilter) as $operator) {
                if (! in_array($operator, AiProviderCriteria::ALLOWED_OPERATORS, true)) {
                    throw new BadRequestHttpException(sprintf(
                        'The "name" filter operator must be one of: %s.',
                        implode(', ', AiProviderCriteria::ALLOWED_OPERATORS)
                    ));
                }
            }
        }

        foreach ($this->handleOperatorFilter($nameFilter, 'name', AiProviderCriteria::ALLOWED_OPERATORS) as $operator => $names) {
            foreach ($names as $name) {
                $criteria = $criteria->withName($name, $operator);
            }
        }

        return $criteria;
    }

    /**
     * @throws BadRequestHttpException when a type is unknown
     */
    private function handleTypeFilter(mixed $typeFilter, AiProviderCriteria $criteria): AiProviderCriteria
    {
        if ($typeFilter === null) {
            return $criteria;
        }

        foreach (is_array($typeFilter) ? $typeFilter : [$typeFilter] as $type) {
            if (! is_string($type) || AiProviderTypeEnum::tryFrom($type) === null) {
                throw new BadRequestHttpException(sprintf(
                    'The "type" filter must be one of: %s.',
                    implode(', ', AiProviderTypeEnum::values())
                ));
            }
            $criteria = $criteria->withType($type);
        }

        return $criteria;
    }
}
