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

namespace Tests\Core\Gorgone\Application\EngineConfiguration;

use Core\Gorgone\Application\EngineConfiguration\EngineConfigurationBuilder;

const MODULE_OUTPUTS = [
    ['type' => 'unified_sql', 'name' => 'sql'],
    ['type' => 'ipv4', 'name' => 'forward-to-central', 'host' => '10.0.0.1', 'port' => '5669'],
];

beforeEach(function (): void {
    $this->builder = new EngineConfigurationBuilder(3, 'poller-3');
    $this->builder->addBrokerModule('poller-3-module', MODULE_OUTPUTS);
});

it('should build the smallest document from the poller and its broker module', function (): void {
    expect($this->builder->build())->toBe([
        'version' => 2,
        'poller' => ['id' => 3, 'name' => 'poller-3'],
        'broker' => ['address' => '10.0.0.1:5669', 'name' => 'poller-3-module'],
    ]);
});

it('should map a host as written in hosts.cfg, keeping only the fields of the schema', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_HOST, [
        'host_name' => 'srv-1',
        'alias' => 'Server 1',
        'address' => '192.168.1.1',
        'check_command' => 'check_ping!200,20%!400,50%',
        'check_period' => '24x7',
        'contacts' => 'admin',
        'max_check_attempts' => '3',
        'check_interval' => '5',
        'retry_interval' => '1',
        'register' => '1',
        'active_checks_enabled' => '1',
        'passive_checks_enabled' => '0',
        'notifications_enabled' => '1',
        'use' => ['generic-host', 'OS-Linux'],
        'parents' => ['srv-0'],
        'group_tags' => ['4'],
        '_HOST_ID' => '12',
        '_SNMPCOMMUNITY' => 'public',
        '_SNMPVERSION' => '2c',
        '_PASSWORD' => 'raw::s3cr3t',
        '_TOKEN' => 'encrypt::QUJDREVGR0g=',
        'timezone' => ':Europe/Paris',
    ]);

    expect($this->builder->build()['hosts'])->toBe([[
        'use' => ['generic-host', 'OS-Linux'],
        'host_name' => 'srv-1',
        'alias' => 'Server 1',
        'address' => '192.168.1.1',
        'check_command' => 'check_ping!200,20%!400,50%',
        'check_period' => '24x7',
        'timezone' => ':Europe/Paris',
        'check_interval' => 5,
        'retry_interval' => 1,
        'max_check_attempts' => 3,
        'active_checks_enabled' => true,
        'passive_checks_enabled' => false,
        'host_id' => 12,
        // Values as written: centreon-engine strips raw:: and decrypts encrypt::.
        'macros' => [
            'SNMPCOMMUNITY' => 'public',
            'SNMPVERSION' => '2c',
            'PASSWORD' => 'raw::s3cr3t',
            'TOKEN' => 'encrypt::QUJDREVGR0g=',
        ],
    ]]);
});

it('should map a service with its host name and _SERVICE_ID, without the severity', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_SERVICE, [
        'host_name' => 'srv-1',
        'service_description' => 'Ping',
        'use' => ['Base-Ping'],
        'check_interval' => '10',
        '_SERVICE_ID' => '42',
        '_CRITICALITY_LEVEL' => '1',
        'severity' => '7',
    ]);

    expect($this->builder->build()['services'])->toBe([[
        'use' => ['Base-Ping'],
        'host_name' => 'srv-1',
        'service_description' => 'Ping',
        'check_interval' => 10,
        'service_id' => 42,
        'macros' => ['CRITICALITY_LEVEL' => '1'],
    ]]);
});

it('should send the templates unresolved, with their name and use list', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_HOST_TEMPLATE, [
        'name' => 'generic-host',
        'use' => ['root-host'],
        'check_command' => 'check_host_alive',
        'register' => '0',
        '_HOST_ID' => '2',
    ]);
    $this->builder->addObject(EngineConfigurationBuilder::KIND_SERVICE_TEMPLATE, [
        'name' => 'Base-Ping',
        'service_description' => 'Ping',
        'max_check_attempts' => '3',
        'register' => '0',
        '_SERVICE_ID' => '5',
    ]);

    $document = $this->builder->build();
    expect($document['host_templates'])->toBe([[
        'name' => 'generic-host',
        'use' => ['root-host'],
        'check_command' => 'check_host_alive',
        'host_id' => 2,
    ]])->and($document['service_templates'])->toBe([[
        'name' => 'Base-Ping',
        'service_description' => 'Ping',
        'max_check_attempts' => 3,
        'service_id' => 5,
    ]]);
});

