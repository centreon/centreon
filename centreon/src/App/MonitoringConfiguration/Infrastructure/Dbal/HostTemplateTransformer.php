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
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * Reads what is stored, out-of-bound values included: a blank text is no value and an unknown SNMP
 * version reads as none.
 *
 * @phpstan-import-type RowTypeAlias from DbalHostTemplateRepository
 * @phpstan-import-type FindOneRowTypeAlias from DbalHostTemplateRepository
 *
 * @implements TransformerInterface<RowTypeAlias|FindOneRowTypeAlias, HostTemplate>
 */
final readonly class HostTemplateTransformer implements TransformerInterface
{
    use TriStateColumnTrait;

    /**
     * A listing row (RowTypeAlias) leaves everything but the name, alias, lock, icon and parents at
     * the aggregate's defaults.
     *
     * @param RowTypeAlias|FindOneRowTypeAlias $from
     */
    public function transform(mixed $from): HostTemplate
    {
        $alias = $this->nullIfBlank($from['host_alias']);
        $parentTemplateIds = new Collection($this->ids($from['parent_template_ids'], HostTemplateId::class), HostTemplateId::class);
        $iconId = $from['icon_id'] !== null ? new MediaId((int) $from['icon_id']) : null;

        if (! array_key_exists('snmp_version', $from)) {
            return new HostTemplate(
                id: new HostTemplateId($from['host_id']),
                name: new HostTemplateName($from['host_name']),
                alias: $alias !== null ? new HostTemplateAlias($alias) : null,
                isLocked: (bool) (int) ($from['host_locked'] ?? 0),
                parentTemplateIds: $parentTemplateIds,
                extendedInformations: $iconId instanceof MediaId ? new HostTemplateExtendedInformations(iconId: $iconId) : null,
            );
        }

        $snmpCommunity = $this->nullIfBlank($from['snmp_community']);
        $snmpVersion = $this->nullIfBlank($from['snmp_version']);

        return new HostTemplate(
            id: new HostTemplateId($from['host_id']),
            name: new HostTemplateName($from['host_name']),
            checkOptions: new HostTemplateCheckOptions(
                $this->commandId($from['check_command_id']),
                CommandArgumentsFormatter::parse($from['check_command_args']),
                new Collection($from['macros'], HostTemplateMacro::class),
            ),
            serviceTemplateCheckCommandIds: new Collection($this->ids($from['service_template_check_command_ids'], CommandId::class), CommandId::class),
            alias: $alias !== null ? new HostTemplateAlias($alias) : null,
            isLocked: (bool) (int) ($from['host_locked'] ?? 0),
            parentTemplateIds: $parentTemplateIds,
            snmpCommunity: $snmpCommunity !== null ? new HostTemplateSnmpCommunity($snmpCommunity) : null,
            snmpVersion: $snmpVersion !== null ? HostTemplateSnmpVersionEnum::tryFrom($snmpVersion) : null,
            // host_location defaults to 0, not NULL: 0 means no timezone too.
            timezoneId: (int) $from['timezone_id'] > 0 ? new TimezoneId((int) $from['timezone_id']) : null,
            severityId: $from['severity_id'] !== null ? new HostSeverityId($from['severity_id']) : null,
            extendedInformations: $this->extendedInformations($from, $iconId),
            schedulingOptions: new HostTemplateSchedulingOptions(
                checkTimeperiodId: $this->timePeriodId($from['check_timeperiod_id']),
                maxCheckAttempts: $this->int($from['max_check_attempts']),
                normalCheckInterval: $this->int($from['normal_check_interval']),
                retryCheckInterval: $this->int($from['retry_check_interval']),
                activeCheckEnabled: $this->columnToTriState($from['active_check_enabled']),
                passiveCheckEnabled: $this->columnToTriState($from['passive_check_enabled']),
            ),
            dataProcessing: new HostTemplateDataProcessing(
                checkFreshness: $this->columnToTriState($from['check_freshness']),
                flapDetectionEnabled: $this->columnToTriState($from['flap_detection_enabled']),
                eventHandlerEnabled: $this->columnToTriState($from['event_handler_enabled']),
                acknowledgmentTimeout: $this->int($from['acknowledgement_timeout']),
                freshnessThreshold: $this->int($from['freshness_threshold']),
                lowFlapThreshold: $this->int($from['low_flap_threshold']),
                highFlapThreshold: $this->int($from['high_flap_threshold']),
                eventHandlerCommandId: $this->commandId($from['event_handler_command_id']),
                eventHandlerArgs: CommandArgumentsFormatter::parse($from['event_handler_args']),
            ),
            notifications: new HostTemplateNotifications(
                enabled: $this->columnToTriState($from['notifications_enabled']),
                contactIds: new Collection($this->ids($from['contact_ids'], NotificationContactId::class), NotificationContactId::class),
                contactGroupIds: new Collection($this->ids($from['contact_group_ids'], ContactGroupId::class), ContactGroupId::class),
                options: $this->notificationOptions($from['notification_options']),
                interval: $this->int($from['notification_interval']),
                periodId: $this->timePeriodId($from['notification_period_id']),
                firstDelay: $this->int($from['first_notification_delay']),
                recoveryDelay: $this->int($from['recovery_notification_delay']),
                contactAdditiveInheritance: (bool) (int) ($from['contact_additive_inheritance'] ?? 0),
                contactGroupAdditiveInheritance: (bool) (int) ($from['cg_additive_inheritance'] ?? 0),
            ),
            categoryIds: new Collection($this->ids($from['category_ids'], HostCategoryId::class), HostCategoryId::class),
            linkedServiceTemplateIds: new Collection($this->ids($from['linked_service_template_ids'], ServiceTemplateId::class), ServiceTemplateId::class),
        );
    }

    /**
     * @param FindOneRowTypeAlias $from
     */
    private function extendedInformations(array $from, ?MediaId $iconId): ?HostTemplateExtendedInformations
    {
        $informations = new HostTemplateExtendedInformations(
            noteUrl: $this->nullIfBlank($from['note_url']),
            note: $this->nullIfBlank($from['note']),
            actionUrl: $this->nullIfBlank($from['action_url']),
            iconId: $iconId,
            altIcon: $this->nullIfBlank($from['alt_icon']),
            comment: $this->nullIfBlank($from['comment']),
        );

        $isEmpty = $informations->noteUrl === null
            && $informations->note === null
            && $informations->actionUrl === null
            && ! $iconId instanceof MediaId
            && $informations->altIcon === null
            && $informations->comment === null;

        return $isEmpty ? null : $informations;
    }

    /**
     * The engine's comma-separated letters, in the enum's order; an unknown letter is skipped, and
     * "n" next to real options is dropped.
     *
     * @return list<HostTemplateNotificationOptionEnum>
     */
    private function notificationOptions(?string $column): array
    {
        $options = [];
        foreach (explode(',', $column ?? '') as $letter) {
            $option = match (trim($letter)) {
                'd' => HostTemplateNotificationOptionEnum::Down,
                'u' => HostTemplateNotificationOptionEnum::Unreachable,
                'r' => HostTemplateNotificationOptionEnum::Recovery,
                'f' => HostTemplateNotificationOptionEnum::Flapping,
                's' => HostTemplateNotificationOptionEnum::DowntimeScheduled,
                'n' => HostTemplateNotificationOptionEnum::None,
                default => null,
            };
            if ($option !== null) {
                $options[$option->name] = $option;
            }
        }

        if (count($options) > 1) {
            unset($options[HostTemplateNotificationOptionEnum::None->name]);
        }

        return array_values(array_filter(
            HostTemplateNotificationOptionEnum::cases(),
            static fn (HostTemplateNotificationOptionEnum $option): bool => isset($options[$option->name]),
        ));
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : null;
    }

    private function int(int|string|null $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    private function commandId(int|string|null $value): ?CommandId
    {
        return $value !== null ? new CommandId((int) $value) : null;
    }

    private function timePeriodId(int|string|null $value): ?TimePeriodId
    {
        return $value !== null ? new TimePeriodId((int) $value) : null;
    }

    /**
     * @template T of AggregateRootId
     *
     * @param list<int> $values
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function ids(array $values, string $class): array
    {
        return array_map(static fn (int $value): AggregateRootId => new $class($value), $values);
    }
}
