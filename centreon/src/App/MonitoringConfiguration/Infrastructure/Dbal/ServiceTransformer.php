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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-type MacroRowTypeAlias array{id?: int|string, name: string, value: string, is_password: string|int|null, description: string|null}
 * @phpstan-type RowTypeAlias array{
 *   service_id: int|string,
 *   service_description: string,
 *   host_id: int|string,
 *   host_activate: string|null,
 *   host_poller_id: int|string|null,
 *   service_activate: string,
 *   template_id: int|string|null,
 *   icon_id: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 *   macros: list<MacroRowTypeAlias>,
 *   check_command_id?: int|string|null,
 *   check_command_args?: string|null,
 *   check_timeperiod_id?: int|string|null,
 *   max_check_attempts?: int|string|null,
 *   active_check_enabled?: string|null,
 *   passive_check_enabled?: string|null,
 *   is_volatile?: string|null,
 *   acknowledgement_timeout?: int|string|null,
 *   check_freshness?: string|null,
 *   freshness_threshold?: int|string|null,
 *   flap_detection_enabled?: string|null,
 *   low_flap_threshold?: int|string|null,
 *   high_flap_threshold?: int|string|null,
 *   event_handler_enabled?: string|null,
 *   event_handler_command_id?: int|string|null,
 *   event_handler_args?: string|null,
 *   obsess_over_service?: string|null,
 *   retain_status_information?: string|null,
 *   retain_non_status_information?: string|null,
 *   stalking_options?: string|null,
 *   notifications_enabled?: string|null,
 *   notification_options?: string|null,
 *   notification_interval?: int|string|null,
 *   notification_period_id?: int|string|null,
 *   first_notification_delay?: int|string|null,
 *   recovery_notification_delay?: int|string|null,
 *   contact_additive_inheritance?: int|string|null,
 *   cg_additive_inheritance?: int|string|null,
 *   use_only_contacts_from_host?: string|null,
 *   notification_contact_ids?: string|null,
 *   notification_contact_group_ids?: string|null,
 *   note?: string|null,
 *   note_url?: string|null,
 *   action_url?: string|null,
 *   alt_icon?: string|null,
 *   comment?: string|null,
 *   geo_coords?: string|null,
 *   graph_template_id?: int|string|null,
 *   severity_id?: int|string|null,
 *   service_group_ids?: string|null,
 *   category_ids?: string|null,
 *   trap_ids?: string|null,
 * }
 *
 * @implements TransformerInterface<RowTypeAlias, Service>
 */