it('should map the commands, resource macros and engine settings', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_COMMAND, [
        'command_name' => 'check_ping',
        'command_line' => '$USER1$/check_icmp -H $HOSTADDRESS$',
    ]);
    $this->builder->addObject(EngineConfigurationBuilder::KIND_COMMAND, [
        'command_name' => 'check_perl',
        'command_line' => '$USER1$/check.pl',
        'connector' => 'Perl Connector',
    ]);
    $this->builder->setResourceMacros([
        '$USER1$' => '/usr/lib64/nagios/plugins',
        '$CENTREONPLUGINS$' => '/usr/lib/centreon/plugins',
        '$PASSWORD$' => 'encrypt::QUJDREVGR0g=',
    ]);
    $this->builder->setEngineSettings([
        'interval_length' => ['60'],
        'service_check_timeout' => '60',
        'host_check_timeout' => '12',
        'max_service_check_spread' => '15',
        'max_host_check_spread' => '15',
        'status_update_interval' => '30',
        'retention_update_interval' => '15',
        'use_timezone' => ':Europe/Paris',
        'use_retained_scheduling_info' => '1',
        'log_file' => '/var/log/centreon-engine/centengine.log',
        'cfg_file' => ['/etc/centreon-engine/hosts.cfg'],
    ]);

    $document = $this->builder->build();
    expect($document['commands'])->toBe([
        ['command_name' => 'check_ping', 'command_line' => '$USER1$/check_icmp -H $HOSTADDRESS$'],
        ['command_name' => 'check_perl', 'command_line' => '$USER1$/check.pl', 'connector' => 'Perl Connector'],
    ])->and($document['resource_macros'])->toBe([
        'USER1' => '/usr/lib64/nagios/plugins',
        'CENTREONPLUGINS' => '/usr/lib/centreon/plugins',
        'PASSWORD' => 'encrypt::QUJDREVGR0g=',
    ])->and($document['settings'])->toBe([
        'interval_length' => 60,
        'service_check_timeout' => 60,
        'host_check_timeout' => 12,
        'max_service_check_spread' => 15,
        'max_host_check_spread' => 15,
        'status_update_interval' => 30,
        'retention_update_interval' => 15,
        'use_timezone' => ':Europe/Paris',
        'use_retained_scheduling_info' => true,
    ])->and(array_keys($document))->toBe(
        ['version', 'poller', 'broker', 'settings', 'resource_macros', 'commands']
    );
});

it('should keep the first TCP output to the central broker', function (array $outputs, string $address): void {
    $builder = new EngineConfigurationBuilder(3, 'poller-3');
    $builder->addBrokerModule('first-module', $outputs);
    $builder->addBrokerModule('second-module', MODULE_OUTPUTS);

    expect($builder->build()['broker'])->toBe(['address' => $address, 'name' => 'first-module']);
})->with([
    'ipv4' => [MODULE_OUTPUTS, '10.0.0.1:5669'],
    'ipv6' => [[['type' => 'ipv6', 'host' => 'fd00::1', 'port' => '5669']], '[fd00::1]:5669'],
    'bbdo client over tcp' => [
        [['type' => 'bbdo_client', 'host' => 'central', 'port' => '5669', 'transport_protocol' => 'tcp']],
        'central:5669',
    ],
]);

it('should take the next module when one has no TCP output to a central broker', function (): void {
    $builder = new EngineConfigurationBuilder(3, 'poller-3');
    $builder->addBrokerModule('grpc-module', [
        ['type' => 'bbdo_client', 'host' => 'central', 'port' => '5669', 'transport_protocol' => 'grpc'],
        ['type' => 'ipv4', 'port' => '5669'],
    ]);
    $builder->addBrokerModule('tcp-module', MODULE_OUTPUTS);

    expect($builder->build()['broker'])->toBe(['address' => '10.0.0.1:5669', 'name' => 'tcp-module']);
});

it('should refuse to build without a broker address', function (): void {
    (new EngineConfigurationBuilder(3, 'poller-3'))->build();
})->throws(\RuntimeException::class, 'Poller "poller-3" has no broker module with a TCP output');

it('should refuse what centreon-engine could not read', function (string $kind, array $written, string $message): void {
    expect(fn () => $this->builder->addObject($kind, $written))
        ->toThrow(\InvalidArgumentException::class, $message);
})->with([
    'timeperiod without name' => [EngineConfigurationBuilder::KIND_TIMEPERIOD, ['alias' => 'x'], 'timeperiod_name'],
    'decimal interval' => [
        EngineConfigurationBuilder::KIND_SERVICE,
        ['service_description' => 'Ping', 'check_interval' => '0.5'],
        'Invalid value "0.5" for check_interval of service "Ping"',
    ],
    'template without name' => [EngineConfigurationBuilder::KIND_HOST_TEMPLATE, ['alias' => 'x'], 'has no name'],
    'command without line' => [EngineConfigurationBuilder::KIND_COMMAND, ['command_name' => 'x'], 'command_line'],
    'unknown kind' => ['contact', ['contact_name' => 'admin'], 'Unknown object kind "contact"'],
]);

