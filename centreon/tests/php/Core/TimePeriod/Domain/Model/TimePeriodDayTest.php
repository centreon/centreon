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
use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\TimeRange;

it('should throw an exception if the day id is less than 1', function() {
    new Day(0, new TimeRange('00:00-12:00'));
})->throws(
    \InvalidArgumentException::class,
    AssertionException::min(
        0,
        1,
        'TimePeriodDay::day'
    )->getMessage()
);

it('should throw an exception if the day id is more than 7', function() {
    new Day(8, new TimeRange('00:00-12:00'));
})->throws(
    \InvalidArgumentException::class,
    AssertionException::max(
        8,
        7,
        'TimePeriodDay::day'
    )->getMessage()
);
