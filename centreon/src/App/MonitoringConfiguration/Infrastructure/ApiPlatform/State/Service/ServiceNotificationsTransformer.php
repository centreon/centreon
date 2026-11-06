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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * The wire representation of a service's notifications: the domain enums carry no string of their own, so this
 * is the place that knows an API client says "warning" and "use_default". A reference whose name no longer
 * resolves is dropped.
 *
 * @implements TransformerInterface<?ServiceNotifications, ?ServiceNotificationsOutput>
 */
final readonly class ServiceNotificationsTransformer implements TransformerInterface
{
    public function __construct(
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private TimePeriodRepository $timePeriodRepository,
    ) {
    }

    public function transform(mixed $from): ?ServiceNotificationsOutput
    {
        if (! $from instanceof ServiceNotifications) {
            return null;
        }

        return new ServiceNotificationsOutput(
            enabled: $from->enabled->value,
            useOnlyContactsFromHost: $from->useOnlyContactsFromHost,
            contactAdditiveInheritance: $from->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $from->contactGroupAdditiveInheritance,
            contacts: $this->resolveContacts($from),
            contactGroups: $this->resolveContactGroups($from),
            interval: $from->interval,
            timeperiod: $this->resolveTimePeriod($from->periodId),
            options: array_map(ServiceNotificationOptionEnumResolver::toString(...), $from->options),
            firstDelay: $from->firstDelay,
            recoveryDelay: $from->recoveryDelay,
        );
    }

    /**
     * @return list<NotificationContactResource>
     */
    private function resolveContacts(ServiceNotifications $notifications): array
    {
        $names = $this->contactRepository->findNamesByIds($notifications->contactIds)->toArray();
        $contacts = [];
        foreach ($notifications->contactIds as $contactId) {
            if (isset($names[$contactId->value])) {
                $contacts[] = new NotificationContactResource($contactId->value, $names[$contactId->value]->value);
            }
        }

        return $contacts;
    }

    /**
     * @return list<ContactGroupResource>
     */
    private function resolveContactGroups(ServiceNotifications $notifications): array
    {
        $names = $this->contactGroupRepository->findNamesByIds($notifications->contactGroupIds)->toArray();
        $contactGroups = [];
        foreach ($notifications->contactGroupIds as $contactGroupId) {
            if (isset($names[$contactGroupId->value])) {
                $contactGroups[] = new ContactGroupResource($contactGroupId->value, $names[$contactGroupId->value]->value);
            }
        }

        return $contactGroups;
    }

    private function resolveTimePeriod(?TimePeriodId $periodId): ?TimePeriodResource
    {
        if (! $periodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository->findNamesByIds(new Collection([$periodId], TimePeriodId::class))->toArray()[$periodId->value] ?? null;

        return $name === null ? null : new TimePeriodResource($periodId->value, $name->value);
    }
}
