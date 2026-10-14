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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostSeverity\HostSeverityChoicesOutput;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<HostSeverityChoicesOutput>
 */
final readonly class ListHostSeveritiesChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<HostSeverity, HostSeverityChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: HostSeverityChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private HostSeverityRepository $repository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<HostSeverityChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // No viewer scoping: the legacy host form's severity picker (CentreonCriticality::getList())
        // ignores ACL visibility, unlike the generic /configuration/host_severities endpoint.
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
        if (($name = $this->handleLikeFilter($filters['name'] ?? null, 'name')) !== null) {
            $criteria = $criteria->withName($name);
        }

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
}
