<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Application\EngineConfiguration;

/**
 * Builds the JSON configuration of a poller run by centreon-engine (schema engine-configuration.v3.json)
 * from the attributes the .cfg generator writes, as it writes them. Only the fields of the schema are
 * kept: centreon-engine refuses a document with an unknown field. Values are kept as written, "encrypt::"
 * and "raw::" included: centreon-engine reads them as Engine does, with the key of the engine_context target.
 *
 * @phpstan-type _Written array<string, string|list<string>> attributes as written in the .cfg file,
 *                                                            custom macros included ("_HOST_ID" => "12")
 */
final class EngineConfigurationBuilder
{
    /** Feature flag: pollers whose engine_name is ENGINE_NAME get this document instead of .cfg files. */
    public const FEATURE_FLAG = 'engine_structured_configuration';
    public const ENGINE_NAME = 'centreon-engine';
    public const FILE_NAME = 'engine-configuration.json';
    /**
     * Version 2: timeperiods, check periods, time zones, recurring downtimes, encrypted values.
     * Version 3: groups, severities, tags, parents, dependencies, acknowledgement timeouts, event log settings.
     */
    public const VERSION = 3;
    public const KIND_HOST = 'host';
    public const KIND_HOST_TEMPLATE = 'host_template';
    public const KIND_SERVICE = 'service';
    public const KIND_SERVICE_TEMPLATE = 'service_template';
    public const KIND_COMMAND = 'command';
    public const KIND_TIMEPERIOD = 'timeperiod';
    public const KIND_RECURRING_DOWNTIME = 'recurring_downtime';
    public const KIND_HOSTGROUP = 'hostgroup';
    public const KIND_SERVICEGROUP = 'servicegroup';
    public const KIND_SEVERITY = 'severity';
    public const KIND_TAG = 'tag';
    public const KIND_HOST_DEPENDENCY = 'host_dependency';
    public const KIND_SERVICE_DEPENDENCY = 'service_dependency';
    /** Host of the meta-services, which the document does not carry: what refers to it is left out. */
    private const META_HOST = '_Module_Meta';

    private const STRING_FIELDS = [
        self::KIND_HOST => ['host_name', 'alias', 'address', 'check_command', 'check_period', 'timezone'],
        self::KIND_SERVICE => ['host_name', 'service_description', 'check_command', 'check_period'],
    ];
    private const INTEGER_FIELDS = [
        'check_interval', 'retry_interval', 'max_check_attempts', 'icon_id', 'acknowledgement_timeout',
    ];
    /** Lists of names, and lists of ids, of hosts and services. */
    private const NAME_LISTS = [self::KIND_HOST => ['parents'], self::KIND_SERVICE => []];
    private const ID_LISTS = ['category_tags', 'group_tags'];
    private const BOOLEAN_FIELDS = ['active_checks_enabled', 'passive_checks_enabled'];
    private const ID_MACROS = [
        self::KIND_HOST => ['_HOST_ID', 'host_id'],
        self::KIND_SERVICE => ['_SERVICE_ID', 'service_id'],
    ];
    private const SETTINGS = [
        'interval_length' => 1,
        'service_check_timeout' => 0,
        'host_check_timeout' => 0,
        'max_service_check_spread' => 0,
        'max_host_check_spread' => 0,
        'status_update_interval' => 0,
        'retention_update_interval' => 0,
        'cached_host_check_horizon' => 0,
    ];
    private const BOOLEAN_SETTINGS = [
        'use_retained_scheduling_info', 'log_service_retries', 'log_host_retries', 'log_external_commands',
        'log_passive_checks', 'soft_state_dependencies', 'enable_predictive_host_dependency_checks',
        'enable_predictive_service_dependency_checks',
    ];
    private const LOG_LEVEL_SETTINGS = ['log_level_events', 'log_level_checks', 'log_level_external_command'];
    private const DEPENDENCY_LISTS = [
        'host_name', 'hostgroup_name', 'dependent_host_name', 'dependent_hostgroup_name', 'service_description',
        'servicegroup_name', 'dependent_service_description', 'dependent_servicegroup_name',
    ];
    private const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    /** Attributes of timeperiods.cfg that are not exceptions. */
    private const TIMEPERIOD_FIELDS = ['name', 'timeperiod_name', 'alias', 'use', 'exclude', 'register'];
    private const BROKER_TCP_OUTPUTS = ['ipv4', 'ipv6', 'tcp'];
    private const COLLECTIONS = [
        self::KIND_COMMAND => 'commands',
        self::KIND_HOST_TEMPLATE => 'host_templates',
        self::KIND_HOST => 'hosts',
        self::KIND_SERVICE_TEMPLATE => 'service_templates',
        self::KIND_SERVICE => 'services',
        self::KIND_TIMEPERIOD => 'timeperiods',
        self::KIND_RECURRING_DOWNTIME => 'recurring_downtimes',
        self::KIND_HOSTGROUP => 'hostgroups',
        self::KIND_SERVICEGROUP => 'servicegroups',
        self::KIND_SEVERITY => 'severities',
        self::KIND_TAG => 'tags',
        self::KIND_HOST_DEPENDENCY => 'host_dependencies',
        self::KIND_SERVICE_DEPENDENCY => 'service_dependencies',
    ];

