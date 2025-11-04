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
use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Repository\ConnectorRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ConnectorCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ConnectorResource;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<ConnectorResource>
 */
final readonly class ListConnectorsProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Connector,ConnectorResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceConnectorTransformer::class)]
        private TransformerInterface $transformer,
        private ConnectorRepository $repository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<ConnectorResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $criteria = new ConnectorCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        /** @var array{filters: array{name?: array<string, string|array<string>>, id?: array<string, int|array<int>>}} $context */
        $criteria = $this->handleNameFilter($context['filters']['name'] ?? null, $criteria);
        $criteria = $this->handleIdFilter($context['filters']['id'] ?? null, $criteria);

        $connectors = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($connectors as $connector) {
            $resources[] = $this->transformer->transform($connector);
        }

        if (! $connectors instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $connectors->getCurrentPage(),
            $connectors->getItemsPerPage(),
            $connectors->getTotalItems()
        );
    }

    /**
     * @param array<string, string|array<string>>|null $nameFilter
     *
     */
    private function handleNameFilter(?array $nameFilter, ConnectorCriteria $criteria): ConnectorCriteria
    {
        if ($nameFilter === null) {
            return $criteria;
        }

        foreach ($nameFilter as $operator => $names) {
            if (! in_array($operator, ConnectorCriteria::ALLOWED_OPERATORS, true)) {
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
     * @param array<string, int|array<int>>|null $idFilter
     *
     */
    private function handleIdFilter(?array $idFilter, ConnectorCriteria $criteria): ConnectorCriteria
    {
        if ($idFilter === null) {
            return $criteria;
        }
        foreach ($idFilter as $operator => $ids) {
            if (! in_array($operator, ConnectorCriteria::ALLOWED_OPERATORS, true)) {
                continue;
            }
            $ids = is_array($ids) ? array_map(fn (int $id): int => $id, $ids) : [(int) $ids];

            foreach ($ids as $id) {
                $criteria = $criteria->withId($id, $operator);
            }
        }

        return $criteria;
    }
}
