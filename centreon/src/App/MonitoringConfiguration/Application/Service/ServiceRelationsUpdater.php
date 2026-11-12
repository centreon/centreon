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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\ListChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TrapNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;

/**
 * Gives a service the relations (service groups, categories, traps, contacts and contact groups) a partial update
 * changes.
 *
 * The resources a change brings are checked here, as the authority, after the same rules ran earlier on the input.
 * A resource the actor cannot access is reported like one that does not exist, and a replacement leaves in place
 * the links the actor cannot see (service groups, categories, contacts and contact groups are scoped by access
 * rights, traps are not).
 */
final readonly class ServiceRelationsUpdater
{
    public function __construct(
        private ServiceGroupRepository $serviceGroupRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
        private TrapRepository $trapRepository,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private AccessScopeResolver $accessScopes,
    ) {
    }

    public function applyTo(Service $service, PatchServiceCommand $command): Service
    {
        $updated = $service->with(
            serviceGroupIds: $this->resolveScoped(
                $command->serviceGroupIds,
                $service->serviceGroupIds,
                ServiceGroupId::class,
                fn (): AccessScope => $this->accessScopes->serviceGroups($command->actor),
                function (array $ids, AccessScope $scope): void {
                    $missingIds = $scope->missing($ids, array_keys($this->serviceGroupRepository->findNamesByIds(new Collection($ids, ServiceGroupId::class))->toArray()));
                    if ($missingIds !== []) {
                        throw new ServiceGroupNotFoundException($missingIds);
                    }
                },
            ),
            categoryIds: $this->resolveScoped(
                $command->categoryIds,
                $service->categoryIds,
                ServiceCategoryId::class,
                fn (): AccessScope => $this->accessScopes->serviceCategories($command->actor),
                function (array $ids, AccessScope $scope): void {
                    $missingIds = $scope->missing($ids, array_keys($this->serviceCategoryRepository->findNamesByIds(new Collection($ids, ServiceCategoryId::class))->toArray()));
                    if ($missingIds !== []) {
                        throw new ServiceCategoryNotFoundException($missingIds);
                    }
                },
            ),
            trapIds: $this->resolve(
                $command->trapIds,
                $service->trapIds,
                TrapId::class,
                function (array $ids): void {
                    $missingIds = array_values(array_unique(array_diff(
                        array_map(static fn (TrapId $id): int => $id->value, $ids),
                        array_keys($this->trapRepository->findByIds(new Collection($ids, TrapId::class))->toArray()),
                    )));
                    if ($missingIds !== []) {
                        throw new TrapNotFoundException($missingIds);
                    }
                },
            ),
        );

        return $this->withContacts($updated, $command);
    }

    private function withContacts(Service $service, PatchServiceCommand $command): Service
    {
        if ($command->contactIds instanceof NoValue && $command->contactGroupIds instanceof NoValue) {
            return $service;
        }

        $notifications = $service->notifications ?? ServiceNotificationsChanges::none();
        $contactIds = $this->resolveScoped(
            $command->contactIds,
            $notifications->contactIds,
            NotificationContactId::class,
            fn (): AccessScope => $this->accessScopes->contacts($command->actor),
            function (array $ids, AccessScope $scope): void {
                $missingIds = $scope->missing($ids, array_keys($this->contactRepository->findNamesByIds(new Collection($ids, NotificationContactId::class))->toArray()));
                if ($missingIds !== []) {
                    throw new NotificationContactNotFoundException($missingIds);
                }
            },
        );
        $contactGroupIds = $this->resolveScoped(
            $command->contactGroupIds,
            $notifications->contactGroupIds,
            ContactGroupId::class,
            fn (): AccessScope => $this->accessScopes->contactGroups($command->actor),
            function (array $ids, AccessScope $scope): void {
                $missingIds = $scope->missing($ids, array_keys($this->contactGroupRepository->findNamesByIds(new Collection($ids, ContactGroupId::class))->toArray()));
                if ($missingIds !== []) {
                    throw new ContactGroupNotFoundException($missingIds);
                }
            },
        );

        return $service->with(notifications: $notifications->withContacts(
            NoValue::resolve($contactIds, $notifications->contactIds),
            NoValue::resolve($contactGroupIds, $notifications->contactGroupIds),
        ));
    }

    /**
     * Like {@see resolve()} for a relation scoped by what the actor can see: the list of what they can see is loaded
     * once, and only when the relation is changed, to check what is brought and to tell what is left in place.
     *
     * @template T of AggregateRootId
     *
     * @param NoValue|ListChange<T> $change
     * @param Collection<T> $current
     * @param class-string<T> $className
     * @param \Closure(): AccessScope<T> $loadScope
     * @param \Closure(list<T>, AccessScope<T>): void $assertExist
     *
     * @return NoValue|Collection<T>
     */
    private function resolveScoped(NoValue|ListChange $change, Collection $current, string $className, \Closure $loadScope, \Closure $assertExist): NoValue|Collection
    {
        if ($change instanceof NoValue) {
            return $change;
        }

        $scope = $loadScope();

        return $this->resolve(
            $change,
            $current,
            $className,
            static fn (array $ids) => $assertExist($ids, $scope),
            $scope->allows(...),
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param NoValue|ListChange<T> $change
     * @param Collection<T> $current
     * @param class-string<T> $className
     * @param \Closure(list<T>): void $assertExist checks the values a replacement or an addition brings
     * @param (\Closure(T): bool)|null $isVisible
     *
     * @return NoValue|Collection<T>
     */
    private function resolve(NoValue|ListChange $change, Collection $current, string $className, \Closure $assertExist, ?\Closure $isVisible = null): NoValue|Collection
    {
        if ($change instanceof NoValue) {
            return $change;
        }

        if ($change->mode !== ListChangeModeEnum::Remove && $change->values !== []) {
            $assertExist($change->values);
        }

        return new Collection(
            $change->applyTo(array_values($current->toArray()), static fn (AggregateRootId $id): int => $id->value, $isVisible),
            $className,
        );
    }
}
