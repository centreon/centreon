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

namespace App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements ActivityLogFactoryInterface<Host>
 */
#[AsTaggedItem(index: Host::class)]
final readonly class HostActivityLogFactory implements ActivityLogFactoryInterface
{
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {
        $target = new Target(
            id: new TargetId($aggregate->id()->value),
            name: new TargetName($aggregate->name->value),
            type: TargetTypeEnum::Host,
        );

        $details = [
            'host_name' => $aggregate->name->value,
            'host_address' => $aggregate->address->value,
            'host_activate' => $aggregate->activated ? '1' : '0',
            ...$this->notificationDetails($aggregate->notifications),
        ];

        return new ActivityLog(
            id: null,
            action: $action,
            actor: $firedBy,
            target: $target,
            performedAt: $firedAt,
            details: $details,
        );
    }

    /**
     * Keyed by legacy column name, and valued in the legacy storage format, like every other
     * entry above: Administration > Logs renders these rows as-is, so a reader has to recognise
     * what they see in the host form.
     *
     * @return array<string, string>
     */
    private function notificationDetails(?Notifications $notifications): array
    {
        if (! $notifications instanceof Notifications) {
            return [];
        }

        return [
            'host_notifications_enabled' => match ($notifications->enabled) {
                TriStateEnum::False => '0',
                TriStateEnum::True => '1',
                TriStateEnum::UseDefault => '2',
            },
            'host_notification_options' => implode(',', array_map(
                static fn (NotificationOptionEnum $option): string => $option->value,
                $notifications->options,
            )),
            'host_notification_interval' => (string) $notifications->interval,
            'timeperiod_tp_id2' => (string) $notifications->periodId?->value,
            'host_first_notification_delay' => (string) $notifications->firstDelay,
            'host_recovery_notification_delay' => (string) $notifications->recoveryDelay,
            'contact_additive_inheritance' => $notifications->contactAdditiveInheritance ? '1' : '0',
            'cg_additive_inheritance' => $notifications->contactGroupAdditiveInheritance ? '1' : '0',
            'host_cs' => implode(',', array_map(
                static fn (NotificationContactId $id): int => $id->value,
                $notifications->contactIds->toArray(),
            )),
            'host_cgs' => implode(',', array_map(
                static fn (ContactGroupId $id): int => $id->value,
                $notifications->contactGroupIds->toArray(),
            )),
        ];
    }
}
