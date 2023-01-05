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
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\TimeRange;

$timeRange = new TimeRange('00:01-02:00');
$dayRange = '';

it(
    'should throw an exception if id id less than of 1',
    function () use ($dayRange, $timeRange): void {
        new ExtraTimePeriod(0, $dayRange, $timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::min(
        0,
        1,
        'ExtraTimePeriod::id'
    )->getMessage()
);

$dayRange = '     ';
it(
    'should throw exception if day range consists only of space',
    function () use ($dayRange, $timeRange): void {
        new ExtraTimePeriod(1, $dayRange, $timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::notEmpty(
        'ExtraTimePeriod::dayRange'
    )->getMessage()
);

$dayRange = '00:00-01:00 ';
it(
    'should apply trim on the day range value',
    function () use ($dayRange, $timeRange): void {
        $extraTimePeriod = new ExtraTimePeriod(1, $dayRange, $timeRange);
        expect(trim($dayRange))->toBe($extraTimePeriod->getDayRange());
    }
);