    /** @var array<string, int|string|bool> */
    private array $settings = [];

    /** @var array<string, string> */
    private array $resourceMacros = [];

    /** @var array{address: string, name: string}|null */
    private ?array $broker = null;

    /** @var array<string, list<array<string, mixed>>> objects by collection of the document */
    private array $objects = [];

    public function __construct(private readonly int $pollerId, private readonly string $pollerName)
    {
        if ($pollerId < 1 || $pollerName === '') {
            throw new \InvalidArgumentException('A poller needs a positive id and a name');
        }
    }

    /**
     * Tells whether nagios_server.engine_name designates centreon-engine.
     */
    public static function isCentreonEngine(?string $engineName): bool
    {
        return $engineName !== null && strcasecmp(trim($engineName), self::ENGINE_NAME) === 0;
    }

    /**
     * @param string $kind one of the KIND_* constants
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     */
    public function addObject(string $kind, array $written): void
    {
        $object = match ($kind) {
            self::KIND_COMMAND => $this->mapCommand($written),
            self::KIND_HOST, self::KIND_SERVICE => $this->mapMonitoredObject($kind, $written, false),
            self::KIND_HOST_TEMPLATE => $this->mapMonitoredObject(self::KIND_HOST, $written, true),
            self::KIND_SERVICE_TEMPLATE => $this->mapMonitoredObject(self::KIND_SERVICE, $written, true),
            self::KIND_TIMEPERIOD => $this->mapTimeperiod($written),
            self::KIND_HOSTGROUP => $this->mapHostgroup($written),
            self::KIND_SERVICEGROUP => $this->mapServicegroup($written),
            self::KIND_SEVERITY => $this->mapSeverity($written),
            self::KIND_TAG => $this->mapTag($written),
            self::KIND_HOST_DEPENDENCY, self::KIND_SERVICE_DEPENDENCY => $this->mapDependency($written),
            default => throw new \InvalidArgumentException(sprintf('Unknown object kind "%s"', $kind)),
        };
        if ($object !== null) {
            $this->objects[self::COLLECTIONS[$kind]][] = $object;
        }
    }

    /**
     * @param _Written $written resource.cfg entries ("$USER1$" => value)
     *
     * @throws \InvalidArgumentException
     */
    public function setResourceMacros(array $written): void
    {
        $this->resourceMacros = [];
        foreach ($written as $name => $value) {
            $name = trim((string) $name, '$');
            if ($name === '' || is_array($value)) {
                continue;
            }
            $this->resourceMacros[$name] = (string) $value;
        }
    }

    /**
     * @param _Written $written centengine.cfg attributes; the last call wins (the target file follows the debug one)
     *
     * @throws \InvalidArgumentException
     */
    public function setEngineSettings(array $written): void
    {
        $this->settings = [];
        foreach (self::SETTINGS as $name => $minimum) {
            $value = $written[$name] ?? null;
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if ($value !== null) {
                $this->settings[$name] = self::toInteger($value, $name, 'centengine.cfg', $minimum);
            }
        }
        $timezone = $written['use_timezone'] ?? null;
        if (is_string($timezone) && $timezone !== '') {
            $this->settings['use_timezone'] = $timezone;
        }
        foreach (self::BOOLEAN_SETTINGS as $name) {
            $value = $written[$name] ?? null;
            if ($value === '0' || $value === '1') {
                $this->settings[$name] = $value === '1';
            }
        }
        foreach (self::LOG_LEVEL_SETTINGS as $name) {
            $value = $written[$name] ?? null;
            if (is_string($value) && $value !== '') {
                $this->settings[$name] = $value;
            }
        }
    }

