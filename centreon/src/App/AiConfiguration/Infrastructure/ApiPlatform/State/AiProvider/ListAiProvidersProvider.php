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
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\Criteria\AiProviderCriteria;
use App\AiConfiguration\Domain\Security\AiProviderActionEnum;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\ListAiProviderResource;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<ListAiProviderResource>
 */
final readonly class ListAiProvidersProvider implements ProviderInterface
{
    use SortAwareProviderTrait;

    /**
     * @param TransformerInterface<AiProvider, ListAiProviderResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceListAiProviderTransformer::class)]
        private TransformerInterface $transformer,
        private AiProviderRepository $repository,
        private Pagination $pagination,
        private Security $security,
        private AiFeatureAvailability $availability,
    ) {
    }

    /**
     * @return iterable<ListAiProviderResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $this->availability->assertAvailable();

        if (! $this->security->isGranted(AiProviderActionEnum::Read->value)) {
            throw new AccessDeniedException('You are not allowed to access AI provider configurations');
        }

        /** @var array{name?: array<string>|null, type?: string|array<string>, is_default?: string, sort?: array<string, string>} $filters */
        $filters = $context['filters'] ?? [];

        $criteria = new AiProviderCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        $criteria = $this->handleNameFilter($filters['name'] ?? null, $criteria);
        $criteria = $this->handleTypeFilter($filters['type'] ?? null, $criteria);
        $criteria = $this->handleIsDefaultFilter($filters['is_default'] ?? null, $criteria);
        /** @var AiProviderCriteria $criteria */
        $criteria = $this->handleSort($filters, $criteria);

        $aiProviders = $this->repository->findAll($criteria);

        $resources = [];
        foreach ($aiProviders as $aiProvider) {
            $resources[] = $this->transformer->transform($aiProvider);
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
     * @param array<string>|null $nameFilter
     */
    private function handleNameFilter(?array $nameFilter, AiProviderCriteria $criteria): AiProviderCriteria
    {
        if ($nameFilter === null) {
            return $criteria;
        }

        foreach ($nameFilter as $operator => $names) {
            if (! in_array($operator, AiProviderCriteria::ALLOWED_OPERATORS, true)) {
                continue;
            }
            if (is_string($names)) {
                $names = [$names];
            }

            foreach ($names as $name) {
                $criteria = $criteria->withName($name, $operator);
            }
        }

        return $criteria;
    }

    /**
     * @param string|array<string>|null $typeFilter
     */
    private function handleTypeFilter(string|array|null $typeFilter, AiProviderCriteria $criteria): AiProviderCriteria
    {
        if ($typeFilter === null) {
            return $criteria;
        }

        $types = is_string($typeFilter) ? [$typeFilter] : $typeFilter;

        foreach ($types as $type) {
            if (AiProviderTypeEnum::tryFrom($type) === null) {
                continue;
            }
            $criteria = $criteria->withType($type);
        }

        return $criteria;
    }

    private function handleIsDefaultFilter(?string $isDefaultFilter, AiProviderCriteria $criteria): AiProviderCriteria
    {
        if ($isDefaultFilter === null) {
            return $criteria;
        }

        return $criteria->withIsDefault(filter_var($isDefaultFilter, \FILTER_VALIDATE_BOOLEAN));
    }
}
