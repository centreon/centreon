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

use ApiPlatform\Metadata\Exception\AccessDeniedException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\CommandCriteria;
use App\MonitoringConfiguration\Domain\Security\CommandActionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\ListCommandResource;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\ApiPlatform\State\SortAwareProviderTrait;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<ListCommandResource>
 */
final readonly class ListCommandsProvider implements ProviderInterface
{
    use SortAwareProviderTrait;

    /**
     * @param TransformerInterface<Command,ListCommandResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ResourceListCommandTransformer::class)]
        private TransformerInterface $transformer,
        private CommandRepository $commandRepository,
        private Pagination $pagination,
        private Security $security,
    ) {
    }

    /**
     * @return iterable<ListCommandResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        if (! $this->security->isGranted(CommandActionEnum::Read->value)) {
            throw new AccessDeniedException('You are not allowed to access commands');
        }
        $allowedTypes = [];
        foreach (CommandTypeEnum::cases() as $commandType) {
            $readPermission = Command::getReadPermissionForType($commandType);
            $writePermission = Command::getWritePermissionForType($commandType);

            if ($this->security->isGranted($readPermission->value) || $this->security->isGranted($writePermission->value)) {
                $allowedTypes[] = $commandType->name;
            }
        }
        /** @var array{type?: string|array<string>, name?: array<string>|null, is_activated?: string} $filters */
        $filters = $context['filters'] ?? [];

        // Filter allowed types by requested types
        /** @var array<string>|string|null $requestedTypes */
        $requestedTypes = $filters['type'] ?? null;

        if ($requestedTypes !== null) {
            $requestedTypes = is_string($requestedTypes) ? [$requestedTypes] : $requestedTypes;
            $allowedTypes = array_intersect($requestedTypes, $allowedTypes);
        }

        $criteria = new CommandCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        $criteria = $this->handleTypeFilter($allowedTypes, $criteria);
        $criteria = $this->handleNameFilter($filters['name'] ?? null, $criteria);
        $criteria = $this->handleStatusFilter($filters['is_activated'] ?? null, $criteria);
        $criteria = $this->handleLockedFilter($filters['is_from_monitoring_connector'] ?? null, $criteria);
        $criteria = $this->handleSort($filters, $criteria);

        $commands = $this->commandRepository->findAll($criteria);
        $commandResources = [];
        if (count($commands) > 0) {
            $counts = $this->commandRepository->countLinkedResources(array_map(
                fn (Command $command): CommandId => $command->id(),
                iterator_to_array($commands)
            ));
        }
        foreach ($commands as $command) {
            /** @var CommandId $id */
            $id = $command->id();
            $commandResource = $this->transformer->transform($command);
            if (isset($counts) && $counts !== []) {
                $commandResource->hydrateLinkedResourceCount($counts[$id->value]);
            }
            $commandResources[] = $commandResource;
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

    /**
     * @param array<string>|null $nameFilter
     */
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

    /**
     * @param array<string> $typeFilter
     */
    private function handleTypeFilter(array $typeFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($typeFilter === []) {
            return $criteria;
        }

        foreach ($typeFilter as $type) {
            $criteria = $criteria->withType($type);
        }

        return $criteria;
    }

    private function handleStatusFilter(?string $statusFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($statusFilter === null) {
            return $criteria;
        }

        $statusFilter = filter_var($statusFilter, FILTER_VALIDATE_BOOLEAN);

        return $criteria->withStatus($statusFilter);
    }

    private function handleLockedFilter(?string $lockedFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($lockedFilter === null) {
            return $criteria;
        }

        $lockedFilter = filter_var($lockedFilter, FILTER_VALIDATE_BOOLEAN);

        return $lockedFilter
            ? $criteria->withLocked(true)
            : $criteria;
    }
}
