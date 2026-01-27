<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GlobalMacroCriteria;
use App\MonitoringConfiguration\Domain\Repository\GlobalMacroRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResource;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<GlobalMacroResource>
 */
final readonly class ListGlobalMacrosProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<GlobalMacro,GlobalMacroResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceGlobalMacroTransformer::class)]
        private TransformerInterface $transformer,
        private GlobalMacroRepository $repository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<GlobalMacroResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $criteria = new GlobalMacroCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        /** @var array{filters: array{name?: array<string, string|array<string>>}} $context */
        $nameFilter = $context['filters']['name'] ?? null;
        if ($nameFilter) {
            foreach ($nameFilter as $operator => $names) {
                if (! in_array($operator, GlobalMacroCriteria::ALLOWED_OPERATORS, true)) {
                    continue;
                }
                if (is_string($names)) {
                    $names = [$names];
                }

                foreach ($names as $name) {
                    $criteria = $criteria->withName($name, $operator);
                }
            }
        }

        $models = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($models as $model) {
            $resources[] = $this->transformer->transform($model);
        }

        if (! $models instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $models->getCurrentPage(),
            $models->getItemsPerPage(),
            $models->getTotalItems()
        );
    }
}
