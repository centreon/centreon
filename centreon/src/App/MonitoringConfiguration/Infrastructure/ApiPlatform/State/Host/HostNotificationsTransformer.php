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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Owns the wire representation of the notification block, in both directions: the domain enums
 * carry no string of their own, so this is the only place that knows an API client says "down"
 * and "use_default".
 *
 * @implements TransformerInterface<?Notifications, ?HostNotificationsOutput>
 */
final readonly class HostNotificationsTransformer implements TransformerInterface
{
    public function __construct(
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private TimePeriodRepository $timePeriodRepository,
    ) {
    }

    public function transform(mixed $from): ?HostNotificationsOutput
    {
        if (! $from instanceof Notifications) {
            return null;
        }

        return new HostNotificationsOutput(
            enabled: $from->enabled->value,
            contacts: $this->resolveContacts($from),
            contactGroups: $this->resolveNotificationContactGroups($from),
            options: array_map(NotificationOptionEnumResolver::toString(...), $from->options),
            interval: $from->interval,
            timeperiod: $this->resolveTimePeriod($from->periodId),
            firstDelay: $from->firstDelay,
            recoveryDelay: $from->recoveryDelay,
            contactAdditiveInheritance: $from->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $from->contactGroupAdditiveInheritance,
        );
    }

    /**
     * @return list<NotificationContactResource>
     */
    private function resolveContacts(Notifications $notifications): array
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
    private function resolveNotificationContactGroups(Notifications $notifications): array
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
