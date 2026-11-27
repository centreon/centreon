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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Application\Service\UnlicensedServiceTemplates;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ServiceTemplate\ServiceTemplateChoicesOutput;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\ApiPlatform\State\PaginationResolver;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<ServiceTemplateChoicesOutput>
 */
final readonly class ListServiceTemplatesChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<ServiceTemplate, ServiceTemplateChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: ServiceTemplateChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private ServiceTemplateRepository $repository,
        private PaginationResolver $paginationResolver,
        private UnlicensedServiceTemplates $unlicensedServiceTemplates,
    ) {
    }

    /**
     * @return iterable<ServiceTemplateChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // No viewer scoping: the legacy service form's source (centreon_configuration_servicetemplate::listClassic) ignores ACL.
        // Unlike legacy, which never filters service templates on the license, the templates coming from
        // Monitoring Connectors are excluded once the license host limit is reached, as the host form does.
        $criteria = new ServiceTemplateCriteria();
        if (($pagination = $this->paginationResolver->resolve($operation, $context)) instanceof Pagination) {
            $criteria = $criteria->withPagination($pagination);
        }

        /** @var array{name?: mixed} $filters */
        $filters = $context['filters'] ?? [];
        if (($name = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withName($name);
        }
        $criteria = $criteria->withExcludedIds($this->unlicensedServiceTemplates->findIds());

        $serviceTemplates = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($serviceTemplates as $serviceTemplate) {
            $resources[] = $this->transformer->transform($serviceTemplate);
        }

        if (! $serviceTemplates instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $serviceTemplates->getCurrentPage(),
            $serviceTemplates->getItemsPerPage(),
            $serviceTemplates->getTotalItems()
        );
    }
}