    /**
     * A recurring downtime as the generator writes it (RecurringDowntime::formatObject).
     *
     * @param array{downtime_id: int|string, downtime_name?: ?string, timezone?: ?string,
     *     host_ids: array<int|string>, service_ids: array<string>, period: array<string>} $object
     *
     * @throws \InvalidArgumentException
     */
    public function addRecurringDowntime(array $object): void
    {
        $id = self::toInteger((string) $object['downtime_id'], 'downtime_id', 'recurring downtime', 1);
        $rule = ['downtime_id' => $id];
        $name = trim((string) preg_replace('/[;\s]+/', ' ', (string) ($object['downtime_name'] ?? '')));
        if ($name !== '') {
            $rule['downtime_name'] = $name;
        }
        if (! empty($object['timezone'])) {
            $rule['timezone'] = (string) $object['timezone'];
        }
        $label = "recurring downtime {$id}";
        $hostIds = array_map(
            static fn (int|string $hostId): int => self::toInteger((string) $hostId, 'host_ids', $label, 1),
            array_values($object['host_ids'])
        );
        if ($hostIds !== []) {
            $rule['host_ids'] = $hostIds;
        }
        $serviceIds = [];
        foreach ($object['service_ids'] as $pair) {
            $parts = explode(':', (string) $pair);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException(sprintf('Invalid service id "%s" of %s', $pair, $label));
            }
            $serviceIds[] = [
                self::toInteger($parts[0], 'service_ids', $label, 1),
                self::toInteger($parts[1], 'service_ids', $label, 1),
            ];
        }
        if ($serviceIds !== []) {
            $rule['service_ids'] = $serviceIds;
        }
        $rule['periods'] = array_values(array_map('strval', $object['period']));
        $this->objects[self::COLLECTIONS[self::KIND_RECURRING_DOWNTIME]][] = $rule;
    }

    /**
     * Keeps the first TCP output to the central broker of the broker modules (cbmod) of the poller.
     *
     * @param string $name broker_name of the module
     * @param array<mixed> $outputs outputs of the module, as in its JSON file
     */
    public function addBrokerModule(string $name, array $outputs): void
    {
        if ($this->broker !== null) {
            return;
        }
        foreach ($outputs as $output) {
            if (! is_array($output)) {
                continue;
            }
            $type = (string) ($output['type'] ?? '');
            $isTcp = in_array($type, self::BROKER_TCP_OUTPUTS, true)
                || ($type === 'bbdo_client' && ($output['transport_protocol'] ?? 'tcp') === 'tcp');
            $host = trim((string) ($output['host'] ?? ''));
            $port = (string) ($output['port'] ?? '');
            if (! $isTcp || $host === '' || ! ctype_digit($port)) {
                continue;
            }
            if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
                $host = "[{$host}]";
            }
            $this->broker = ['address' => "{$host}:{$port}", 'name' => $name];

            return;
        }
    }

    /**
     * @throws \RuntimeException when no broker module of the poller sends to a central broker over TCP
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        if ($this->broker === null) {
            throw new \RuntimeException(sprintf(
                'Poller "%s" has no broker module with a TCP output to the central broker: '
                . 'centreon-engine cannot send its events',
                $this->pollerName
            ));
        }

        $document = [
            'version' => self::VERSION,
            'poller' => ['id' => $this->pollerId, 'name' => $this->pollerName],
            'broker' => $this->broker['name'] !== ''
                ? $this->broker
                : ['address' => $this->broker['address']],
        ];
        if ($this->settings !== []) {
            $document['settings'] = $this->settings;
        }
        if ($this->resourceMacros !== []) {
            $document['resource_macros'] = $this->resourceMacros;
        }
        foreach (self::COLLECTIONS as $collection) {
            if (($this->objects[$collection] ?? []) !== []) {
                $document[$collection] = $this->objects[$collection];
            }
        }

        return $document;
    }

    /**
     * @throws \RuntimeException
     * @throws \JsonException
     */
    public function toJson(): string
    {
        // Maps stay JSON objects even when their keys look like list indexes ("_0").
        $document = $this->build();
        if (isset($document['resource_macros'])) {
            $document['resource_macros'] = (object) $document['resource_macros'];
        }
        foreach (self::COLLECTIONS as $collection) {
            foreach ($document[$collection] ?? [] as $index => $object) {
                if (isset($object['macros'])) {
                    $document[$collection][$index]['macros'] = (object) $object['macros'];
                }
            }
        }

        return json_encode(
            $document,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    /**
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, string>
     */
    private function mapCommand(array $written): array
    {
        $command = [];
        foreach (['command_name', 'command_line', 'connector'] as $field) {
            if (isset($written[$field]) && is_string($written[$field]) && $written[$field] !== '') {
                $command[$field] = $written[$field];
            }
        }
        if (! isset($command['command_name'], $command['command_line'])) {
            throw new \InvalidArgumentException('A command needs a command_name and a command_line');
        }

        return $command;
    }

    /**
     * @param string $kind KIND_HOST or KIND_SERVICE
     * @param _Written $written
     * @param bool $isTemplate
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapMonitoredObject(string $kind, array $written, bool $isTemplate): array
    {
        $label = self::describe($kind, $written, $isTemplate);
        $object = [];
        if ($isTemplate) {
            $name = $written['name'] ?? '';
            if (! is_string($name) || $name === '') {
                throw new \InvalidArgumentException(sprintf('The %s template has no name', $kind));
            }
            $object['name'] = $name;
        }
        if (isset($written['use'])) {
            $uses = array_values(array_filter(
                array_map('strval', (array) $written['use']),
                static fn (string $use): bool => $use !== ''
            ));
            if ($uses !== []) {
                $object['use'] = $uses;
            }
        }
        foreach (self::STRING_FIELDS[$kind] as $field) {
            if (isset($written[$field]) && is_string($written[$field])) {
                $object[$field] = $written[$field];
            }
        }
        foreach (self::INTEGER_FIELDS as $field) {
            if (isset($written[$field]) && is_string($written[$field])) {
                $object[$field] = self::toInteger($written[$field], $field, $label, 0);
            }
        }
        foreach (self::BOOLEAN_FIELDS as $field) {
            $value = $written[$field] ?? null;
            if ($value === '0' || $value === '1') {
                $object[$field] = $value === '1';
            }
        }
        foreach (self::NAME_LISTS[$kind] as $field) {
            $names = self::names($written[$field] ?? []);
            if ($names !== []) {
                $object[$field] = $names;
            }
        }
        foreach (self::ID_LISTS as $field) {
            $ids = array_map(
                static fn (string $id): int => self::toInteger($id, $field, $label, 1),
                self::names($written[$field] ?? [])
            );
            if ($ids !== []) {
                $object[$field] = $ids;
            }
        }
        // The generator writes the severity with the macros ("severity" => id).
        $severity = $written['severity'] ?? null;
        if (is_string($severity) && $severity !== '') {
            $object['severity_id'] = self::toInteger($severity, 'severity', $label, 1);
        }

        [$idMacro, $idField] = self::ID_MACROS[$kind];
        $macros = [];
        foreach ($written as $key => $value) {
            $key = (string) $key;
            if (! str_starts_with($key, '_') || mb_strlen($key) < 2 || is_array($value)) {
                continue;
            }
            if ($key === $idMacro) {
                $object[$idField] = self::toInteger($value, $idField, $label, 1);
            } else {
                $macros[mb_substr($key, 1)] = $value;
            }
        }
        if ($macros !== []) {
            $object['macros'] = $macros;
        }

        return $object;
    }

    /**
     * @param string|list<string> $value a list, or one comma-separated value
     *
     * @return list<string>
     */
    private static function names(string|array $value): array
    {
        $names = is_array($value) ? $value : explode(',', $value);

        return array_values(array_filter(
            array_map(static fn ($name): string => trim((string) $name), $names),
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * hostgroups.cfg: id, name, alias and the member hosts.
     *
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapHostgroup(array $written): array
    {
        $name = $written['hostgroup_name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('A host group needs a hostgroup_name');
        }
        $group = [];
        if (isset($written['hostgroup_id']) && is_string($written['hostgroup_id'])) {
            $group['hostgroup_id'] = self::toInteger(
                $written['hostgroup_id'],
                'hostgroup_id',
                "host group \"{$name}\"",
                1
            );
        }
        $group['hostgroup_name'] = $name;
        if (isset($written['alias']) && is_string($written['alias'])) {
            $group['alias'] = $written['alias'];
        }
        $members = array_values(array_filter(
            self::names($written['members'] ?? []),
            static fn (string $host): bool => $host !== self::META_HOST
        ));
        if ($members !== []) {
            $group['members'] = $members;
        }

        return $group;
    }

    /**
     * servicegroups.cfg: id, name, alias and the members, written as host, service, host, service...
     *
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapServicegroup(array $written): array
    {
        $name = $written['servicegroup_name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('A service group needs a servicegroup_name');
        }
        $group = [];
        if (isset($written['servicegroup_id']) && is_string($written['servicegroup_id'])) {
            $group['servicegroup_id'] = self::toInteger(
                $written['servicegroup_id'],
                'servicegroup_id',
                "service group \"{$name}\"",
                1
            );
        }
        $group['servicegroup_name'] = $name;
        if (isset($written['alias']) && is_string($written['alias'])) {
            $group['alias'] = $written['alias'];
        }
        $members = [];
        foreach (array_chunk(self::names($written['members'] ?? []), 2) as $pair) {
            if (count($pair) === 2 && $pair[0] !== self::META_HOST) {
                $members[] = $pair;
            }
        }
        if ($members !== []) {
            $group['members'] = $members;
        }

        return $group;
    }

    /**
     * severities.cfg.
     *
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapSeverity(array $written): array
    {
        $name = $written['severity_name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('A severity needs a severity_name');
        }
        $label = "severity \"{$name}\"";
        $severity = [
            'id' => self::toInteger((string) ($written['id'] ?? ''), 'id', $label, 1),
            'severity_name' => $name,
            'level' => self::toInteger((string) ($written['level'] ?? ''), 'level', $label, 1),
        ];
        if (isset($written['icon_id']) && is_string($written['icon_id'])) {
            $severity['icon_id'] = self::toInteger($written['icon_id'], 'icon_id', $label, 0);
        }
        $severity['type'] = (string) ($written['type'] ?? '');

        return $severity;
    }

    /**
     * tags.cfg: host and service categories, host and service groups.
     *
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapTag(array $written): array
    {
        $name = $written['tag_name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('A tag needs a tag_name');
        }

        return [
            'id' => self::toInteger((string) ($written['id'] ?? ''), 'id', "tag \"{$name}\"", 1),
            'tag_name' => $name,
            'type' => (string) ($written['type'] ?? ''),
        ];
    }

    /**
     * dependencies.cfg: a host or service dependency; one on a meta-service is left out.
     *
     * @param _Written $written
     *
     * @return array<string, mixed>|null
     */
    private function mapDependency(array $written): ?array
    {
        $dependency = [];
        foreach (self::DEPENDENCY_LISTS as $field) {
            $names = self::names($written[$field] ?? []);
            if (in_array(self::META_HOST, $names, true)) {
                return null;
            }
            if ($names !== []) {
                $dependency[$field] = $names;
            }
        }
        foreach (['execution_failure_criteria', 'notification_failure_criteria', 'dependency_period'] as $field) {
            if (isset($written[$field]) && is_string($written[$field]) && $written[$field] !== '') {
                $dependency[$field] = $written[$field];
            }
        }
        $inherits = $written['inherits_parent'] ?? null;
        if ($inherits === '0' || $inherits === '1') {
            $dependency['inherits_parent'] = $inherits === '1';
        }

        return $dependency;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function toInteger(string $value, string $field, string $owner, int $minimum): int
    {
        $value = trim($value);
        if (! ctype_digit($value) || (int) $value < $minimum || (int) $value > 4294967295) {
            throw new \InvalidArgumentException(
                sprintf('Invalid value "%s" for %s of %s: an integer is expected', $value, $field, $owner)
            );
        }

        return (int) $value;
    }

    /**
     * A timeperiod as written in timeperiods.cfg: its weekdays, then its exceptions (the other attributes).
     *
     * @param _Written $written
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function mapTimeperiod(array $written): array
    {
        $name = $written['timeperiod_name'] ?? '';
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('A timeperiod needs a timeperiod_name');
        }
        $period = ['timeperiod_name' => $name];
        if (isset($written['alias']) && is_string($written['alias'])) {
            $period['alias'] = $written['alias'];
        }
        foreach (['use', 'exclude'] as $list) {
            $names = array_values(array_filter(
                array_map('strval', (array) ($written[$list] ?? [])),
                static fn (string $value): bool => $value !== ''
            ));
            if ($names !== []) {
                $period[$list] = $names;
            }
        }
        foreach (self::WEEKDAYS as $day) {
            if (isset($written[$day]) && is_string($written[$day]) && $written[$day] !== '') {
                $period[$day] = $written[$day];
            }
        }
        $exceptions = [];
        foreach ($written as $days => $timerange) {
            $days = (string) $days;
            if (in_array($days, self::TIMEPERIOD_FIELDS, true) || in_array($days, self::WEEKDAYS, true)
                || is_array($timerange)) {
                continue;
            }
            $exceptions[] = ['days' => $days, 'timerange' => $timerange];
        }
        if ($exceptions !== []) {
            $period['exceptions'] = $exceptions;
        }

        return $period;
    }

    /**
     * @param _Written $written
     */
    private static function describe(string $kind, array $written, bool $isTemplate): string
    {
        $nameField = $isTemplate ? 'name' : ($kind === self::KIND_HOST ? 'host_name' : 'service_description');
        $name = $written[$nameField] ?? '';

        return sprintf('%s%s "%s"', $kind, $isTemplate ? ' template' : '', is_string($name) ? $name : '');
    }
}
