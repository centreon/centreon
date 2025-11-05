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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command\CommandResource;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\TransformerInterface;
use Centreon\Domain\Contact\Contact;
use Symfony\Bundle\SecurityBundle\Security;
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
        private Security $security,
    ) {

    }

    /**
     * @return iterable<CommandResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $allowedTypes = [];
        foreach (CommandTypeEnum::cases() as $commandType) {
            $readPermission = Command::getReadPermissionForType($commandType);
            $writePermission = Command::getWritePermissionForType($commandType);

            if ($this->security->isGranted($readPermission->value) || $this->security->isGranted($writePermission->value)) {
                $allowedTypes[] = $commandType->name;
            }
        }

        if (empty($allowedTypes)) {
            return new TraversablePaginator(
                new \ArrayIterator([]),
                1,
                $this->pagination->getLimit($operation, $context),
                0
            );
        }

        // allowed types Intersect with requested types
        if (isset($context['filters']['type'])) {
            /** @var array<string> $requestedTypes */
            $requestedTypes = $context['filters']['type'];
            $allowedTypes = array_intersect($requestedTypes, $allowedTypes);
        }

        $criteria = new CommandCriteria();
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context)
            );
        }

        /** @var array{filters: array{name?: array<string, string|array<string>>, id?: array<string, int|array<int>>}} $context */
        $criteria = $this->handleNameFilter($context['filters']['name'] ?? null, $criteria);
        $criteria = $this->handleTypeFilter($allowedTypes ?? null, $criteria);
        $criteria = $this->handleStatusFilter($context['filters']['status'] ?? null, $criteria);

        $commands = $this->commandRepository->findAll($criteria);
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

    /**
     * @param array<string, string|array<string>>|null $nameFilter
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
     * @param array<string, string>|null $typeFilter
     */
    private function handleTypeFilter(?array $typeFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($typeFilter === null) {

            return $criteria;
        }

        foreach ($typeFilter as $type) {
            $criteria = $criteria->withType($type);
        }

        return $criteria;
    }

    /**
     * @param array<string, bool>|null $statusFilter
     */
    private function handleStatusFilter(?array $statusFilter, CommandCriteria $criteria): CommandCriteria
    {
        if ($statusFilter === null) {
            return $criteria;
        }

        foreach ($statusFilter as $value) {
            $status = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $criteria = $criteria->withStatus($status);
        }

        return $criteria;
    }
}
