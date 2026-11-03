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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Webmozart\Assert\Assert;

/**
 * The service's notification settings and its contact / contact group links.
 */
final readonly class ServiceNotifications
{
    public const DEFAULT_ADDITIVE_INHERITANCE = false;
    public const MIN_INTERVAL = 0;
    public const MIN_FIRST_DELAY = 0;
    public const MIN_RECOVERY_DELAY = 0;

    /** @var Collection<NotificationContactId> */
    public Collection $contactIds;

    /** @var Collection<ContactGroupId> */
    public Collection $contactGroupIds;

    /** @var list<ServiceNotificationOptionEnum> */
    public array $options;

    /**
     * @param Collection<NotificationContactId> $contactIds duplicates are collapsed
     * @param Collection<ContactGroupId> $contactGroupIds duplicates are collapsed
     * @param list<ServiceNotificationOptionEnum> $options duplicates are collapsed;
     *                                                     {@see ServiceNotificationOptionEnum::None} is exclusive
     * @param bool $useOnlyContactsFromHost when true, the engine notifies the contacts of the host only
     */
    public function __construct(
        public TriStateEnum $enabled,
        Collection $contactIds,
        Collection $contactGroupIds,
        array $options = [],
        public ?int $interval = null,
        public ?TimePeriodId $periodId = null,
        public ?int $firstDelay = null,
        public ?int $recoveryDelay = null,
        public bool $contactAdditiveInheritance = self::DEFAULT_ADDITIVE_INHERITANCE,
        public bool $contactGroupAdditiveInheritance = self::DEFAULT_ADDITIVE_INHERITANCE,
        public bool $useOnlyContactsFromHost = false,
    ) {
        // The relation tables carry no unique index, so a repeated id would otherwise become a duplicate row.
        $this->contactIds = $this->uniqueIds($contactIds, NotificationContactId::class);
        $this->contactGroupIds = $this->uniqueIds($contactGroupIds, ContactGroupId::class);

        // Normalised to the enum's own declaration order, the only order legacy writes (w,u,c,r,f,s).
        $requested = [];
        foreach ($options as $option) {
            $requested[$option->name] = true;
        }
        $this->options = array_values(array_filter(
            ServiceNotificationOptionEnum::cases(),
            static fn (ServiceNotificationOptionEnum $option): bool => isset($requested[$option->name]),
        ));

        Assert::false(
            in_array(ServiceNotificationOptionEnum::None, $this->options, true) && count($this->options) > 1,
            'ServiceNotifications::options cannot combine the "none" option with any other option.',
        );

        Assert::nullOrGreaterThanEq($interval, self::MIN_INTERVAL);
        Assert::nullOrGreaterThanEq($firstDelay, self::MIN_FIRST_DELAY);
        Assert::nullOrGreaterThanEq($recoveryDelay, self::MIN_RECOVERY_DELAY);
    }

    /**
     * Same settings, other contacts and contact groups.
     *
     * @param Collection<NotificationContactId> $contactIds
     * @param Collection<ContactGroupId> $contactGroupIds
     */
    public function withContacts(Collection $contactIds, Collection $contactGroupIds): self
    {
        return new self(
            enabled: $this->enabled,
            contactIds: $contactIds,
            contactGroupIds: $contactGroupIds,
            options: $this->options,
            interval: $this->interval,
            periodId: $this->periodId,
            firstDelay: $this->firstDelay,
            recoveryDelay: $this->recoveryDelay,
            contactAdditiveInheritance: $this->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $this->contactGroupAdditiveInheritance,
            useOnlyContactsFromHost: $this->useOnlyContactsFromHost,
        );
    }

    /**
     * Additive inheritance off, for a platform whose inheritance_mode disables it.
     */
    public function withoutAdditiveInheritance(): self
    {
        return new self(
            enabled: $this->enabled,
            contactIds: $this->contactIds,
            contactGroupIds: $this->contactGroupIds,
            options: $this->options,
            interval: $this->interval,
            periodId: $this->periodId,
            firstDelay: $this->firstDelay,
            recoveryDelay: $this->recoveryDelay,
            contactAdditiveInheritance: false,
            contactGroupAdditiveInheritance: false,
            useOnlyContactsFromHost: $this->useOnlyContactsFromHost,
        );
    }

    /**
     * Clears the contacts and contact groups the scopes allow, and forces additive inheritance off; the hidden ones
     * stay linked.
     *
     * @param AccessScope<NotificationContactId> $contactScope
     * @param AccessScope<ContactGroupId> $contactGroupScope
     */
    public function withHostContactsOnly(AccessScope $contactScope, AccessScope $contactGroupScope): self
    {
        return new self(
            enabled: $this->enabled,
            contactIds: new Collection(
                array_values(array_filter(
                    $this->contactIds->toArray(),
                    static fn (NotificationContactId $id): bool => ! $contactScope->allows($id),
                )),
                NotificationContactId::class,
            ),
            contactGroupIds: new Collection(
                array_values(array_filter(
                    $this->contactGroupIds->toArray(),
                    static fn (ContactGroupId $id): bool => ! $contactGroupScope->allows($id),
                )),
                ContactGroupId::class,
            ),
            options: $this->options,
            interval: $this->interval,
            periodId: $this->periodId,
            firstDelay: $this->firstDelay,
            recoveryDelay: $this->recoveryDelay,
            contactAdditiveInheritance: false,
            contactGroupAdditiveInheritance: false,
            useOnlyContactsFromHost: true,
        );
    }

    /**
     * The contacts and the contact groups are compared as sets: they are relations of the service, whose order
     * means nothing.
     */
    public function equals(self $other): bool
    {
        return $this->enabled === $other->enabled
            && $this->options === $other->options
            && $this->interval === $other->interval
            && $this->periodId?->value === $other->periodId?->value
            && $this->firstDelay === $other->firstDelay
            && $this->recoveryDelay === $other->recoveryDelay
            && $this->contactAdditiveInheritance === $other->contactAdditiveInheritance
            && $this->contactGroupAdditiveInheritance === $other->contactGroupAdditiveInheritance
            && $this->useOnlyContactsFromHost === $other->useOnlyContactsFromHost
            && $this->sortedIdValues($this->contactIds) === $this->sortedIdValues($other->contactIds)
            && $this->sortedIdValues($this->contactGroupIds) === $this->sortedIdValues($other->contactGroupIds);
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function uniqueIds(Collection $ids, string $className): Collection
    {
        $uniqueIds = [];
        foreach ($ids as $id) {
            $uniqueIds[$id->value] ??= $id;
        }

        return new Collection(array_values($uniqueIds), $className);
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function sortedIdValues(Collection $ids): array
    {
        $values = array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
        sort($values);

        return $values;
    }
}
