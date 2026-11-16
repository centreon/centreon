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

namespace Tests\Core\Metric\Domain\TimeSeries;

use Core\Metric\Domain\TimeSeries\StatusTimeline;

it('should turn the rrdtool status rows into intervals', function (): void {
    // status RRD written with 100 (OK), 75 (WARNING), 0 (CRITICAL) and one gap, read by
    // "rrdtool graph --imgformat=JSONTIME" as CentreonGraphStatus does
    $rrdtool = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Infrastructure/Graph/fixtures/rrdtool-status.json'),
        true
    );

    expect(StatusTimeline::fromRows($rrdtool['data']))->toBe([
        'critical' => [['start' => '1700004000', 'end' => '1700004900']],
        'warning' => [['start' => '1700002200', 'end' => '1700004000']],
        'ok' => [
            ['start' => '1700000100', 'end' => '1700002200'],
            ['start' => '1700004900', 'end' => '1700007000'],
        ],
        'unknown' => [['start' => '1700007000', 'end' => '1700007000']],
    ]);
});

it('should map the status values of the contract', function (float|int|null $value, string $status): void {
    $timeline = StatusTimeline::fromRows([['10', $value], ['20', $value]]);

    expect($timeline[$status])->toBe([['start' => '10', 'end' => '20']]);
})->with([
    'OK' => [100.0, 'ok'],
    'WARNING' => [75.0, 'warning'],
    'CRITICAL' => [0.0, 'critical'],
    'no value (UNKNOWN is not written)' => [null, 'unknown'],
    'OK and WARNING in the same step' => [87.5, 'warning'],
    'WARNING and CRITICAL in the same step' => [37.5, 'critical'],
    'integer value' => [100, 'ok'],
]);

it('should return no interval without rows', function (): void {
    expect(StatusTimeline::fromRows([]))->toBe(['critical' => [], 'warning' => [], 'ok' => [], 'unknown' => []]);
});
