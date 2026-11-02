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
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements ActivityLogFactoryInterface<Service>
 */
#[AsTaggedItem(index: Service::class)]
final readonly class ServiceActivityLogFactory implements ActivityLogFactoryInterface
{
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {
        $target = new Target(
            id: new TargetId($aggregate->id()->value),
            name: new TargetName($aggregate->name->value),
            type: TargetTypeEnum::Service,
        );

        $details = match ($action) {
            ActionEnum::Add => $this->details($aggregate),
            ActionEnum::Update, ActionEnum::MassChange, ActionEnum::Delete, ActionEnum::Enable, ActionEnum::Disable => [
                'service_name' => $aggregate->name->value,
                'host_id' => (string) $aggregate->hostId->value,
            ],
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
     * Every field of the form of a created service, and its host, keyed by legacy column name and valued in the
     * legacy storage format, like {@see HostActivityLogFactory}: an unset value is an empty string, a tri-state
     * is 0, 1 or 2, options are the engine letters, arguments are bang-joined and encoded. Service groups,
     * categories, severity, traps and macros are not part of the log, so no macro password can appear in it.
     *
     * The storage formats are spelled out here rather than reused from the write side, which lives in
     * Infrastructure: a change to one of them has to be made in both places.
     *
     * @return array<string, string>
     */
    private function details(Service $service): array
    {
        return [
            'service_description' => $service->name->value,
            'service_activate' => $service->activated ? '1' : '0',
            'service_template_model_stm_id' => (string) $service->templateId?->value,
            'command_command_id' => (string) $service->checkOptions->checkCommandId?->value,
            'command_command_id_arg' => $this->arguments($service->checkOptions->args),
            ...$this->schedulingDetails($service->schedulingOptions),
            ...$this->notificationDetails($service->notifications),
            ...$this->dataProcessingDetails($service->dataProcessing),
            ...$this->extendedInformationDetails($service->extendedInformations),
            'graph_id' => (string) $service->graphTemplateId?->value,
            'geo_coords' => (string) $service->extendedInformations?->geoCoordinates,
            'service_comment' => $service->extendedInformations->comment ?? '',
            'host_id' => (string) $service->hostId->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function schedulingDetails(ServiceSchedulingOptions $options): array
    {
        return [
            'timeperiod_tp_id' => (string) $options->checkTimeperiodId?->value,
            'service_max_check_attempts' => (string) $options->maxCheckAttempts,
            'service_normal_check_interval' => (string) $options->normalCheckInterval,
            'service_retry_check_interval' => (string) $options->retryCheckInterval,
            'service_active_checks_enabled' => $this->triState($options->activeCheckEnabled),
            'service_passive_checks_enabled' => $this->triState($options->passiveCheckEnabled),
            'service_is_volatile' => $this->triState($options->isVolatile),
        ];
    }

    /**
     * No key at all without notifications block (Cloud), as for hosts.
     *
     * @return array<string, string>
     */
    private function notificationDetails(?ServiceNotifications $notifications): array
    {
        if (! $notifications instanceof ServiceNotifications) {
            return [];
        }

        return [
            'service_notifications_enabled' => $this->triState($notifications->enabled),
            'service_use_only_contacts_from_host' => $notifications->useOnlyContactsFromHost ? '1' : '0',
            'contact_additive_inheritance' => $notifications->contactAdditiveInheritance ? '1' : '0',
            'cg_additive_inheritance' => $notifications->contactGroupAdditiveInheritance ? '1' : '0',
            'service_cs' => implode(',', array_map(
                static fn (NotificationContactId $id): int => $id->value,
                $notifications->contactIds->toArray(),
            )),
            'service_cgs' => implode(',', array_map(
                static fn (ContactGroupId $id): int => $id->value,
                $notifications->contactGroupIds->toArray(),
            )),
            'service_notification_interval' => (string) $notifications->interval,
            'timeperiod_tp_id2' => (string) $notifications->periodId?->value,
            'service_notification_options' => implode(',', array_map(
                static fn (ServiceNotificationOptionEnum $option): string => match ($option) {
                    ServiceNotificationOptionEnum::Warning => 'w',
                    ServiceNotificationOptionEnum::Unknown => 'u',
                    ServiceNotificationOptionEnum::Critical => 'c',
                    ServiceNotificationOptionEnum::Recovery => 'r',
                    ServiceNotificationOptionEnum::Flapping => 'f',
                    ServiceNotificationOptionEnum::DowntimeScheduled => 's',
                    ServiceNotificationOptionEnum::None => 'n',
                },
                $notifications->options,
            )),
            'service_first_notification_delay' => (string) $notifications->firstDelay,
            'service_recovery_notification_delay' => (string) $notifications->recoveryDelay,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dataProcessingDetails(ServiceDataProcessing $dataProcessing): array
    {
        return [
            'service_obsess_over_service' => $this->triState($dataProcessing->obsessOverService),
            'service_acknowledgement_timeout' => (string) $dataProcessing->acknowledgmentTimeout,
            'service_check_freshness' => $this->triState($dataProcessing->checkFreshness),
            'service_freshness_threshold' => (string) $dataProcessing->freshnessThreshold,
            'service_flap_detection_enabled' => $this->triState($dataProcessing->flapDetectionEnabled),
            'service_low_flap_threshold' => (string) $dataProcessing->lowFlapThreshold,
            'service_high_flap_threshold' => (string) $dataProcessing->highFlapThreshold,
            'service_retain_status_information' => $this->triState($dataProcessing->retainStatusInformation),
            'service_retain_nonstatus_information' => $this->triState($dataProcessing->retainNonStatusInformation),
            'service_stalking_options' => implode(',', array_map(
                static fn (ServiceStalkingOptionEnum $option): string => match ($option) {
                    ServiceStalkingOptionEnum::Ok => 'o',
                    ServiceStalkingOptionEnum::Warning => 'w',
                    ServiceStalkingOptionEnum::Unknown => 'u',
                    ServiceStalkingOptionEnum::Critical => 'c',
                },
                $dataProcessing->stalkingOptions,
            )),
            'service_event_handler_enabled' => $this->triState($dataProcessing->eventHandlerEnabled),
            'command_command_id2' => (string) $dataProcessing->eventHandlerCommandId?->value,
            'command_command_id_arg2' => $this->arguments($dataProcessing->eventHandlerArgs),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extendedInformationDetails(?ServiceExtendedInformations $informations): array
    {
        return [
            'esi_notes_url' => $informations->noteUrl ?? '',
            'esi_notes' => $informations->note ?? '',
            'esi_action_url' => $informations->actionUrl ?? '',
            'esi_icon_image' => (string) $informations?->iconId?->value,
            'esi_icon_image_alt' => $informations->altIcon ?? '',
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
}
