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
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandChoicesOutput;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<CommandChoicesOutput>
 */
final readonly class ListCommandsChoicesProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    /**
     * @param TransformerInterface<Command, CommandChoicesOutput> $transformer
     */
    public function __construct(
        #[Autowire(service: CommandChoicesTransformer::class)]
        private TransformerInterface $transformer,
        private CommandRepository $repository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<CommandChoicesOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        // No per-type command permission here: the host form fills a host's check and event handler
        // commands, so the host read-write permission checked on the operation is the only gate.
        $criteria = new CommandCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $itemsPerPage = $this->pagination->getLimit($operation, $context);
            if ($itemsPerPage <= 0) {
                throw new BadRequestHttpException('itemsPerPage must be a positive integer.');
            }
            $criteria = $criteria->withPagination($this->pagination->getPage($context), $itemsPerPage);
        }

        /** @var array{name?: mixed, type?: mixed, is_activated?: mixed} $filters */
        $filters = $context['filters'] ?? [];

        foreach ($this->handleOperatorFilter($filters['name'] ?? null, 'name', CommandCriteria::ALLOWED_OPERATORS) as $operator => $values) {
            foreach ($values as $value) {
                $criteria = $criteria->withName($value, $operator);
            }
        }

        $requestedTypes = $filters['type'] ?? [];
        foreach (is_array($requestedTypes) ? $requestedTypes : [$requestedTypes] as $type) {
            if (! is_string($type) || ! $this->isCommandTypeName($type)) {
                throw new BadRequestHttpException('The "type" filter must be one of: ' . implode(', ', array_column(CommandTypeEnum::cases(), 'name')) . '.');
            }
            $criteria = $criteria->withType($type);
        }

        if (is_string($filters['is_activated'] ?? null)) {
            $criteria = $criteria->withIsActivated(filter_var($filters['is_activated'], FILTER_VALIDATE_BOOLEAN));
        }

        $commands = $this->repository->findAll($criteria);
        $resources = [];
        foreach ($commands as $command) {
            $resources[] = $this->transformer->transform($command);
        }

        if (! $commands instanceof Paginator) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $commands->getCurrentPage(),
            $commands->getItemsPerPage(),
            $commands->getTotalItems()
        );
    }

    private function isCommandTypeName(string $type): bool
    {
        return in_array($type, array_column(CommandTypeEnum::cases(), 'name'), true);
    }
}
