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

use Core\Metric\Domain\TimeSeries\TimeGrid;

it('should never go below the check interval', function (): void {
    // 2 hours: 15 s would give 480 points, the check interval wins
    expect(TimeGrid::computeStep(0, 7200, 300))->toBe(300);
});

it('should keep about 500 points over a long period', function (): void {
    // 31 days with a 1 minute check: 5357 s, i.e. 500 points
    expect(TimeGrid::computeStep(0, 31 * 86400, 60))->toBe(5357)
        ->and(TimeGrid::forPeriod(1_700_000_000, 1_700_000_000 + 31 * 86400, 60)->count())->toBeLessThanOrEqual(501);
});

it('should not return a step below one second', function (): void {
    expect(TimeGrid::computeStep(0, 10, 0))->toBe(1);
});

it('should lay the timestamps out as rrdtool does', function (int $start, int $end): void {
    // rrdtool graph --start 1700000000 (or 1700000010) --end 1700006900 (or 1700006950), RRD step 300:
    // rows from 1700000100 to 1700007000 (tests/php/Core/Metric/Infrastructure/Graph/fixtures/rrdtool-graph.json)
    $grid = TimeGrid::forPeriod($start, $end, 300);

    expect($grid->getFirst())->toBe(1_700_000_100)
        ->and($grid->getLast())->toBe(1_700_007_000)
        ->and($grid->getStep())->toBe(300)
        ->and($grid->count())->toBe(24)
        ->and($grid->getTimestamps())->toHaveCount(24)
        ->and($grid->indexOf(1_700_000_400))->toBe(1)
        ->and($grid->indexOf(1_700_000_401))->toBeNull()
        ->and($grid->indexOf(1_700_007_300))->toBeNull();
})->with([
    'aligned on nothing' => [1_700_000_000, 1_700_006_900],
    'unaligned bounds' => [1_700_000_010, 1_700_006_950],
]);

it('should refuse an empty period', function (): void {
    TimeGrid::forPeriod(100, 100, 60);
})->throws(\InvalidArgumentException::class);
