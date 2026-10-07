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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Infrastructure\TransformerInterface;
use Webmozart\Assert\Assert;

/**
 * Owns the wire representation of the notification block, in both directions: the domain enums
 * carry no string of their own, so this is the only place that knows an API client says "down"
 * and "use_default".
 *
 * A reference missing from the name lookups (deleted between validation and the response) is
 * dropped from the output rather than failing the whole response.
 *
 * @phpstan-type ExtraDataTypeAlias array{
 *     contactNames?: array<int, NotificationContactName>,
 *     contactGroupNames?: array<int, ContactGroupName>,
 *     timePeriodNames?: array<int, TimePeriodName>,
 * }
 *
 * @implements TransformerInterface<?Notifications, ?HostNotificationsOutput, ExtraDataTypeAlias>
 */
final readonly class HostNotificationsTransformer implements TransformerInterface
{
    public function transform(mixed $from, array $extraData = []): ?HostNotificationsOutput
    {
        if (! $from instanceof Notifications) {
            return null;
        }

        Assert::keyExists($extraData, 'contactNames');
        Assert::keyExists($extraData, 'contactGroupNames');
        Assert::keyExists($extraData, 'timePeriodNames');

        $contacts = [];
        foreach ($from->contactIds as $contactId) {
            if (isset($extraData['contactNames'][$contactId->value])) {
                $contacts[] = new NotificationContactResource($contactId->value, $extraData['contactNames'][$contactId->value]->value);
            }
        }

        $contactGroups = [];
        foreach ($from->contactGroupIds as $contactGroupId) {
            if (isset($extraData['contactGroupNames'][$contactGroupId->value])) {
                $contactGroups[] = new ContactGroupResource($contactGroupId->value, $extraData['contactGroupNames'][$contactGroupId->value]->value);
            }
        }

        return new HostNotificationsOutput(
            enabled: $from->enabled->value,
            contacts: $contacts,
            contactGroups: $contactGroups,
            options: array_map(NotificationOptionEnumResolver::toString(...), $from->options),
            interval: $from->interval,
            timeperiod: $this->resolveTimePeriod($from->periodId, $extraData['timePeriodNames']),
            firstDelay: $from->firstDelay,
            recoveryDelay: $from->recoveryDelay,
            contactAdditiveInheritance: $from->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $from->contactGroupAdditiveInheritance,
        );
    }

    /**
     * @param array<int, TimePeriodName> $timePeriodNames
     */
    private function resolveTimePeriod(?TimePeriodId $periodId, array $timePeriodNames): ?TimePeriodResource
    {
        if (! $periodId instanceof TimePeriodId || ! isset($timePeriodNames[$periodId->value])) {
            return null;
        }

        return new TimePeriodResource($periodId->value, $timePeriodNames[$periodId->value]->value);
    }
}
