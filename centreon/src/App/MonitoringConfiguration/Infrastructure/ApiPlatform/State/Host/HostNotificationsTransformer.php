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

use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationContactGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationContactOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationPeriodOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
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
            contacts: $this->transformContacts($from),
            contactGroups: $this->transformContactGroups($from),
            options: array_map(self::optionToApi(...), $from->options),
            interval: $from->interval,
            period: $this->transformPeriod($from->periodId),
            firstDelay: $from->firstDelay,
            recoveryDelay: $from->recoveryDelay,
            contactAdditiveInheritance: $from->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $from->contactGroupAdditiveInheritance,
        );
    }

    public static function optionToApi(NotificationOptionEnum $option): string
    {
        return match ($option) {
            NotificationOptionEnum::Down => 'down',
            NotificationOptionEnum::Unreachable => 'unreachable',
            NotificationOptionEnum::Recovery => 'recovery',
            NotificationOptionEnum::Flapping => 'flapping',
            NotificationOptionEnum::DowntimeScheduled => 'downtime_scheduled',
            NotificationOptionEnum::None => 'none',
        };
    }

    /**
     * Only ever called on a value the `Assert\Choice` on the input DTO already accepted, which is
     * why an unknown string is a programming error rather than a client one.
     */
    public static function optionFromApi(string $option): NotificationOptionEnum
    {
        return match ($option) {
            'down' => NotificationOptionEnum::Down,
            'unreachable' => NotificationOptionEnum::Unreachable,
            'recovery' => NotificationOptionEnum::Recovery,
            'flapping' => NotificationOptionEnum::Flapping,
            'downtime_scheduled' => NotificationOptionEnum::DowntimeScheduled,
            'none' => NotificationOptionEnum::None,
            default => throw new \ValueError(sprintf('"%s" is not a valid notification option.', $option)),
        };
    }

    /**
     * The choices the API accepts, so the input DTO and this mapping cannot drift apart.
     *
     * @return list<string>
     */
    public static function apiOptions(): array
    {
        return array_map(self::optionToApi(...), NotificationOptionEnum::cases());
    }

    /**
     * @return list<HostNotificationContactOutput>
     */
    private function transformContacts(Notifications $notifications): array
    {
        $names = $this->contactRepository->findNamesByIds($notifications->contactIds)->toArray();

        $contacts = [];
        foreach ($notifications->contactIds as $contactId) {
            if (isset($names[$contactId->value])) {
                $contacts[] = new HostNotificationContactOutput($contactId->value, $names[$contactId->value]->value);
            }
        }

        return $contacts;
    }

    /**
     * @return list<HostNotificationContactGroupOutput>
     */
    private function transformContactGroups(Notifications $notifications): array
    {
        $names = $this->contactGroupRepository->findNamesByIds($notifications->contactGroupIds)->toArray();

        $contactGroups = [];
        foreach ($notifications->contactGroupIds as $contactGroupId) {
            if (isset($names[$contactGroupId->value])) {
                $contactGroups[] = new HostNotificationContactGroupOutput($contactGroupId->value, $names[$contactGroupId->value]->value);
            }
        }

        return $contactGroups;
    }

    private function transformPeriod(?TimePeriodId $periodId): ?HostNotificationPeriodOutput
    {
        if (! $periodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository->findNamesByIds(new Collection([$periodId], TimePeriodId::class))->toArray()[$periodId->value] ?? null;

        return $name === null ? null : new HostNotificationPeriodOutput($periodId->value, $name->value);
    }
}
