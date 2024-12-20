<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\TimePeriod\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\TimePeriod\Domain\Exception\TimeRangeException;
use Core\TimePeriod\Domain\Model\TimeRange;

$timeRange = '';
it(
    'should throw exception with empty time range',
    function () use ($timeRange): void {
        new TimeRange($timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        $timeRange,
        strlen($timeRange),
        11,
        'TimeRange::timeRange'
    )->getMessage()
);

it(
    'should throw exception with wrong time format',
    function (): void {
        new TimeRange('00:00-12d00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::badTimeRangeFormat('00:00-12d00')->getMessage()
);

it(
    'should throw exception with wrong time ranges format',
    function (): void {
        new TimeRange('00:00 12:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::badTimeRangeFormat('00:00 12:00')->getMessage()
);

it(
    'should throw exception with wrong time ranges repetition',
    function (): void {
        new TimeRange('00:00-12:00 13:00-14:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::badTimeRangeFormat('00:00-12:00 13:00-14:00')->getMessage()
);

it(
    'should throw exception when the start of the interval is equal to the end of the interval',
    function (): void {
        new TimeRange('12:00-12:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::orderTimeIntervalsNotConsistent()->getMessage()
);

it(
    'should throw exception when the start of the interval is greater than the end of the interval',
    function (): void {
        new TimeRange('12:01-12:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::orderTimeIntervalsNotConsistent()->getMessage()
);

it(
    'should throw exception when the start of the second interval is equal to the end of the first interval',
    function (): void {
        new TimeRange('00:00-12:00,12:00-14:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::orderTimeIntervalsNotConsistent()->getMessage()
);

it(
    'should throw exception when the start of the second interval is less than the end of the first interval',
    function (): void {
        new TimeRange('00:00-12:00,11:00-14:00');
    }
)->throws(
    TimeRangeException::class,
    TimeRangeException::orderTimeIntervalsNotConsistent()->getMessage()
);

it('should return a valid single array', function (): void {
        $timeRange = new TimeRange('00:00-10:00');
        expect($timeRange->getRanges())->toBeArray()->toHaveCount(1);
});

it('should return a valid multiple array', function (): void {
    $timeRange = new TimeRange('00:00-10:00,11:00-18:00');
    expect($timeRange->getRanges())->toBeArray()->toHaveCount(2);
});

it('should not throw an exception for 00:00-00:00', function (): void {
    $timeRange = new TimeRange('00:00-00:00');
    expect($timeRange->getRanges())->toBeArray()->toHaveCount(1);
});