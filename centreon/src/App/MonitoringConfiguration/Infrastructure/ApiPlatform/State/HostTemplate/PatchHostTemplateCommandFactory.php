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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessingChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateDataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\PatchHostTemplateNotificationsInput;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Webmozart\Assert\Assert;

/**
 * A key left out of the body becomes NoValue; a tri-state sent as null becomes "use_default".
 */
final readonly class PatchHostTemplateCommandFactory
{
    public function create(HostTemplateId $id, ActingUser $actor, PatchHostTemplateInput $input, RequestPayload $payload): PatchHostTemplateCommand
    {
        return new PatchHostTemplateCommand(
            id: $id,
            actor: $actor,
            change: new HostTemplateChange(
                name: $this->provided($payload, 'name', fn (): HostTemplateName => HostTemplateName::fromUserInput($this->required($input->name))),
                alias: $this->provided($payload, 'alias', fn (): HostTemplateAlias => new HostTemplateAlias($this->required($input->alias))),
                snmpVersion: $this->provided($payload, 'snmp_version', static fn (): ?HostTemplateSnmpVersionEnum => $input->snmpVersion),
                timezoneId: $this->provided($payload, 'timezone_id', static fn (): ?TimezoneId => $input->timezoneId !== null ? new TimezoneId($input->timezoneId) : null),
                severityId: $this->provided($payload, 'severity_id', static fn (): ?HostSeverityId => $input->severityId !== null ? new HostSeverityId($input->severityId) : null),
                checkOptions: $this->provided($payload, 'check_options', fn (): HostTemplateCheckOptionsChange => $this->checkOptions(
                    $this->required($input->checkOptions),
                    $payload->section('check_options'),
                )),
                extendedInformations: $this->provided($payload, 'extended_informations', fn (): HostTemplateExtendedInformationsChange => $this->extendedInformations(
                    $this->required($input->extendedInformations),
                    $payload->section('extended_informations'),
                )),
                schedulingOptions: $this->provided($payload, 'scheduling_options', fn (): HostTemplateSchedulingOptionsChange => $this->schedulingOptions(
                    $this->required($input->schedulingOptions),
                    $payload->section('scheduling_options'),
                )),
                dataProcessing: $this->provided($payload, 'data_processing', fn (): HostTemplateDataProcessingChange => $this->dataProcessing(
                    $this->required($input->dataProcessing),
                    $payload->section('data_processing'),
                )),
                notifications: $this->provided($payload, 'notifications', fn (): HostTemplateNotificationsChange => $this->notifications(
                    $this->required($input->notifications),
                    $payload->section('notifications'),
                )),
            ),
            snmpCommunity: $this->provided($payload, 'snmp_community', fn (): ?string => $this->trimmedOrNull($input->snmpCommunity)),
        );
    }

    private function checkOptions(PatchHostTemplateCheckOptionsInput $data, RequestPayload $sent): HostTemplateCheckOptionsChange
    {
        return new HostTemplateCheckOptionsChange(
            checkCommandId: $this->provided($sent, 'command_id', static fn (): ?CommandId => $data->commandId !== null ? new CommandId($data->commandId) : null),
            args: $this->provided($sent, 'args', static fn (): array => array_values($data->args)),
        );
    }

    private function extendedInformations(PatchHostTemplateExtendedInformationsInput $data, RequestPayload $sent): HostTemplateExtendedInformationsChange
    {
        return new HostTemplateExtendedInformationsChange(
            noteUrl: $this->provided($sent, 'note_url', fn (): ?string => $this->trimmedOrNull($data->noteUrl)),
            note: $this->provided($sent, 'note', fn (): ?string => $this->trimmedOrNull($data->note)),
            actionUrl: $this->provided($sent, 'action_url', fn (): ?string => $this->trimmedOrNull($data->actionUrl)),
            iconId: $this->provided($sent, 'icon_id', static fn (): ?MediaId => $data->iconId !== null ? new MediaId($data->iconId) : null),
            altIcon: $this->provided($sent, 'alt_icon', fn (): ?string => $this->trimmedOrNull($data->altIcon)),
            comment: $this->provided($sent, 'comment', fn (): ?string => $this->trimmedOrNull($data->comment)),
        );
    }

    private function trimmedOrNull(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed === '' ? null : $trimmed;
    }

    private function schedulingOptions(CreateHostTemplateSchedulingOptionsInput $data, RequestPayload $sent): HostTemplateSchedulingOptionsChange
    {
        return new HostTemplateSchedulingOptionsChange(
            checkTimeperiodId: $this->provided($sent, 'check_timeperiod_id', static fn (): ?TimePeriodId => $data->checkTimeperiodId !== null ? new TimePeriodId($data->checkTimeperiodId) : null),
            maxCheckAttempts: $this->provided($sent, 'max_check_attempts', static fn (): ?int => $data->maxCheckAttempts),
            normalCheckInterval: $this->provided($sent, 'normal_check_interval', static fn (): ?int => $data->normalCheckInterval),
            retryCheckInterval: $this->provided($sent, 'retry_check_interval', static fn (): ?int => $data->retryCheckInterval),
            activeCheckEnabled: $this->provided($sent, 'active_check_enabled', static fn (): TriStateEnum => $data->activeCheckEnabled ?? TriStateEnum::UseDefault),
            passiveCheckEnabled: $this->provided($sent, 'passive_check_enabled', static fn (): TriStateEnum => $data->passiveCheckEnabled ?? TriStateEnum::UseDefault),
        );
    }

    private function dataProcessing(CreateHostTemplateDataProcessingInput $data, RequestPayload $sent): HostTemplateDataProcessingChange
    {
        return new HostTemplateDataProcessingChange(
            checkFreshness: $this->provided($sent, 'check_freshness', static fn (): TriStateEnum => $data->checkFreshness ?? TriStateEnum::UseDefault),
            flapDetectionEnabled: $this->provided($sent, 'flap_detection_enabled', static fn (): TriStateEnum => $data->flapDetectionEnabled ?? TriStateEnum::UseDefault),
            eventHandlerEnabled: $this->provided($sent, 'event_handler_enabled', static fn (): TriStateEnum => $data->eventHandlerEnabled ?? TriStateEnum::UseDefault),
            acknowledgmentTimeout: $this->provided($sent, 'acknowledgment_timeout', static fn (): ?int => $data->acknowledgmentTimeout),
            freshnessThreshold: $this->provided($sent, 'freshness_threshold', static fn (): ?int => $data->freshnessThreshold),
            lowFlapThreshold: $this->provided($sent, 'low_flap_threshold', static fn (): ?int => $data->lowFlapThreshold),
            highFlapThreshold: $this->provided($sent, 'high_flap_threshold', static fn (): ?int => $data->highFlapThreshold),
            eventHandlerCommandId: $this->provided($sent, 'event_handler_command_id', static fn (): ?CommandId => $data->eventHandlerCommandId !== null ? new CommandId($data->eventHandlerCommandId) : null),
            eventHandlerArgs: $this->provided($sent, 'event_handler_args', static fn (): array => array_values($data->eventHandlerArgs)),
        );
    }

    private function notifications(PatchHostTemplateNotificationsInput $data, RequestPayload $sent): HostTemplateNotificationsChange
    {
        return new HostTemplateNotificationsChange(
            enabled: $this->provided($sent, 'enabled', static fn (): TriStateEnum => $data->enabled !== null ? TriStateEnum::from($data->enabled) : TriStateEnum::UseDefault),
            interval: $this->provided($sent, 'interval', static fn (): ?int => $data->interval),
            periodId: $this->provided($sent, 'timeperiod_id', static fn (): ?TimePeriodId => $data->timeperiodId !== null ? new TimePeriodId($data->timeperiodId) : null),
            firstDelay: $this->provided($sent, 'first_delay', static fn (): ?int => $data->firstDelay),
            recoveryDelay: $this->provided($sent, 'recovery_delay', static fn (): ?int => $data->recoveryDelay),
            contactAdditiveInheritance: $this->provided($sent, 'contact_additive_inheritance', fn (): bool => $this->required($data->contactAdditiveInheritance)),
            contactGroupAdditiveInheritance: $this->provided($sent, 'contact_group_additive_inheritance', fn (): bool => $this->required($data->contactGroupAdditiveInheritance)),
        );
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
     * Once a key is sent, a property that cannot be null is not: the input has refused a null.
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
}