it('should map a timeperiod as written in timeperiods.cfg, exceptions included', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_TIMEPERIOD, [
        'name' => 'workhours',
        'timeperiod_name' => 'workhours',
        'alias' => 'Work hours',
        'monday' => '09:00-12:00,14:00-18:00',
        'tuesday' => '09:00-18:00',
        'use' => ['base'],
        'exclude' => ['holidays'],
        'december 25' => '00:00-24:00',
        '2026-12-24 - 2026-12-26' => '08:00-09:00',
    ]);

    expect($this->builder->build()['timeperiods'])->toBe([[
        'timeperiod_name' => 'workhours',
        'alias' => 'Work hours',
        'use' => ['base'],
        'exclude' => ['holidays'],
        'monday' => '09:00-12:00,14:00-18:00',
        'tuesday' => '09:00-18:00',
        'exceptions' => [
            ['days' => 'december 25', 'timerange' => '00:00-24:00'],
            ['days' => '2026-12-24 - 2026-12-26', 'timerange' => '08:00-09:00'],
        ],
    ]]);
});

it('should map a recurring downtime as the generator writes it', function (): void {
    $this->builder->addRecurringDowntime([
        'downtime_id' => 7,
        'downtime_name' => "Night; backup\nwindow",
        'timezone' => ':Europe/Paris',
        'host_ids' => [12, '13'],
        'service_ids' => ['14:42'],
        'period' => ['weekly:1,7 22:00-24:00 fixed', 'monthly:1 01:00-02:00 flexible:600'],
    ]);
    $this->builder->addRecurringDowntime([
        'downtime_id' => '8',
        'timezone' => null,
        'host_ids' => [],
        'service_ids' => ['14:43'],
        'period' => ['nth:last:5 10:00-11:00 fixed'],
    ]);

    expect($this->builder->build()['recurring_downtimes'])->toBe([
        [
            'downtime_id' => 7,
            'downtime_name' => 'Night backup window',
            'timezone' => ':Europe/Paris',
            'host_ids' => [12, 13],
            'service_ids' => [[14, 42]],
            'periods' => ['weekly:1,7 22:00-24:00 fixed', 'monthly:1 01:00-02:00 flexible:600'],
        ],
        ['downtime_id' => 8, 'service_ids' => [[14, 43]], 'periods' => ['nth:last:5 10:00-11:00 fixed']],
    ]);
    expect(fn () => $this->builder->addRecurringDowntime([
        'downtime_id' => 9, 'host_ids' => [], 'service_ids' => ['14'], 'period' => [],
    ]))->toThrow(\InvalidArgumentException::class, 'Invalid service id "14"');
});

it('should match the schema the engine reads', function (): void {
    $schemaFile = getenv('ENGINE_CONFIGURATION_SCHEMA');
    if (! is_string($schemaFile) || ! is_file($schemaFile)) {
        $this->markTestSkipped('ENGINE_CONFIGURATION_SCHEMA does not name the schema of gorgone-rs');
    }
    $schema = json_decode((string) file_get_contents($schemaFile), true, 512, JSON_THROW_ON_ERROR);
    expect($schema['properties']['version']['const'])->toBe(EngineConfigurationBuilder::VERSION)
        ->and(array_keys($schema['properties']))->toContain('timeperiods', 'recurring_downtimes')
        ->and(array_keys($schema['$defs']['host']['properties']))->toContain('check_period', 'timezone')
        ->and(array_keys($schema['properties']['settings']['properties']))
        ->toContain('use_timezone', 'retention_update_interval', 'use_retained_scheduling_info');
});

it('should write the maps as JSON objects', function (): void {
    $this->builder->addObject(EngineConfigurationBuilder::KIND_HOST, ['host_name' => 'srv-1', '_0' => 'zero']);

    $document = json_decode($this->builder->toJson(), false, 512, JSON_THROW_ON_ERROR);
    expect($document->hosts[0]->macros)->toEqual((object) ['0' => 'zero']);
});

it('should recognize centreon-engine from the engine name of a poller', function (?string $engineName, bool $expected): void {
    expect(EngineConfigurationBuilder::isCentreonEngine($engineName))->toBe($expected);
})->with([
    ['centreon-engine', true],
    [' Centreon-Engine ', true],
    ['centengine', false],
    ['', false],
    [null, false],
]);
