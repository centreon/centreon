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

use App\MonitoringConfiguration\Application\Command\CheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\DataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use Webmozart\Assert\Assert;

/**
 * Builds the command of a partial update from the validated Input DTO and the keys that were really
 * sent: a key left out becomes {@see NoValue}, a key sent as null clears the value when it can be
 * cleared (a blank text reads as null, a tri-state as "use default"), anything else replaces it.
 */
final readonly class PatchHostCommandFactory
{
    public function create(
        HostId $id,
        int $updatedBy,
        ?UserId $viewerId,
        PatchHostInput $input,
        RequestPayload $payload,
    ): PatchHostCommand {
        return new PatchHostCommand(
            id: $id,
            updatedBy: $updatedBy,
            activated: $this->provided($payload, 'activated', fn (): bool => $this->required($input->activated)),
            name: $this->provided($payload, 'name', fn (): HostName => new HostName($this->required($input->name))),
            address: $this->provided($payload, 'address', fn (): HostAddress => new HostAddress($this->required($input->address))),
            pollerId: $this->provided($payload, 'poller_id', fn (): PollerId => new PollerId($this->required($input->pollerId))),
            alias: $this->provided($payload, 'alias', function () use ($input): ?HostAlias {
                $alias = $this->trimmedOrNull($input->alias);

                return $alias !== null ? new HostAlias($alias) : null;
            }),
            snmpVersion: $this->provided($payload, 'snmp_version', static fn (): ?SnmpVersionEnum => $input->snmpVersion),
            snmpCommunity: $this->provided($payload, 'snmp_community', fn (): ?string => $this->trimmedOrNull($input->snmpCommunity)),
            timezoneId: $this->provided($payload, 'timezone_id', static fn (): ?TimezoneId => $input->timezoneId !== null ? new TimezoneId($input->timezoneId) : null),
            severityId: $this->provided($payload, 'severity_id', static fn (): ?HostSeverityId => $input->severityId !== null ? new HostSeverityId($input->severityId) : null),
            dataProcessing: $this->provided($payload, 'data_processing', fn (): DataProcessingChanges => $this->dataProcessing(
                $this->required($input->dataProcessing),
                $payload->section('data_processing'),
            )),
            extendedInformations: $this->provided($payload, 'extended_informations', fn (): ExtendedInformationsChanges => $this->extendedInformations(
                $this->required($input->extendedInformations),
                $payload->section('extended_informations'),
            )),
            schedulingOptions: $this->provided($payload, 'scheduling_options', fn (): SchedulingOptionsChanges => $this->schedulingOptions(
                $this->required($input->schedulingOptions),
                $payload->section('scheduling_options'),
            )),
            checkOptions: $this->provided($payload, 'check_options', fn (): CheckOptionsChanges => $this->checkOptions(
                $this->required($input->checkOptions),
                $payload->section('check_options'),
            )),
            notifications: $this->provided($payload, 'notifications', fn (): NotificationsChanges => $this->notifications(
                $this->required($input->notifications),
                $payload->section('notifications'),
            )),
            viewerId: $viewerId,
        );
    }

    private function dataProcessing(DataProcessingInput $data, RequestPayload $sent): DataProcessingChanges
    {
        return new DataProcessingChanges(
            checkFreshness: $this->provided($sent, 'check_freshness', fn (): TriStateEnum => $this->triState($data->checkFreshness)),
            flapDetectionEnabled: $this->provided($sent, 'flap_detection_enabled', fn (): TriStateEnum => $this->triState($data->flapDetectionEnabled)),
            eventHandlerEnabled: $this->provided($sent, 'event_handler_enabled', fn (): TriStateEnum => $this->triState($data->eventHandlerEnabled)),
            acknowledgmentTimeout: $this->provided($sent, 'acknowledgment_timeout', static fn (): ?int => $data->acknowledgmentTimeout),
            freshnessThreshold: $this->provided($sent, 'freshness_threshold', static fn (): ?int => $data->freshnessThreshold),
            lowFlapThreshold: $this->provided($sent, 'low_flap_threshold', static fn (): ?int => $data->lowFlapThreshold),
            highFlapThreshold: $this->provided($sent, 'high_flap_threshold', static fn (): ?int => $data->highFlapThreshold),
            eventHandlerCommandId: $this->provided($sent, 'event_handler_command_id', static fn (): ?CommandId => $data->eventHandlerCommandId !== null ? new CommandId($data->eventHandlerCommandId) : null),
            eventHandlerArgs: $this->provided($sent, 'event_handler_args', static fn (): array => array_values($data->eventHandlerArgs)),
        );
    }

    private function extendedInformations(CreateHostExtendedInformationsInput $data, RequestPayload $sent): ExtendedInformationsChanges
    {
        return new ExtendedInformationsChanges(
            noteUrl: $this->provided($sent, 'note_url', fn (): ?string => $this->trimmedOrNull($data->noteUrl)),
            note: $this->provided($sent, 'note', fn (): ?string => $this->trimmedOrNull($data->note)),
            actionUrl: $this->provided($sent, 'action_url', fn (): ?string => $this->trimmedOrNull($data->actionUrl)),
            iconId: $this->provided($sent, 'icon_id', static fn (): ?MediaId => $data->iconId !== null ? new MediaId($data->iconId) : null),
            altIcon: $this->provided($sent, 'alt_icon', fn (): ?string => $this->trimmedOrNull($data->altIcon)),
            comment: $this->provided($sent, 'comment', fn (): ?string => $this->trimmedOrNull($data->comment)),
            geoCoordinates: $this->provided($sent, 'geo_coordinates', static fn (): ?GeoCoordinates => $data->geoCoordinates !== null ? GeoCoordinates::fromString($data->geoCoordinates) : null),
        );
    }

    private function schedulingOptions(CreateHostSchedulingOptionsInput $data, RequestPayload $sent): SchedulingOptionsChanges
    {
        return new SchedulingOptionsChanges(
            checkTimeperiodId: $this->provided($sent, 'check_timeperiod_id', static fn (): ?TimePeriodId => $data->checkTimeperiodId !== null ? new TimePeriodId($data->checkTimeperiodId) : null),
            maxCheckAttempts: $this->provided($sent, 'max_check_attempts', static fn (): ?int => $data->maxCheckAttempts),
            normalCheckInterval: $this->provided($sent, 'normal_check_interval', static fn (): ?int => $data->normalCheckInterval),
            retryCheckInterval: $this->provided($sent, 'retry_check_interval', static fn (): ?int => $data->retryCheckInterval),
            activeCheckEnabled: $this->provided($sent, 'active_check_enabled', fn (): TriStateEnum => $this->triState($data->activeCheckEnabled)),
            passiveCheckEnabled: $this->provided($sent, 'passive_check_enabled', fn (): TriStateEnum => $this->triState($data->passiveCheckEnabled)),
        );
    }

    private function checkOptions(PatchHostCheckOptionsInput $data, RequestPayload $sent): CheckOptionsChanges
    {
        return new CheckOptionsChanges(
            checkCommandId: $this->provided($sent, 'command_id', static fn (): ?CommandId => $data->commandId !== null ? new CommandId($data->commandId) : null),
            args: $this->provided($sent, 'args', static fn (): array => array_values($data->args)),
        );
    }

    private function notifications(PatchHostNotificationsInput $data, RequestPayload $sent): NotificationsChanges
    {
        return new NotificationsChanges(
            enabled: $this->provided($sent, 'enabled', static fn (): TriStateEnum => $data->enabled !== null ? TriStateEnum::from($data->enabled) : TriStateEnum::UseDefault),
            options: $this->provided($sent, 'options', static fn (): array => array_map(NotificationOptionEnumResolver::toDomain(...), $data->options)),
            interval: $this->provided($sent, 'interval', static fn (): ?int => $data->interval),
            periodId: $this->provided($sent, 'timeperiod_id', static fn (): ?TimePeriodId => $data->timeperiodId !== null ? new TimePeriodId($data->timeperiodId) : null),
            firstDelay: $this->provided($sent, 'first_delay', static fn (): ?int => $data->firstDelay),
            recoveryDelay: $this->provided($sent, 'recovery_delay', static fn (): ?int => $data->recoveryDelay),
            contactAdditiveInheritance: $this->provided($sent, 'contact_additive_inheritance', static fn (): bool => $data->contactAdditiveInheritance),
            contactGroupAdditiveInheritance: $this->provided($sent, 'contact_group_additive_inheritance', static fn (): bool => $data->contactGroupAdditiveInheritance),
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
     * Once a key is provided, the property that cannot be null is not: the constraint on the Input DTO
     * has already refused it.
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

    private function trimmedOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
