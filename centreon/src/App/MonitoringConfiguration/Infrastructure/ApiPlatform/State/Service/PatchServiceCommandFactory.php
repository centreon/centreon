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

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceCheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Webmozart\Assert\Assert;

/**
 * Builds the command of a partial update from the validated Input DTO and the keys that were really sent: a key
 * left out becomes {@see NoValue}, a key sent as null clears the value when it can be cleared (a tri-state reads as
 * "use default"), anything else replaces it.
 *
 * The "monitoring_engine" section feeds the scheduling options and the notes, URLs, icon and coordinates; the
 * "advanced" one the data processing directives and the comment.
 */
final readonly class PatchServiceCommandFactory
{
    public function __construct(
        private ServiceMacroTransformer $macroTransformer,
    ) {
    }

    public function create(
        ServiceId $id,
        ActingUser $actor,
        PatchServiceInput $input,
        RequestPayload $payload,
    ): PatchServiceCommand {
        $engine = $payload->has('monitoring_engine') ? $this->required($input->monitoringEngine) : null;
        $advanced = $payload->has('advanced') ? $this->required($input->advanced) : null;
        $notifications = $payload->has('notifications') ? $this->required($input->notifications) : null;
        $notificationsSent = $payload->section('notifications');

        return new PatchServiceCommand(
            id: $id,
            actor: $actor,
            activated: $this->provided($payload, 'activated', fn (): bool => $this->required($input->activated)),
            // Stored as it is compared and measured by the input: trimmed, inner runs of spaces collapsed.
            name: $this->provided($payload, 'name', fn (): ServiceName => new ServiceName(CreateServiceInput::normalizeName($this->required($input->name)))),
            hostId: $this->provided($payload, 'host_id', fn (): HostId => new HostId($this->required($input->hostId))),
            templateId: $this->provided($payload, 'service_template_id', static fn (): ?ServiceTemplateId => $input->serviceTemplateId !== null ? new ServiceTemplateId($input->serviceTemplateId) : null),
            severityId: $this->provided($payload, 'severity_id', static fn (): ?ServiceSeverityId => $input->severityId !== null ? new ServiceSeverityId($input->severityId) : null),
            graphTemplateId: $this->provided($payload, 'graph_template_id', static fn (): ?GraphTemplateId => $input->graphTemplateId !== null ? new GraphTemplateId($input->graphTemplateId) : null),
            serviceGroupIds: $this->listChange($payload, 'service_group_ids', $input->serviceGroupIds, $input->serviceGroupIdsToAdd, $input->serviceGroupIdsToRemove, static fn (int $id): ServiceGroupId => new ServiceGroupId($id)),
            categoryIds: $this->listChange($payload, 'category_ids', $input->categoryIds, $input->categoryIdsToAdd, $input->categoryIdsToRemove, static fn (int $id): ServiceCategoryId => new ServiceCategoryId($id)),
            trapIds: $this->listChange($payload, 'trap_ids', $input->trapIds, $input->trapIdsToAdd, $input->trapIdsToRemove, static fn (int $id): TrapId => new TrapId($id)),
            checkOptions: $this->provided($payload, 'check_options', fn (): ServiceCheckOptionsChanges => $this->checkOptions(
                $this->required($input->checkOptions),
                $payload->section('check_options'),
            )),
            macros: $this->macroChanges($payload->section('check_options'), $input->checkOptions),
            schedulingOptions: $engine instanceof ServiceMonitoringEngineInput
                ? $this->schedulingOptions($engine, $payload->section('monitoring_engine'))
                : new NoValue(),
            extendedInformations: $engine instanceof ServiceMonitoringEngineInput || $advanced instanceof PatchServiceAdvancedInput
                ? $this->extendedInformations($engine, $payload->section('monitoring_engine'), $advanced, $payload->section('advanced'))
                : new NoValue(),
            notifications: $notifications instanceof PatchServiceNotificationsInput
                ? $this->notifications($notifications, $notificationsSent)
                : new NoValue(),
            contactIds: $notifications instanceof PatchServiceNotificationsInput
                ? $this->listChange($notificationsSent, 'contacts', $notifications->contacts, $notifications->contactsToAdd, $notifications->contactsToRemove, static fn (int $id): NotificationContactId => new NotificationContactId($id))
                : new NoValue(),
            contactGroupIds: $notifications instanceof PatchServiceNotificationsInput
                ? $this->listChange($notificationsSent, 'contact_groups', $notifications->contactGroups, $notifications->contactGroupsToAdd, $notifications->contactGroupsToRemove, static fn (int $id): ContactGroupId => new ContactGroupId($id))
                : new NoValue(),
            dataProcessing: $advanced instanceof PatchServiceAdvancedInput
                ? $this->dataProcessing($advanced, $payload->section('advanced'))
                : new NoValue(),
        );
    }

    private function checkOptions(PatchServiceCheckOptionsInput $data, RequestPayload $sent): ServiceCheckOptionsChanges
    {
        return new ServiceCheckOptionsChanges(
            checkCommandId: $this->provided($sent, 'command_id', static fn (): ?CommandId => $data->commandId !== null ? new CommandId($data->commandId) : null),
            args: $this->provided($sent, 'args', static fn (): array => array_values($data->args)),
        );
    }

    private function macroChanges(RequestPayload $sent, ?PatchServiceCheckOptionsInput $data): NoValue|ServiceMacroChanges
    {
        if (! $data instanceof PatchServiceCheckOptionsInput) {
            return new NoValue();
        }

        return match (true) {
            $sent->has('macros') => ServiceMacroChanges::replace(array_map($this->macroTransformer->toChange(...), array_values($data->macros))),
            $sent->has('macros_to_upsert') => ServiceMacroChanges::upsert(array_map($this->macroTransformer->toChange(...), array_values($data->macrosToUpsert))),
            $sent->has('macros_to_remove') => ServiceMacroChanges::remove(array_map(static fn (int $id): ServiceMacroId => new ServiceMacroId($id), array_values($data->macrosToRemove))),
            default => new NoValue(),
        };
    }

    private function schedulingOptions(ServiceMonitoringEngineInput $data, RequestPayload $sent): ServiceSchedulingOptionsChanges
    {
        return new ServiceSchedulingOptionsChanges(
            checkTimeperiodId: $this->provided($sent, 'check_timeperiod_id', static fn (): ?TimePeriodId => $data->checkTimeperiodId !== null ? new TimePeriodId($data->checkTimeperiodId) : null),
            maxCheckAttempts: $this->provided($sent, 'max_check_attempts', static fn (): ?int => $data->maxCheckAttempts),
            normalCheckInterval: $this->provided($sent, 'normal_check_interval', static fn (): ?int => $data->normalCheckInterval),
            retryCheckInterval: $this->provided($sent, 'retry_check_interval', static fn (): ?int => $data->retryCheckInterval),
            activeCheckEnabled: $this->provided($sent, 'active_check_enabled', fn (): TriStateEnum => $this->triState($data->activeCheckEnabled)),
            passiveCheckEnabled: $this->provided($sent, 'passive_check_enabled', fn (): TriStateEnum => $this->triState($data->passiveCheckEnabled)),
            isVolatile: $this->provided($sent, 'is_volatile', fn (): TriStateEnum => $this->triState($data->isVolatile)),
        );
    }

    private function extendedInformations(
        ?ServiceMonitoringEngineInput $engine,
        RequestPayload $engineSent,
        ?PatchServiceAdvancedInput $advanced,
        RequestPayload $advancedSent,
    ): ServiceExtendedInformationsChanges {
        return new ServiceExtendedInformationsChanges(
            noteUrl: $this->provided($engineSent, 'note_url', static fn (): ?string => $engine?->noteUrl),
            note: $this->provided($engineSent, 'note', static fn (): ?string => $engine?->note),
            actionUrl: $this->provided($engineSent, 'action_url', static fn (): ?string => $engine?->actionUrl),
            iconId: $this->provided($engineSent, 'icon_id', static fn (): ?MediaId => $engine?->iconId !== null ? new MediaId($engine->iconId) : null),
            altIcon: $this->provided($engineSent, 'alt_icon', static fn (): ?string => $engine?->altIcon),
            comment: $this->provided($advancedSent, 'comment', static fn (): ?string => $advanced?->comment),
            geoCoordinates: $this->provided($engineSent, 'geo_coordinates', static fn (): ?GeoCoordinates => $engine?->geoCoordinates !== null ? GeoCoordinates::fromString($engine->geoCoordinates) : null),
        );
    }

    private function notifications(PatchServiceNotificationsInput $data, RequestPayload $sent): ServiceNotificationsChanges
    {
        return new ServiceNotificationsChanges(
            enabled: $this->provided($sent, 'enabled', static fn (): TriStateEnum => $data->enabled !== null ? TriStateEnum::from($data->enabled) : TriStateEnum::UseDefault),
            options: $this->listChange($sent, 'options', $data->options, $data->optionsToAdd, $data->optionsToRemove, ServiceNotificationOptionEnumResolver::toDomain(...)),
            interval: $this->provided($sent, 'interval', static fn (): ?int => $data->interval),
            periodId: $this->provided($sent, 'timeperiod_id', static fn (): ?TimePeriodId => $data->timeperiodId !== null ? new TimePeriodId($data->timeperiodId) : null),
            firstDelay: $this->provided($sent, 'first_delay', static fn (): ?int => $data->firstDelay),
            recoveryDelay: $this->provided($sent, 'recovery_delay', static fn (): ?int => $data->recoveryDelay),
            useOnlyContactsFromHost: $this->provided($sent, 'use_only_contacts_from_host', static fn (): bool => $data->useOnlyContactsFromHost),
            contactAdditiveInheritance: $this->provided($sent, 'contact_additive_inheritance', static fn (): bool => $data->contactAdditiveInheritance),
            contactGroupAdditiveInheritance: $this->provided($sent, 'contact_group_additive_inheritance', static fn (): bool => $data->contactGroupAdditiveInheritance),
        );
    }

    private function dataProcessing(PatchServiceAdvancedInput $data, RequestPayload $sent): ServiceDataProcessingChanges
    {
        return new ServiceDataProcessingChanges(
            checkFreshness: $this->provided($sent, 'check_freshness', fn (): TriStateEnum => $this->triState($data->checkFreshness)),
            flapDetectionEnabled: $this->provided($sent, 'flap_detection_enabled', fn (): TriStateEnum => $this->triState($data->flapDetectionEnabled)),
            eventHandlerEnabled: $this->provided($sent, 'event_handler_enabled', fn (): TriStateEnum => $this->triState($data->eventHandlerEnabled)),
            obsessOverService: $this->provided($sent, 'obsess_over_service', fn (): TriStateEnum => $this->triState($data->obsessOverService)),
            retainStatusInformation: $this->provided($sent, 'retain_status_information', fn (): TriStateEnum => $this->triState($data->retainStatusInformation)),
            retainNonStatusInformation: $this->provided($sent, 'retain_non_status_information', fn (): TriStateEnum => $this->triState($data->retainNonStatusInformation)),
            acknowledgmentTimeout: $this->provided($sent, 'acknowledgment_timeout', static fn (): ?int => $data->acknowledgmentTimeout),
            freshnessThreshold: $this->provided($sent, 'freshness_threshold', static fn (): ?int => $data->freshnessThreshold),
            lowFlapThreshold: $this->provided($sent, 'low_flap_threshold', static fn (): ?int => $data->lowFlapThreshold),
            highFlapThreshold: $this->provided($sent, 'high_flap_threshold', static fn (): ?int => $data->highFlapThreshold),
            eventHandlerCommandId: $this->provided($sent, 'event_handler_command_id', static fn (): ?CommandId => $data->eventHandlerCommandId !== null ? new CommandId($data->eventHandlerCommandId) : null),
            eventHandlerArgs: $this->provided($sent, 'event_handler_args', static fn (): array => array_values($data->eventHandlerArgs)),
            stalkingOptions: $this->listChange($sent, 'stalking_options', $data->stalkingOptions, $data->stalkingOptionsToAdd, $data->stalkingOptionsToRemove, ServiceStalkingOptionEnumResolver::toDomain(...)),
        );
    }

    /**
     * How the update changes a list, from the one of its three keys that was sent: the key itself replaces the
     * list, with `_to_add` it is added to, with `_to_remove` it is removed from.
     *
     * @template U of int|string
     * @template T
     *
     * @param list<U> $replacement
     * @param list<U> $addition
     * @param list<U> $removal
     * @param \Closure(U): T $toValue
     *
     * @return NoValue|ListChange<T>
     */
    private function listChange(RequestPayload $sent, string $key, array $replacement, array $addition, array $removal, \Closure $toValue): NoValue|ListChange
    {
        return match (true) {
            $sent->has($key) => ListChange::replace(array_map($toValue, array_values($replacement))),
            $sent->has($key . '_to_add') => ListChange::add(array_map($toValue, array_values($addition))),
            $sent->has($key . '_to_remove') => ListChange::remove(array_map($toValue, array_values($removal))),
            default => new NoValue(),
        };
    }

    /**
     * @template T
     *
     * @param \Closure(): T $value
     *
     * @return T|NoValue
     */
    private function provided(RequestPayload $payload, string $key, \Closure $value): mixed
    {
        return $payload->has($key) ? $value() : new NoValue();
    }

    /**
     * Once a key is provided, the property that cannot be null is not: the constraint on the Input DTO has already
     * refused it.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T
     */
    private function required(mixed $value): mixed
    {
        Assert::notNull($value);

        return $value;
    }

    private function triState(?TriStateEnum $value): TriStateEnum
    {
        return $value ?? TriStateEnum::UseDefault;
    }
}
