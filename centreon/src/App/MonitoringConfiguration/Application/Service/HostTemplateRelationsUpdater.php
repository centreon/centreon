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
use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateRelationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\LinkedServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Service\HostTemplateInheritance;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;

/**
 * Gives a template the relations a partial update changes. A link the requester cannot see is reported as
 * unknown and stays on a replacement; only an added parent or linked service template is held to the license.
 */
final readonly class HostTemplateRelationsUpdater
{
    public function __construct(
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private AccessScopeResolver $accessScopes,
        private HostTemplateLicenseGuard $templateLicenseGuard,
        private LinkedServiceTemplateLicenseGuard $serviceTemplateLicenseGuard,
        private HostTemplateInheritance $inheritance,
    ) {
    }

    public function resolve(HostTemplate $template, PatchHostTemplateCommand $command): HostTemplateRelationsChange
    {
        $notifications = $template->notifications ?? Notifications::default();

        $parentIds = $this->resolveList(
            $command->templateIds,
            $template->parentTemplateIds,
            HostTemplateId::class,
            function (array $ids) use ($template): void {
                $this->assertTemplatesExist($ids);
                $this->templateLicenseGuard->assertLicensed(new Collection($ids, HostTemplateId::class), $template->parentTemplateIds);
            },
        );
        if ($parentIds instanceof Collection) {
            // On the resulting list: a loop already stored through the template is refused as long as
            // the save keeps its back edge.
            $this->inheritance->assertNoLoop($template->id(), $parentIds, $this->hostTemplateRepository->findTemplateParentIds());
        }

        return new HostTemplateRelationsChange(
            parentTemplateIds: $parentIds,
            categoryIds: $this->resolveScoped(
                $command->categoryIds,
                $template->categoryIds,
                HostCategoryId::class,
                fn (): AccessScope => $this->accessScopes->hostCategories($command->actor),
                function (array $ids, AccessScope $scope): void {
                    $missingIds = $scope->missing($ids, array_keys($this->hostCategoryRepository->findNamesByIds(new Collection($ids, HostCategoryId::class))->toArray()));
                    if ($missingIds !== []) {
                        throw new HostCategoryNotFoundException($missingIds);
                    }
                },
            ),
            linkedServiceTemplateIds: $this->resolveList(
                $command->linkedServiceTemplateIds,
                $template->linkedServiceTemplateIds,
                ServiceTemplateId::class,
                function (array $ids) use ($template): void {
                    $missingIds = $this->unknownIds($ids, array_keys($this->serviceTemplateRepository->findNamesByIds(new Collection($ids, ServiceTemplateId::class))->toArray()));
                    if ($missingIds !== []) {
                        throw new LinkedServiceTemplateNotFoundException($missingIds);
                    }
                    $this->serviceTemplateLicenseGuard->assertLicensed(new Collection($ids, ServiceTemplateId::class), $template->linkedServiceTemplateIds);
                },
            ),
            contactIds: $this->resolveScoped(
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
            ),
            contactGroupIds: $this->resolveScoped(
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
            ),
        );
    }

    /**
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

        return $this->resolveList(
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
     * @param \Closure(list<T>): void $assertAccepted checks the values a replacement or an addition brings
     * @param (\Closure(T): bool)|null $isVisible
     *
     * @return NoValue|Collection<T>
     */
    private function resolveList(NoValue|ListChange $change, Collection $current, string $className, \Closure $assertAccepted, ?\Closure $isVisible = null): NoValue|Collection
    {
        if ($change instanceof NoValue) {
            return $change;
        }

        if ($change->mode !== ListChangeModeEnum::Remove && $change->values !== []) {
            $assertAccepted($change->values);
        }

        return new Collection(
            $change->applyTo(array_values($current->toArray()), static fn (AggregateRootId $id): int => $id->value, $isVisible),
            $className,
        );
    }

    /**
     * Templates are not scoped by access rights: only their existence is checked.
     *
     * @param list<HostTemplateId> $ids
     */
    private function assertTemplatesExist(array $ids): void
    {
        $missingIds = $this->unknownIds($ids, array_keys($this->hostTemplateRepository->findNamesByIds(new Collection($ids, HostTemplateId::class))->toArray()));

        if ($missingIds !== []) {
            throw new HostTemplateNotFoundException($missingIds);
        }
    }

    /**
     * @template T of AggregateRootId
     *
     * @param list<T> $ids
     * @param list<int|string> $foundIds
     *
     * @return list<int>
     */
    private function unknownIds(array $ids, array $foundIds): array
    {
        $requestedIds = array_map(static fn (AggregateRootId $id): int => $id->value, $ids);

        return array_values(array_unique(array_diff($requestedIds, array_map(intval(...), $foundIds))));
    }
}
