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
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
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

        $details = match ($action) {
            ActionEnum::Add, ActionEnum::Update => $this->details($aggregate),
            ActionEnum::Delete, ActionEnum::Enable, ActionEnum::Disable => [],
        };

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
     * Legacy records the properties of a host only when it is created (and their diff when it is
     * updated): deleting, enabling or disabling one logs the bare action, without details.
     *
     * The keys and formats of the properties below are those written by the legacy creation path
     * (Core\Host\Infrastructure\Repository\DbWriteHostActionLogRepository): an unset value is an
     * empty string and a tri-state is 0, 1 or 2. Relations (templates, groups, categories, parents,
     * children) and macros are not part of that log. The SNMP community is logged as stored, that is
     * the `secret::` reference once vaulted.
     *
     * @return array<string, string>
     */
    private function details(Host $host): array
    {
        return [
            'host_name' => $host->name->value,
            'host_alias' => $host->alias->value ?? '',
            'host_address' => $host->address->value,
            'host_activate' => $host->activated ? '1' : '0',
            'nagios_server_id' => (string) $host->pollerId->value,
            'host_snmp_version' => $host->snmpVersion->value ?? '',
            'host_snmp_community' => $host->snmpCommunity->value ?? '',
            'host_location' => (string) $host->timezoneId?->value,
            'severity_id' => (string) $host->severityId?->value,
            ...$this->extendedInformationDetails($host->extendedInformations),
            ...$this->schedulingDetails($host->schedulingOptions),
            ...$this->dataProcessingDetails($host->dataProcessing),
            'command_command_id' => (string) $host->checkOptions->checkCommandId?->value,
            'command_command_id_arg1' => $this->arguments($host->checkOptions->args),
            ...$this->notificationDetails($host->notifications),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extendedInformationDetails(?ExtendedInformations $informations): array
    {
        return [
            'ehi_notes_url' => $informations->noteUrl ?? '',
            'ehi_notes' => $informations->note ?? '',
            'ehi_action_url' => $informations->actionUrl ?? '',
            'ehi_icon_image' => (string) $informations?->iconId?->value,
            'ehi_icon_image_alt' => $informations->altIcon ?? '',
            'host_comment' => $informations->comment ?? '',
            'geo_coords' => (string) $informations?->geoCoordinates,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function schedulingDetails(SchedulingOptions $options): array
    {
        return [
            'timeperiod_tp_id' => (string) $options->checkTimeperiodId?->value,
            'host_max_check_attempts' => (string) $options->maxCheckAttempts,
            'host_check_interval' => (string) $options->normalCheckInterval,
            'host_retry_check_interval' => (string) $options->retryCheckInterval,
            'host_active_checks_enabled' => $this->triState($options->activeCheckEnabled),
            'host_passive_checks_enabled' => $this->triState($options->passiveCheckEnabled),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dataProcessingDetails(DataProcessing $dataProcessing): array
    {
        return [
            'host_check_freshness' => $this->triState($dataProcessing->checkFreshness),
            'host_freshness_threshold' => (string) $dataProcessing->freshnessThreshold,
            'host_flap_detection_enabled' => $this->triState($dataProcessing->flapDetectionEnabled),
            'host_low_flap_threshold' => (string) $dataProcessing->lowFlapThreshold,
            'host_high_flap_threshold' => (string) $dataProcessing->highFlapThreshold,
            'host_event_handler_enabled' => $this->triState($dataProcessing->eventHandlerEnabled),
            'command_command_id2' => (string) $dataProcessing->eventHandlerCommandId?->value,
            'command_command_id_arg2' => $this->arguments($dataProcessing->eventHandlerArgs),
            'host_acknowledgement_timeout' => (string) $dataProcessing->acknowledgmentTimeout,
        ];
    }

    private function triState(TriStateEnum $value): string
    {
        return match ($value) {
            TriStateEnum::False => '0',
            TriStateEnum::True => '1',
            TriStateEnum::UseDefault => '2',
        };
    }

    /**
     * Bang-prefixed and bang-joined, with the line breaks and tabs encoded, like legacy stores them.
     *
     * @param list<string> $arguments
     */
    private function arguments(array $arguments): string
    {
        if ($arguments === []) {
            return '';
        }

        return '!' . implode('!', str_replace(["\n", "\t", "\r"], ['#BR#', '#T#', '#R#'], $arguments));
    }

    /**
     * Keyed by legacy column name, and valued in the legacy storage format, like every other
     * entry above: Administration > Logs renders these rows as-is, so a reader has to recognise
     * what they see in the host form.
     *
     * The two format mappings are spelled out here rather than reused from the write side
     * (Shared\Infrastructure\Dbal\TriStateColumnTrait for the tri-state,
     * MonitoringConfiguration\Infrastructure\Dbal\DbalNotificationsTransformer for the options):
     * this factory lives in Domain and cannot depend on Infrastructure code. Any change to a storage format has to be made in both places — the same constraint
     * `host_activate` above is already under.
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
                static fn (NotificationOptionEnum $option): string => match ($option) {
                    NotificationOptionEnum::Down => 'd',
                    NotificationOptionEnum::Unreachable => 'u',
                    NotificationOptionEnum::Recovery => 'r',
                    NotificationOptionEnum::Flapping => 'f',
                    NotificationOptionEnum::DowntimeScheduled => 's',
                    NotificationOptionEnum::None => 'n',
                },
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
