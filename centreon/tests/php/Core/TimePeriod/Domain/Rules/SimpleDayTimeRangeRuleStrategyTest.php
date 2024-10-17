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

namespace Tests\Core\TimePeriod\Domain\Rules;

use Core\TimePeriod\Domain\Rules\Strategies\SimpleDayTimeRangeRuleStrategy;

it('return true if DateTimes are within the time range on the specified day', function (): void {
    $ranges = [
        ['start' => '00:00', 'end' => '09:00'],
        ['start' => '17:00', 'end' => '24:00']
    ];
    $day = (int)(new \DateTimeImmutable())->format('N');
    $now = new \DateTime();
    $now->setTime(8, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(true);
});

it('return true if DateTimes swapped are within the time range on the specified day', function (): void {
    $ranges = [
        ['start' => '17:00', 'end' => '24:00'],
        ['start' => '00:00', 'end' => '09:00']
    ];
    $day = (int)(new \DateTimeImmutable())->format('N');
    $now = new \DateTime();
    $now->setTime(8, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(true);

    $now->setTime(18, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(true);
});

it('return false if DateTimes are outside the time range on the specified day', function (): void {
    $ranges = [
        ['start' => '17:00', 'end' => '24:00'],
        ['start' => '00:00', 'end' => '09:00']
    ];
    $day = (int)(new \DateTimeImmutable())->format('N');
    $now = new \DateTime();
    $now->setTime(10, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(false);

    $now->setTime(16, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(false);
});


it('return false if the day is not today', function (): void {
    $ranges = [
        ['start' => '00:00', 'end' => '09:00'],
        ['start' => '17:00', 'end' => '24:00']
    ];
    $yesterday = (new \DateTimeImmutable())->modify('+1 day');
    $day = (int)$yesterday->format('N');
    $now = new \DateTime();
    $now->setTime(8, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(false);

    $now->setTime(18, 30);
    $isWithin = (new SimpleDayTimeRangeRuleStrategy())->isIncluded($now, $day, $ranges);
    expect($isWithin)->toBe(false);
});