final readonly class ServiceTransformer implements TransformerInterface
{
    use TriStateColumnTrait;

    /**
     * A listing row (and the rows of the host flows) carries only the service's identity, activation, template,
     * own icon and own check intervals; every other key exists only on findOne()'s row, so each is read
     * defensively and the aggregate's defaults apply when absent (no notifications block, empty relations).
     *
     * Tolerant read: a stored number outside the bounds the service accepts today reads as unset, so a legacy
     * value never makes a whole read fail; texts are kept as stored.
     */
    public function transform(mixed $from): Service
    {
        return new Service(
            id: new ServiceId((int) $from['service_id']),
            name: new ServiceName($from['service_description']),
            hostId: new HostId((int) $from['host_id']),
            hostActivated: $from['host_activate'] === '1',
            hostPollerId: $from['host_poller_id'] !== null ? new PollerId((int) $from['host_poller_id']) : null,
            activated: $from['service_activate'] === '1',
            templateId: $from['template_id'] !== null ? new ServiceTemplateId((int) $from['template_id']) : null,
            checkOptions: new ServiceCheckOptions(
                checkCommandId: $this->id($from['check_command_id'] ?? null, CommandId::class),
                args: CommandArgumentsFormatter::parse($from['check_command_args'] ?? null),
                macros: array_map(self::createMacro(...), $from['macros']),
            ),
            schedulingOptions: new ServiceSchedulingOptions(
                checkTimeperiodId: $this->id($from['check_timeperiod_id'] ?? null, TimePeriodId::class),
                maxCheckAttempts: $this->atLeast($from['max_check_attempts'] ?? null, 1),
                normalCheckInterval: $this->atLeast($from['normal_check_interval'], 1),
                retryCheckInterval: $this->atLeast($from['retry_check_interval'], 1),
                activeCheckEnabled: $this->columnToTriState($from['active_check_enabled'] ?? null),
                passiveCheckEnabled: $this->columnToTriState($from['passive_check_enabled'] ?? null),
                isVolatile: $this->columnToTriState($from['is_volatile'] ?? null),
            ),
            notifications: $this->buildNotifications($from),
            dataProcessing: $this->buildDataProcessing($from),
            extendedInformations: $this->buildExtendedInformations($from),
            graphTemplateId: $this->id($from['graph_template_id'] ?? null, GraphTemplateId::class),
            severityId: $this->id($from['severity_id'] ?? null, ServiceSeverityId::class),
            serviceGroupIds: $this->idList($from['service_group_ids'] ?? null, ServiceGroupId::class),
            categoryIds: $this->idList($from['category_ids'] ?? null, ServiceCategoryId::class),
            trapIds: $this->idList($from['trap_ids'] ?? null, TrapId::class),
        );
    }

    /**
     * Also used for the macros of service templates ({@see ServiceTemplateTransformer}).
     *
     * @param MacroRowTypeAlias $row
     */
    public static function createMacro(array $row): ServiceMacro
    {
        // Stored as the full engine form ($_SERVICE<NAME>$, see ServiceMacroName::toStorageName());
        // strip the '$_SERVICE' prefix and trailing '$' to get back the short name the VO expects.
        $shortName = mb_substr($row['name'], 9, -1);

        return new ServiceMacro(
            name: new ServiceMacroName($shortName),
            value: $row['value'],
            isPassword: (bool) (int) ($row['is_password'] ?? 0),
            description: $row['description'],
            id: isset($row['id']) ? new ServiceMacroId((int) $row['id']) : null,
        );
    }

    /**
     * Only present on findOne()'s row: a listing row reads with no notifications block. Kept platform-agnostic:
     * the Cloud-vs-on-premise decision lives in the API layer.
     *
     * @param RowTypeAlias $from
     */
    private function buildNotifications(array $from): ?ServiceNotifications
    {
        if (! array_key_exists('notifications_enabled', $from)) {
            return null;
        }

        return new ServiceNotifications(
            enabled: $this->columnToTriState($from['notifications_enabled']),
            contactIds: $this->idList($from['notification_contact_ids'] ?? null, NotificationContactId::class),
            contactGroupIds: $this->idList($from['notification_contact_group_ids'] ?? null, ContactGroupId::class),
            options: $this->notificationOptions($from['notification_options'] ?? null),
            interval: $this->atLeast($from['notification_interval'] ?? null, 0),
            periodId: $this->id($from['notification_period_id'] ?? null, TimePeriodId::class),
            firstDelay: $this->atLeast($from['first_notification_delay'] ?? null, 0),
            recoveryDelay: $this->atLeast($from['recovery_notification_delay'] ?? null, 0),
            contactAdditiveInheritance: (bool) (int) ($from['contact_additive_inheritance'] ?? 0),
            contactGroupAdditiveInheritance: (bool) (int) ($from['cg_additive_inheritance'] ?? 0),
            useOnlyContactsFromHost: ($from['use_only_contacts_from_host'] ?? null) === '1',
        );
    }

    /**
     * @param RowTypeAlias $from
     */
    private function buildDataProcessing(array $from): ServiceDataProcessing
    {
        $lowFlapThreshold = $this->atLeast($from['low_flap_threshold'] ?? null, 0);
        $highFlapThreshold = $this->atLeast($from['high_flap_threshold'] ?? null, 0);

        return new ServiceDataProcessing(
            checkFreshness: $this->columnToTriState($from['check_freshness'] ?? null),
            flapDetectionEnabled: $this->columnToTriState($from['flap_detection_enabled'] ?? null),
            eventHandlerEnabled: $this->columnToTriState($from['event_handler_enabled'] ?? null),
            obsessOverService: $this->columnToTriState($from['obsess_over_service'] ?? null),
            retainStatusInformation: $this->columnToTriState($from['retain_status_information'] ?? null),
            retainNonStatusInformation: $this->columnToTriState($from['retain_non_status_information'] ?? null),
            acknowledgmentTimeout: $this->atLeast($from['acknowledgement_timeout'] ?? null, 1),
            freshnessThreshold: $this->atLeast($from['freshness_threshold'] ?? null, 0),
            lowFlapThreshold: $lowFlapThreshold !== null && $lowFlapThreshold <= 100 ? $lowFlapThreshold : null,
            highFlapThreshold: $highFlapThreshold !== null && $highFlapThreshold <= 100 ? $highFlapThreshold : null,
            eventHandlerCommandId: $this->id($from['event_handler_command_id'] ?? null, CommandId::class),
            eventHandlerArgs: CommandArgumentsFormatter::parse($from['event_handler_args'] ?? null),
            stalkingOptions: $this->stalkingOptions($from['stalking_options'] ?? null),
        );
    }

    /**
     * @param RowTypeAlias $from
     */
    private function buildExtendedInformations(array $from): ServiceExtendedInformations
    {
        $geoCoordinates = $this->nullIfBlank($from['geo_coords'] ?? null);

        return new ServiceExtendedInformations(
            noteUrl: $this->nullIfBlank($from['note_url'] ?? null),
            note: $this->nullIfBlank($from['note'] ?? null),
            actionUrl: $this->nullIfBlank($from['action_url'] ?? null),
            iconId: $this->id($from['icon_id'], MediaId::class),
            altIcon: $this->nullIfBlank($from['alt_icon'] ?? null),
            comment: $this->nullIfBlank($from['comment'] ?? null),
            geoCoordinates: $geoCoordinates !== null ? GeoCoordinates::fromString($geoCoordinates) : null,
        );
    }

    /**
     * The engine's comma-separated letters `w`, `u`, `c`, `r`, `f`, `s`, `n`; an unknown letter is ignored. Legacy
     * (mass change, CLAPI) can store "n" next to real options: the real options win.
     *
     * @return list<ServiceNotificationOptionEnum>
     */
    private function notificationOptions(?string $column): array
    {
        $options = [];
        foreach (explode(',', $column ?? '') as $letter) {
            $option = match (trim($letter)) {
                'w' => ServiceNotificationOptionEnum::Warning,
                'u' => ServiceNotificationOptionEnum::Unknown,
                'c' => ServiceNotificationOptionEnum::Critical,
                'r' => ServiceNotificationOptionEnum::Recovery,
                'f' => ServiceNotificationOptionEnum::Flapping,
                's' => ServiceNotificationOptionEnum::DowntimeScheduled,
                'n' => ServiceNotificationOptionEnum::None,
                default => null,
            };
            if ($option !== null) {
                $options[$option->name] = $option;
            }
        }

        if (count($options) > 1) {
            unset($options[ServiceNotificationOptionEnum::None->name]);
        }

        return array_values($options);
    }

    /**
     * The engine's comma-separated letters `o`, `w`, `u`, `c`; an unknown letter is ignored.
     *
     * @return list<ServiceStalkingOptionEnum>
     */
    private function stalkingOptions(?string $column): array
    {
        $options = [];
        foreach (explode(',', $column ?? '') as $letter) {
            $option = match (trim($letter)) {
                'o' => ServiceStalkingOptionEnum::Ok,
                'w' => ServiceStalkingOptionEnum::Warning,
                'u' => ServiceStalkingOptionEnum::Unknown,
                'c' => ServiceStalkingOptionEnum::Critical,
                default => null,
            };
            if ($option !== null) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * A stored number below $minimum counts as unset.
     */
    private function atLeast(int|string|null $value, int $minimum): ?int
    {
        return $value === null || (int) $value < $minimum ? null : (int) $value;
    }

    /**
     * A field cleared outside the form (CLAPI, mass change) can hold '' or blanks: it reads as unset.
     */
    private function nullIfBlank(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : null;
    }

    /**
     * @template T of AggregateRootId
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function id(int|string|null $value, string $class): ?object
    {
        return $value !== null ? new $class((int) $value) : null;
    }

    /**
     * @template T of AggregateRootId
     *
     * @param class-string<T> $class
     *
     * @return Collection<T>
     */
    private function idList(?string $commaSeparated, string $class): Collection
    {
        $ids = $commaSeparated === null || $commaSeparated === ''
            ? []
            : array_map(static fn (string $id): object => new $class((int) $id), explode(',', $commaSeparated));

        return new Collection($ids, $class);
    }
}
