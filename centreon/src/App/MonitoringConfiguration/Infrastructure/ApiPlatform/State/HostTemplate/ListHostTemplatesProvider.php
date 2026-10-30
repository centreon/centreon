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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Domain\Repository\SortDirectionEnum;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<HostTemplateCollectionOutput>
 */
final readonly class ListHostTemplatesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;
    use SortAwareProviderTrait;

    /**
     * @param TransformerInterface<HostTemplate, HostTemplateCollectionOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostTemplateCollectionOutputTransformer::class)]
        private TransformerInterface $transformer,
        private HostTemplateRepository $repository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<HostTemplateCollectionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // No actor scoping: host templates are never ACL-filtered. Like the legacy listing page,
        // access is the page permission alone.
        $criteria = HostTemplateCriteria::unscoped();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        // one search covers both the name and the alias, like the legacy listing
        if (($search = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withNameOrAlias($search);
        }

        if ($this->handleBoolFilter($filters['is_locked'] ?? null, 'is_locked') !== true) {
            $criteria = $criteria->withoutLocked();
        }

        $criteria = $this->handleSort($filters, $criteria);
        if ($criteria->getSort() === []) {
            $criteria = $criteria->withSort('name', SortDirectionEnum::ASC);
        }

        $hostTemplates = $this->repository->findAll($criteria);
        $resources = $this->transformHostTemplates(array_values(iterator_to_array($hostTemplates)));

        if (! $hostTemplates instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $hostTemplates->getCurrentPage(),
            $hostTemplates->getItemsPerPage(),
            $hostTemplates->getTotalItems()
        );
    }

    /**
     * Transforms the page, then assigns what each template references by id, fetched in bulk for
     * the whole page: its linked service template count, its parents' names and its icon.
     *
     * @param list<HostTemplate> $hostTemplates
     *
     * @return list<HostTemplateCollectionOutput>
     */
    private function transformHostTemplates(array $hostTemplates): array
    {
        if ($hostTemplates === []) {
            return [];
        }

        /** @var list<HostTemplateId> $ids */
        $ids = [];
        /** @var array<int, HostTemplateId> $parentIds */
        $parentIds = [];
        /** @var array<int, MediaId> $iconIds */
        $iconIds = [];
        /** @var list<HostTemplateId> $idsWithoutIcon */
        $idsWithoutIcon = [];
        foreach ($hostTemplates as $hostTemplate) {
            $ids[] = $hostTemplate->id();
            foreach ($hostTemplate->parentTemplateIds as $parentId) {
                $parentIds[$parentId->value] = $parentId;
            }
            if ($hostTemplate->iconId instanceof MediaId) {
                $iconIds[$hostTemplate->iconId->value] = $hostTemplate->iconId;
            } elseif (count($hostTemplate->parentTemplateIds) > 0) {
                $idsWithoutIcon[] = $hostTemplate->id();
            }
        }

        // like legacy, a template without its own icon falls back to the one inherited from its parents
        /** @var array<int, MediaId> $inheritedIconIds */
        $inheritedIconIds = $idsWithoutIcon === []
            ? []
            : $this->repository->findTemplatesInheritedIconIds(new Collection($idsWithoutIcon, HostTemplateId::class))->toArray();
        foreach ($inheritedIconIds as $inheritedIconId) {
            $iconIds[$inheritedIconId->value] = $inheritedIconId;
        }

        $linkedCounts = $this->repository->countLinkedServiceTemplates(new Collection($ids, HostTemplateId::class));
        /** @var array<int, HostTemplateName> $parentNames */
        $parentNames = $this->repository->findNamesByIds(new Collection(array_values($parentIds), HostTemplateId::class))->toArray();
        /** @var array<int, Media> $icons */
        $icons = $iconIds === []
            ? []
            : $this->mediaRepository->findByIds(new Collection(array_values($iconIds), MediaId::class))->toArray();

        $resources = [];
        foreach ($hostTemplates as $hostTemplate) {
            $id = $hostTemplate->id()->value;
            $resource = $this->transformer->transform($hostTemplate);
            $resource->linkedServiceTemplatesCount = $linkedCounts[$id] ?? 0;

            $parents = [];
            foreach ($hostTemplate->parentTemplateIds as $parentId) {
                if (isset($parentNames[$parentId->value])) {
                    $parents[] = new HostTemplateOutput($parentId->value, $parentNames[$parentId->value]->value);
                }
            }
            $resource->parentTemplates = $parents;

            $iconId = $hostTemplate->iconId ?? $inheritedIconIds[$id] ?? null;
            $icon = $iconId !== null ? $icons[$iconId->value] ?? null : null;
            $resource->icon = $icon instanceof Media
                ? new HostIconOutput($icon->id()->value, $icon->name->value, $this->mediaUrlGenerator->generate($icon))
                : null;

            $resources[] = $resource;
        }

        return $resources;
    }
}
