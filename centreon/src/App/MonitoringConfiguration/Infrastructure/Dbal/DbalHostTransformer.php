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
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalHostRepository
 * @phpstan-import-type FindOneRowTypeAlias from DbalHostRepository
 *
 * @implements TransformerInterface<RowTypeAlias|FindOneRowTypeAlias, Host>
 */
final readonly class DbalHostTransformer implements TransformerInterface
{
    use TriStateColumnTrait;

    public function transform(mixed $from): Host
    {
        $alias = $from['alias'] !== null ? trim($from['alias']) : '';

        return new Host(
            id: new HostId((int) $from['id']),
            name: new HostName($from['name']),
            alias: $alias !== '' ? new HostAlias($alias) : null,
            address: new HostAddress($from['ip_address']),
            activated: $from['is_activated'] === '1',
            pollerId: new PollerId((int) $from['poller_id']),
            templateIds: new Collection($from['template_ids'] !== null ? $this->parseIdList($from['template_ids'], HostTemplateId::class) : [], HostTemplateId::class),
            hostGroupIds: new Collection($from['group_ids'] !== null ? $this->parseIdList($from['group_ids'], HostGroupId::class) : [], HostGroupId::class),
            // The remaining fields are only present on the row `findOne()` builds — a listing row
            // (`RowTypeAlias`) carries none of these keys, and Host's own defaults (empty
            // collections, null, an empty CheckOptions/SchedulingOptions/DataProcessing) apply.
            // `findAll()` stays a deliberately partial read (see `Host::$checkOptions` docblock).
            categoryIds: new Collection(($from['category_ids'] ?? null) !== null ? $this->parseIdList($from['category_ids'], HostCategoryId::class) : [], HostCategoryId::class),
            parentHostIds: new Collection(($from['parent_host_ids'] ?? null) !== null ? $this->parseIdList($from['parent_host_ids'], HostId::class) : [], HostId::class),
            childHostIds: new Collection(($from['child_host_ids'] ?? null) !== null ? $this->parseIdList($from['child_host_ids'], HostId::class) : [], HostId::class),
            snmpVersion: isset($from['snmp_version']) ? SnmpVersionEnum::from($from['snmp_version']) : null,
            snmpCommunity: isset($from['snmp_community']) ? new SnmpCommunity($from['snmp_community']) : null,
            timezoneId: isset($from['timezone_id']) ? new TimezoneId((int) $from['timezone_id']) : null,
            severityId: isset($from['severity_id']) ? new HostSeverityId((int) $from['severity_id']) : null,
            extendedInformations: $this->buildExtendedInformations($from),
            schedulingOptions: $this->buildSchedulingOptions($from),
            dataProcessing: $this->buildDataProcessing($from),
            checkOptions: $this->buildCheckOptions($from),
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function parseIdList(string $commaSeparated, string $class): array
    {
        return array_map(
            static fn (string $id): object => new $class((int) $id),
            explode(',', $commaSeparated),
        );
    }

    /**
     * @param RowTypeAlias|FindOneRowTypeAlias $from
     */
    private function buildExtendedInformations(array $from): ExtendedInformations
    {
        return new ExtendedInformations(
            noteUrl: $from['note_url'] ?? null,
            note: $from['note'] ?? null,
            actionUrl: $from['action_url'] ?? null,
            iconId: $from['icon_id'] !== null ? new MediaId((int) $from['icon_id']) : null,
            altIcon: $from['alt_icon'] ?? null,
            comment: $from['comment'] ?? null,
            geoCoordinates: isset($from['geo_coords']) ? GeoCoordinates::fromString($from['geo_coords']) : null,
        );
    }

    /**
     * Only present on `findOne()`'s row; `findAll()`'s row carries none of these columns.
     *
     * @param RowTypeAlias|FindOneRowTypeAlias $from
     */
    private function buildSchedulingOptions(array $from): SchedulingOptions
    {
        if (! array_key_exists('check_timeperiod_id', $from)) {
            return new SchedulingOptions();
        }

        return new SchedulingOptions(
            checkTimeperiodId: $from['check_timeperiod_id'] !== null ? new TimePeriodId((int) $from['check_timeperiod_id']) : null,
            maxCheckAttempts: $from['max_check_attempts'] !== null ? (int) $from['max_check_attempts'] : null,
            normalCheckInterval: $from['normal_check_interval'] !== null ? (int) $from['normal_check_interval'] : null,
            retryCheckInterval: $from['retry_check_interval'] !== null ? (int) $from['retry_check_interval'] : null,
            activeCheckEnabled: $this->columnToTriState($from['active_check_enabled']),
            passiveCheckEnabled: $this->columnToTriState($from['passive_check_enabled']),
        );
    }

    /**
     * Only present on `findOne()`'s row; `findAll()`'s row carries none of these columns.
     *
     * @param RowTypeAlias|FindOneRowTypeAlias $from
     */
    private function buildDataProcessing(array $from): DataProcessing
    {
        if (! array_key_exists('acknowledgement_timeout', $from)) {
            return new DataProcessing();
        }

        return new DataProcessing(
            checkFreshness: $this->columnToTriState($from['check_freshness']),
            flapDetectionEnabled: $this->columnToTriState($from['flap_detection_enabled']),
            eventHandlerEnabled: $this->columnToTriState($from['event_handler_enabled']),
            acknowledgmentTimeout: $from['acknowledgement_timeout'] !== null ? (int) $from['acknowledgement_timeout'] : null,
            freshnessThreshold: $from['freshness_threshold'] !== null ? (int) $from['freshness_threshold'] : null,
            lowFlapThreshold: $from['low_flap_threshold'] !== null ? (int) $from['low_flap_threshold'] : null,
            highFlapThreshold: $from['high_flap_threshold'] !== null ? (int) $from['high_flap_threshold'] : null,
            eventHandlerCommandId: $from['event_handler_command_id'] !== null ? new CommandId((int) $from['event_handler_command_id']) : null,
            eventHandlerArgs: CommandArgumentsFormatter::parse($from['event_handler_args'] ?? null),
        );
    }

    /**
     * `args`/`macros` default to empty for `findAll()`'s row, same as `checkCommandId` to null —
     * no presence gate needed here, unlike scheduling/data processing: every sub-value already
     * degrades to its own empty/null default via `??`/`isset()`.
     *
     * @param RowTypeAlias|FindOneRowTypeAlias $from
     */
    private function buildCheckOptions(array $from): CheckOptions
    {
        return new CheckOptions(
            checkCommandId: isset($from['check_command_id']) ? new CommandId((int) $from['check_command_id']) : null,
            args: CommandArgumentsFormatter::parse($from['check_command_args'] ?? null),
            macros: array_map($this->createMacro(...), $from['macros'] ?? []),
        );
    }

    /**
     * @param array{name: string, value: string, is_password: string|int, description: string|null} $row
     */
    private function createMacro(array $row): HostMacro
    {
        // Stored as the full engine form ($_HOST<NAME>$, see HostMacroName::toStorageName()); strip
        // the '$_HOST' prefix and trailing '$' to get back the short name the VO's constructor expects.
        $shortName = mb_substr($row['name'], 6, -1);

        return new HostMacro(
            name: new HostMacroName($shortName),
            value: $row['value'],
            isPassword: (bool) $row['is_password'],
            description: $row['description'],
        );
    }
}
