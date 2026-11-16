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

require_once __DIR__ . '/../../../../../www/class/config-generate/abstract/object.class.php';
require_once __DIR__ . '/../../../../../www/class/config-generate/recurringdowntime.class.php';

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function recurringDowntimePeriod(array $overrides = []): array
{
    return array_merge(
        [
            'dtp_start_time' => '22:00:00',
            'dtp_end_time' => '23:30:00',
            'dtp_day_of_week' => null,
            'dtp_month_cycle' => 'all',
            'dtp_day_of_month' => null,
            'dtp_fixed' => '1',
            'dtp_duration' => null,
        ],
        $overrides
    );
}

it('formats a weekly period', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod(['dtp_day_of_week' => '5,1,3'])))
        ->toBe(['weekly:1,3,5 22:00-23:30 fixed']);
});

it('reads day 0 of a weekly period as Sunday', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_day_of_week' => '0,6',
        'dtp_month_cycle' => 'none',
    ])))->toBe(['weekly:6,7 22:00-23:30 fixed']);
});

it('formats a monthly period with a flexible duration in seconds', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_start_time' => '02:00:00',
        'dtp_end_time' => '04:00:00',
        'dtp_month_cycle' => 'none',
        'dtp_day_of_month' => '15,1,31',
        'dtp_fixed' => '0',
        'dtp_duration' => '3600',
    ])))->toBe(['monthly:1,15,31 02:00-04:00 flexible:3600']);
});

it('formats a specific date period, one line per day of week', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_start_time' => '01:00:00',
        'dtp_end_time' => '05:00:00',
        'dtp_day_of_week' => '7',
        'dtp_month_cycle' => 'last',
    ])))->toBe(['nth:last:7 01:00-05:00 fixed']);

    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_day_of_week' => '1,2',
        'dtp_month_cycle' => 'second',
    ])))->toBe(['nth:second:1 22:00-23:30 fixed', 'nth:second:2 22:00-23:30 fixed']);
});

it('accepts 24:00 as end time', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_start_time' => '00:00:00',
        'dtp_end_time' => '24:00:00',
        'dtp_day_of_week' => '6',
    ])))->toBe(['weekly:6 00:00-24:00 fixed']);
});

it('writes a flexible period without duration as a zero duration, as the cron sends it', function (): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod([
        'dtp_day_of_week' => '1',
        'dtp_fixed' => '0',
    ])))->toBe(['weekly:1 22:00-23:30 flexible:0']);
});

it('skips a period that Engine would reject', function (array $overrides): void {
    expect(RecurringDowntime::formatPeriod(recurringDowntimePeriod($overrides)))->toBe([]);
})->with([
    'end before start' => [['dtp_day_of_week' => '1', 'dtp_start_time' => '23:00:00', 'dtp_end_time' => '01:00:00']],
    'empty range' => [['dtp_day_of_week' => '1', 'dtp_start_time' => '10:00:00', 'dtp_end_time' => '10:00:00']],
    'after 24:00' => [['dtp_day_of_week' => '1', 'dtp_end_time' => '24:30:00']],
    'no day' => [[]],
    'unknown cycle' => [['dtp_day_of_week' => '1', 'dtp_month_cycle' => 'fifth']],
    'day of month out of range' => [['dtp_month_cycle' => 'none', 'dtp_day_of_month' => '32']],
]);

it('resolves the targets on the poller only', function (): void {
    $targets = RecurringDowntime::resolveTargets(
        [
            'hosts' => [14, 99],
            'hostgroups' => [3, 4],
            'services' => [[14, 26], [15, 31], [99, 1]],
            'servicegroups' => [8, 9],
        ],
        [14, 15, 16],
        [14 => [26, 27], 15 => [30]],
        [3 => [16, 14]],
        [8 => [[15, 30], [14, 26]]],
    );

    // 99: other poller; hostgroup 4 and servicegroup 9 have no member here; 15:31 is not generated
    expect($targets)->toBe([
        'host_ids' => [14, 16],
        'service_ids' => ['14:26', '15:30'],
    ]);
});

it('resolves no target when nothing is on the poller', function (): void {
    expect(RecurringDowntime::resolveTargets(
        ['hosts' => [1], 'hostgroups' => [], 'services' => [[1, 2]], 'servicegroups' => []],
        [],
        [],
        [],
        [],
    ))->toBe(['host_ids' => [], 'service_ids' => []]);
});

it('formats the configuration object', function (): void {
    expect(RecurringDowntime::formatObject([
        'downtime_id' => 3,
        'downtime_name' => "Weekly; maintenance\n",
        'timezone' => ':Europe/Paris',
        'host_ids' => [14, 15],
        'service_ids' => ['14:26', '15:30'],
        'period' => ['weekly:1,2,3,4,5 22:00-23:30 fixed', 'monthly:1,15 02:00-04:00 flexible:3600'],
    ]))->toBe(
        "\ndefine recurringdowntime {\n"
        . "    downtime_id                    3\n"
        . "    downtime_name                  Weekly maintenance\n"
        . "    timezone                       :Europe/Paris\n"
        . "    host_ids                       14,15\n"
        . "    service_ids                    14:26,15:30\n"
        . "    period                         weekly:1,2,3,4,5 22:00-23:30 fixed\n"
        . "    period                         monthly:1,15 02:00-04:00 flexible:3600\n"
        . "}\n"
    );
});

it('omits the optional directives that are empty', function (): void {
    expect(RecurringDowntime::formatObject([
        'downtime_id' => 4,
        'downtime_name' => '',
        'timezone' => null,
        'host_ids' => [],
        'service_ids' => ['1:2'],
        'period' => ['nth:first:1 01:00-02:00 fixed'],
    ]))->toBe(
        "\ndefine recurringdowntime {\n"
        . "    downtime_id                    4\n"
        . "    service_ids                    1:2\n"
        . "    period                         nth:first:1 01:00-02:00 fixed\n"
        . "}\n"
    );
});
