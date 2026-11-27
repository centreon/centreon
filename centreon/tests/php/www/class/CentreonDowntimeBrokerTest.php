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

namespace Tests\www\class;

use CentreonDowntimeBroker;
use CentreonGMT;
use DateTime;
use DateTimeZone;
use ReflectionClass;
use ReflectionMethod;

beforeEach(function (): void {
    // The constructor opens database connections that are not needed here
    $this->broker = (new ReflectionClass(CentreonDowntimeBroker::class))->newInstanceWithoutConstructor();
    $this->getDowntimeTimezone = new ReflectionMethod(CentreonDowntimeBroker::class, 'getDowntimeTimezone');

    // In-memory timezone list, with the host located in a timezone other than the platform one
    $this->gmt = new class () extends CentreonGMT {
        public function __construct()
        {
            $this->timezoneById = [1 => 'UTC', 2 => 'Europe/Paris', 3 => 'America/New_York'];
            $this->timezones = ['UTC' => 1, 'Europe/Paris' => 2, 'America/New_York' => 3];
            $this->sDefaultTimezone = '1';
            $this->hostLocations = [10 => 'America/New_York'];
        }
    };
});

it('uses the timezone stored with the downtime', function (): void {
    $timezone = $this->getDowntimeTimezone->invoke(
        $this->broker,
        ['dt_timezone_id' => '2', 'host_id' => 10],
        $this->gmt
    );

    expect($timezone->getName())->toBe('Europe/Paris');
});

it('uses the host timezone when the downtime has no timezone', function (?string $timezoneId): void {
    $timezone = $this->getDowntimeTimezone->invoke(
        $this->broker,
        ['dt_timezone_id' => $timezoneId, 'host_id' => 10],
        $this->gmt
    );

    expect($timezone->getName())->toBe('America/New_York');
})->with([
    'downtime created before the timezone was stored' => [null],
    'downtime created by a user without timezone' => ['0'],
]);

it('keeps the hours of the downtime timezone across daylight saving time', function (string $date, string $expectedUtc): void {
    $timezone = $this->getDowntimeTimezone->invoke(
        $this->broker,
        ['dt_timezone_id' => '2', 'host_id' => 10],
        $this->gmt
    );

    $start = new DateTime($date . ' 00:00', $timezone);

    expect($start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe($expectedUtc);
})->with([
    'summer' => ['2026-07-13', '2026-07-12 22:00'],
    'winter' => ['2026-12-14', '2026-12-13 23:00'],
]);
