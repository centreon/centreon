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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ResourceCommandTransformer;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<CommandResource>
 */
final readonly class ListCommandsProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Command,CommandResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceCommandTransformer::class)]
        private TransformerInterface $transformer,
        private CommandRepository $commandRepository,
        private Pagination $pagination,
    ) {

    }

    /**
     * @return iterable<CommandResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $criteria = new CommandCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

         /** @var array{filters: array{name?: array<string, string|array<string>>, id?: array<string, int|array<int>>}} $context */
        $criteria = $this->handleNameFilter($context['filters']['name'] ?? null, $criteria);
        $criteria = $this->handleTypeFilter($context['filters']['type'] ?? null, $criteria);
        $criteria = $this->handleStatusFilter($context['filters']['isActivated'] ?? null, $criteria);

        $commands = $this->commandRepository->findAllByCriteria($criteria);
        $commandResources = [];
        foreach ($commands as $command) {
            $commandResources[] = $this->transformer->transform($command);
        }

        if (! $commands instanceof Paginator) {
            return $commandResources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($commandResources),
            $commands->getCurrentPage(),
            $commands->getItemsPerPage(),
            $commands->getTotalItems()
        );
    }

    private function handleNameFilter(?array $nameFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($nameFilter === null) {
            return $criteria;
        }

        foreach ($nameFilter as $operator => $names) {
            if (! in_array($operator, CommandCriteria::ALLOWED_OPERATORS, true)) {
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

    private function handleTypeFilter(?array $typeFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($typeFilter === null) {
            return $criteria;
        }

        foreach ($typeFilter as $types) {
            if (is_string($types)) {
                $types = [$types];
            }

            foreach ($types as $type) {
                $criteria = $criteria->withType($type);
            }
        }

        return $criteria;
    }

    private function handleStatusFilter(?bool $statusFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($statusFilter === null) {
            return $criteria;
        }

        if (is_string($statusFilter)) {
            $status = filter_var($statusFilter, FILTER_VALIDATE_BOOLEAN);
            $criteria = $criteria->withStatus($status);
        }

        return $criteria;
    }
}
