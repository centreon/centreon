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
use Core\TimePeriod\Domain\Model\NewExtraTimePeriod;
use Core\TimePeriod\Domain\Model\TimeRange;

$timeRange = new TimeRange('00:01-02:00');

it(
    'should throw exception with empty day range',
    function () use ($timeRange): void {
        new NewExtraTimePeriod('', $timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        NewExtraTimePeriod::MIN_DAY_RANGE_LENGTH,
        'NewExtraTimePeriod::dayRange'
    )->getMessage()
);

$badValue = str_repeat('_', NewExtraTimePeriod::MAX_DAY_RANGE_LENGTH + 1);
it(
    'should throw exception with too long day range',
    function () use ($timeRange, $badValue): void {
        new NewExtraTimePeriod($badValue, $timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::maxLength(
        $badValue,
        mb_strlen($badValue),
        NewExtraTimePeriod::MAX_DAY_RANGE_LENGTH,
        'NewExtraTimePeriod::dayRange'
    )->getMessage()
);

it(
    'should throw exception if day range consists only of space',
    function () use ($timeRange): void {
        new NewExtraTimePeriod('   ', $timeRange);
    }
)->throws(
    \InvalidArgumentException::class,
    AssertionException::minLength(
        '',
        0,
        NewExtraTimePeriod::MIN_DAY_RANGE_LENGTH,
        'NewExtraTimePeriod::dayRange'
    )->getMessage()
);

it(
    'should apply trim on the day range value',
    function () use ($timeRange): void {
        $dayRange = '00:00-01:00 ';
        $extraTimePeriod = new NewExtraTimePeriod($dayRange, $timeRange);
        expect(trim($dayRange))->toBe($extraTimePeriod->getDayRange());
    }
);
