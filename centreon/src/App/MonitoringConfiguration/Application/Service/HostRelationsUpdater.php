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
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;

/**
 * Gives a host the relations (templates, host groups, categories, parents, children, contacts and
 * contact groups) a partial update changes.
 *
 * The resources a change brings are checked here, as the authority, after the same rules ran earlier
 * on the input. A resource the requester cannot access is reported like one that does not exist, and
 * a replacement leaves in place the links the requester cannot see (host groups, categories, hosts,
 * contacts and contact groups are scoped by access rights, templates are not). A null viewer is an
 * unrestricted requester.
 */
final readonly class HostRelationsUpdater
{
    public function __construct(
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostRepository $hostRepository,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private HostTemplateLicenseGuard $templateLicenseGuard,
    ) {
    }

    public function applyTo(Host $host, PatchHostCommand $command): Host
    {
        $viewerId = $command->viewerId;
        $reachableHostIds = $this->reachableHostIds($host, $command, $viewerId);
        $isHostVisible = $viewerId instanceof UserId
            ? static fn (HostId $id): bool => in_array($id->value, $reachableHostIds, true)
            : null;

        $parentHostIds = $this->resolve(
            $command->parentHostIds,
            $host->parentHostIds,
            HostId::class,
            fn (array $ids) => $this->assertHostsExist($ids, 'parentHostIds', $reachableHostIds),
            $isHostVisible,
        );
        $childHostIds = $this->resolve(
            $command->childHostIds,
            $host->childHostIds,
            HostId::class,
            fn (array $ids) => $this->assertHostsExist($ids, 'childHostIds', $reachableHostIds),
            $isHostVisible,
        );
        if ($parentHostIds instanceof Collection || $childHostIds instanceof Collection) {
            $this->assertRelationsAreNotCircular(
                $host->id(),
                $parentHostIds instanceof Collection ? $parentHostIds : $host->parentHostIds,
                $childHostIds instanceof Collection ? $childHostIds : $host->childHostIds,
            );
        }

        $updated = $host->with(
            templateIds: $this->resolve(
                $command->templateIds,
                $host->templateIds,
                HostTemplateId::class,
                function (array $ids) use ($host): void {
                    $this->assertTemplatesExist($ids);
                    // The templates the host already has are kept, even those it could no longer be given.
                    $this->templateLicenseGuard->assertLicensed(new Collection($ids, HostTemplateId::class), $host->templateIds);
                },
            ),
            hostGroupIds: $this->resolveScoped(
                $command->hostGroupIds,
                $host->hostGroupIds,
                HostGroupId::class,
                fn (): ?Collection => $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId) : null,
                fn (array $ids, ?Collection $accessible) => $this->assertHostGroupsAccessible($ids, $accessible),
            ),
            categoryIds: $this->resolveScoped(
                $command->categoryIds,
                $host->categoryIds,
                HostCategoryId::class,
                fn (): ?Collection => $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostCategoryIds($viewerId) : null,
                fn (array $ids, ?Collection $accessible) => $this->assertCategoriesAccessible($ids, $accessible),
            ),
            parentHostIds: $parentHostIds,
            childHostIds: $childHostIds,
        );

        return $this->withContacts($updated, $command);
    }

    private function withContacts(Host $host, PatchHostCommand $command): Host
    {
        if ($command->contactIds instanceof NoValue && $command->contactGroupIds instanceof NoValue) {
            return $host;
        }

        $viewerId = $command->viewerId;
        $notifications = $host->notifications ?? Notifications::default();

        return $host->with(notifications: $notifications->with(
            contactIds: $this->resolveScoped(
                $command->contactIds,
                $notifications->contactIds,
                NotificationContactId::class,
                fn (): ?Collection => $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleContactIds($viewerId) : null,
                fn (array $ids, ?Collection $accessible) => $this->assertContactsAccessible($ids, $accessible),
            ),
            contactGroupIds: $this->resolveScoped(
                $command->contactGroupIds,
                $notifications->contactGroupIds,
                ContactGroupId::class,
                fn (): ?Collection => $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleContactGroupIds($viewerId) : null,
                fn (array $ids, ?Collection $accessible) => $this->assertContactGroupsAccessible($ids, $accessible),
            ),
        ));
    }

    /**
     * Like {@see resolve()} for a relation scoped by what the requester can see: the list of what they
     * can see is loaded once, and only when the relation is changed, to check what is brought and to
     * tell what is left in place.
     *
     * @template T of AggregateRootId
     *
     * @param NoValue|ListChange<T> $change
     * @param Collection<T> $current
     * @param class-string<T> $className
     * @param \Closure(): (Collection<T>|null) $loadAccessible null when the requester can see everything
     * @param \Closure(list<T>, Collection<T>|null): void $assertExist
     *
     * @return NoValue|Collection<T>
     */
    private function resolveScoped(NoValue|ListChange $change, Collection $current, string $className, \Closure $loadAccessible, \Closure $assertExist): NoValue|Collection
    {
        if ($change instanceof NoValue) {
            return $change;
        }

        $accessible = $loadAccessible();

        return $this->resolve(
            $change,
            $current,
            $className,
            static fn (array $ids) => $assertExist($ids, $accessible),
            $this->visibleIn($accessible),
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

        if ($change->mode !== ListChangeModeEnum::Remove) {
            $assertExist($change->values);
        }

        return new Collection(
            $change->applyTo(array_values($current->toArray()), static fn (AggregateRootId $id): int => $id->value, $isVisible),
            $className,
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T>|null $accessible null when the requester can see everything
     *
     * @return (\Closure(T): bool)|null
     */
    private function visibleIn(?Collection $accessible): ?\Closure
    {
        if (! $accessible instanceof Collection) {
            return null;
        }

        $visibleIds = array_map(static fn (AggregateRootId $id): int => $id->value, $accessible->toArray());

        return static fn (AggregateRootId $id): bool => in_array($id->value, $visibleIds, true);
    }

    /**
     * @param list<HostGroupId> $ids
     * @param Collection<HostGroupId>|null $accessible null when the requester can see everything
     */
    private function assertHostGroupsAccessible(array $ids, ?Collection $accessible): void
    {
        $missingIds = $this->missingIds(
            $ids,
            array_keys($this->hostGroupRepository->findNamesByIds(new Collection($ids, HostGroupId::class))->toArray()),
            $accessible,
        );

        if ($missingIds !== []) {
            throw new HostGroupNotFoundException(['hostGroupIds' => $missingIds]);
        }
    }

    /**
     * @param list<HostTemplateId> $ids
     */
    private function assertTemplatesExist(array $ids): void
    {
        $missingIds = $this->missingIds(
            $ids,
            array_keys($this->hostTemplateRepository->findNamesByIds(new Collection($ids, HostTemplateId::class))->toArray()),
        );

        if ($missingIds !== []) {
            throw new HostTemplateNotFoundException($missingIds);
        }
    }

    /**
     * @param list<HostCategoryId> $ids
     * @param Collection<HostCategoryId>|null $accessible null when the requester can see everything
     */
    private function assertCategoriesAccessible(array $ids, ?Collection $accessible): void
    {
        $missingIds = $this->missingIds(
            $ids,
            array_keys($this->hostCategoryRepository->findNamesByIds(new Collection($ids, HostCategoryId::class))->toArray()),
            $accessible,
        );

        if ($missingIds !== []) {
            throw new HostCategoryNotFoundException($missingIds);
        }
    }

    /**
     * @param list<HostId> $ids
     * @param list<int> $reachableHostIds the hosts that exist and the requester can see
     */
    private function assertHostsExist(array $ids, string $criterion, array $reachableHostIds): void
    {
        $missingIds = $this->missingIds($ids, $reachableHostIds);

        if ($missingIds !== []) {
            throw new HostNotFoundException($missingIds, $criterion);
        }
    }

    /**
     * Among the hosts this one is linked to and the ones the update brings or removes, the ones that exist
     * and the requester can see, found in one lookup: the others do not exist for them, and are left in
     * place by a replacement and left alone by a removal, like the other scoped relations.
     *
     * @return list<int>
     */
    private function reachableHostIds(Host $host, PatchHostCommand $command, ?UserId $viewerId): array
    {
        $changes = array_filter([$command->parentHostIds, $command->childHostIds], static fn (NoValue|ListChange $change): bool => $change instanceof ListChange);
        if ($changes === []) {
            return [];
        }

        $ids = [...$host->parentHostIds->toArray(), ...$host->childHostIds->toArray()];
        foreach ($changes as $change) {
            $ids = [...$ids, ...$change->values];
        }

        return array_map(
            intval(...),
            array_keys($this->hostRepository->findNamesByIds(new Collection($ids, HostId::class), $viewerId)->toArray()),
        );
    }

    /**
     * @param list<NotificationContactId> $ids
     * @param Collection<NotificationContactId>|null $accessible null when the requester can see everything
     */
    private function assertContactsAccessible(array $ids, ?Collection $accessible): void
    {
        $missingIds = $this->missingIds(
            $ids,
            array_keys($this->contactRepository->findNamesByIds(new Collection($ids, NotificationContactId::class))->toArray()),
            $accessible,
        );

        if ($missingIds !== []) {
            throw new NotificationContactNotFoundException($missingIds);
        }
    }

    /**
     * @param list<ContactGroupId> $ids
     * @param Collection<ContactGroupId>|null $accessible null when the requester can see everything
     */
    private function assertContactGroupsAccessible(array $ids, ?Collection $accessible): void
    {
        $missingIds = $this->missingIds(
            $ids,
            array_keys($this->contactGroupRepository->findNamesByIds(new Collection($ids, ContactGroupId::class))->toArray()),
            $accessible,
        );

        if ($missingIds !== []) {
            throw new ContactGroupNotFoundException($missingIds);
        }
    }

    /**
     * @template T of AggregateRootId
     *
     * @param list<T> $ids
     * @param list<int|string> $foundIds the ids that exist
     * @param Collection<T>|null $accessible null when the requester can see everything
     *
     * @return list<int>
     */
    private function missingIds(array $ids, array $foundIds, ?Collection $accessible = null): array
    {
        $requestedIds = array_map(static fn (AggregateRootId $id): int => $id->value, $ids);
        $missingIds = array_diff($requestedIds, array_map('intval', $foundIds));

        if ($accessible instanceof Collection) {
            $accessibleIds = array_map(static fn (AggregateRootId $id): int => $id->value, $accessible->toArray());
            $missingIds = array_merge($missingIds, array_diff($requestedIds, $accessibleIds));
        }

        return array_values(array_unique($missingIds));
    }

    /**
     * A loop is closed when a requested child can still reach a requested parent through the rest of the
     * graph. The edited host's own parent and child edges are about to be replaced, so they are left out of
     * the traversal: otherwise an edge about to disappear would raise a loop that is not there.
     * findAncestorIds() includes its inputs, so a host on both sides falls out too.
     *
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    private function assertRelationsAreNotCircular(HostId $hostId, Collection $parentHostIds, Collection $childHostIds): void
    {
        foreach ([...$parentHostIds->toArray(), ...$childHostIds->toArray()] as $relatedId) {
            if ($relatedId->value === $hostId->value) {
                throw new CircularHostRelationException([$hostId->value]);
            }
        }

        if (count($parentHostIds) === 0 || count($childHostIds) === 0) {
            return;
        }

        $ancestorIds = array_map(
            static fn (HostId $id): int => $id->value,
            $this->hostRepository->findAncestorIds($parentHostIds, $hostId)->toArray(),
        );
        $childIds = array_map(static fn (HostId $id): int => $id->value, $childHostIds->toArray());

        $offendingIds = array_intersect($childIds, $ancestorIds);
        if ($offendingIds !== []) {
            throw new CircularHostRelationException(array_values($offendingIds));
        }
    }
}
