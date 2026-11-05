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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements ActivityLogFactoryInterface<HostTemplate>
 */
#[AsTaggedItem(index: HostTemplate::class)]
final readonly class HostTemplateActivityLogFactory implements ActivityLogFactoryInterface
{
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {
        $details = match ($action) {
            ActionEnum::Add, ActionEnum::Update => $this->details($aggregate),
            ActionEnum::Delete, ActionEnum::Enable, ActionEnum::Disable, ActionEnum::MassChange => [],
        };

        return new ActivityLog(
            id: null,
            action: $action,
            actor: $firedBy,
            target: new Target(
                id: new TargetId($aggregate->id()->value),
                name: new TargetName($aggregate->name->value),
                type: TargetTypeEnum::HostTemplate,
            ),
            performedAt: $firedAt,
            details: $details,
        );
    }

    /**
     * The host log keys and storage formats (an unset value is an empty string, a tri-state 0, 1 or 2),
     * without the host-only ones. Relations and macros are not logged; the SNMP community is logged as
     * stored, the `secret::` reference once vaulted.
     *
     * @return array<string, string>
     */
    private function details(HostTemplate $template): array
    {
        return [
            'host_name' => $template->name->value,
            'host_alias' => $template->alias->value ?? '',
            'host_snmp_version' => $template->snmpVersion->value ?? '',
            'host_snmp_community' => $template->snmpCommunity->value ?? '',
            'host_location' => (string) $template->timezoneId?->value,
            'severity_id' => (string) $template->severityId?->value,
            ...$this->extendedInformationDetails($template->extendedInformations),
            ...$this->schedulingDetails($template->schedulingOptions),
            ...$this->dataProcessingDetails($template->dataProcessing),
            'command_command_id' => (string) $template->checkOptions->checkCommandId?->value,
            'command_command_id_arg1' => $this->arguments($template->checkOptions->args),
            ...$this->notificationDetails($template->notifications),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extendedInformationDetails(?HostTemplateExtendedInformations $informations): array
    {
        return [
            'ehi_notes_url' => $informations->noteUrl ?? '',
            'ehi_notes' => $informations->note ?? '',
            'ehi_action_url' => $informations->actionUrl ?? '',
            'ehi_icon_image' => (string) $informations?->iconId?->value,
            'ehi_icon_image_alt' => $informations->altIcon ?? '',
            'host_comment' => $informations->comment ?? '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function schedulingDetails(HostTemplateSchedulingOptions $options): array
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
    private function dataProcessingDetails(HostTemplateDataProcessing $dataProcessing): array
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

    /**
     * @return array<string, string>
     */
    private function notificationDetails(?HostTemplateNotifications $notifications): array
    {
        if (! $notifications instanceof HostTemplateNotifications) {
            return [];
        }

        return [
            'host_notifications_enabled' => $this->triState($notifications->enabled),
            'host_notification_options' => implode(',', array_map(
                static fn (HostTemplateNotificationOptionEnum $option): string => match ($option) {
                    HostTemplateNotificationOptionEnum::Down => 'd',
                    HostTemplateNotificationOptionEnum::Unreachable => 'u',
                    HostTemplateNotificationOptionEnum::Recovery => 'r',
                    HostTemplateNotificationOptionEnum::Flapping => 'f',
                    HostTemplateNotificationOptionEnum::DowntimeScheduled => 's',
                    HostTemplateNotificationOptionEnum::None => 'n',
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

    private function triState(TriStateEnum $value): string
    {
        return match ($value) {
            TriStateEnum::False => '0',
            TriStateEnum::True => '1',
            TriStateEnum::UseDefault => '2',
        };
    }

    /**
     * Bang-prefixed and bang-joined, line breaks and tabs encoded, as stored.
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
}
