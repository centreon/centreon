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
use App\MonitoringConfiguration\Application\Service\UnlicensedHostTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateChoicesOutput;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<HostTemplateChoicesOutput>
 */
final readonly class ListHostTemplatesChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<HostTemplate, HostTemplateChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostTemplateChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private HostTemplateRepository $repository,
        private Pagination $pagination,
        private UnlicensedHostTemplates $unlicensedHostTemplates,
    ) {
    }

    /**
     * @return iterable<HostTemplateChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // Unscoped, locked included, id order, like CentreonHost::getList(); Monitoring Connector
        // templates excluded once the license host limit is reached (CentreonHost::getLimitedList()).
        $criteria = HostTemplateCriteria::unscoped();
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

        $excludedIds = $this->unlicensedHostTemplates->findIds()->toArray();
        $templateId = $operation->getParameters()?->get('exclude_template_id')?->getValue(null);
        if (is_int($templateId)) {
            // the template itself and its descendants: picking any of them as its parent makes a loop
            $templateId = new HostTemplateId($templateId);
            array_push($excludedIds, $templateId, ...$this->repository->findDescendantIds($templateId)->toArray());
        }
        $criteria = $criteria->withExcludedIds(new Collection($excludedIds, HostTemplateId::class));

        $hostTemplates = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($hostTemplates as $hostTemplate) {
            $resources[] = $this->transformer->transform($hostTemplate);
        }

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
}
