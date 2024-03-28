<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Tests\Core\Common\Infrastructure;

use Core\Common\Infrastructure\Repository\ValuesByPackets;

const NO_LIMIT = 1000000;

it('should divide the values according to the maxItemsByPackets limitation only', function(): void {
    $maxItemsByPackets = 5;
    $maxQueryStringLength = NO_LIMIT;
    $values = range(1, 1000);
    $valuesByPackets = new ValuesByPackets($values, $maxItemsByPackets, $maxQueryStringLength);
    foreach ($valuesByPackets as $iterationNumber => $valuesToCheck) {
        expect(count($valuesToCheck))->toBeLessThanOrEqual($maxItemsByPackets);
        for($index = 0; $index < $maxItemsByPackets && array_key_exists($index, $valuesToCheck); $index++) {
            expect($values[$index + ($iterationNumber * $maxItemsByPackets)])->toEqual($valuesToCheck[$index]);
        }
    }
});

it('should divide the values according to the maxQueryStringLength limitation only', function(): void {
    $maxItemsByPackets = NO_LIMIT;
    $maxQueryStringLength = 50;
    $values = range(1, 1000);
    $valueSeparator = ',';
    $valuesByPackets = new ValuesByPackets($values, $maxItemsByPackets, $maxQueryStringLength, mb_strlen($valueSeparator));
    foreach ($valuesByPackets as $valuesToCheck) {
        expect(mb_strlen(implode($valueSeparator, $valuesToCheck)) <= $maxQueryStringLength)->toBeTrue();
    }
});

it('should divide the values according to all the limitations', function(): void {
    $maxItemsByPackets = 10;
    $maxQueryStringLength = 50;
    $values = range(1, 1000);
    $valueSeparator = ',';
    $valuesByPackets = new ValuesByPackets($values, $maxItemsByPackets, $maxQueryStringLength, mb_strlen($valueSeparator));
    foreach ($valuesByPackets as $iterationNumber => $valuesToCheck) {
        expect(count($valuesToCheck))->toBeLessThanOrEqual($maxItemsByPackets);
        expect(mb_strlen(implode($valueSeparator, $valuesToCheck)) <= $maxQueryStringLength)->toBeTrue();
        for($index = 0; $index < $maxItemsByPackets && array_key_exists($index, $valuesToCheck); $index++) {
            expect($values[$index + ($iterationNumber * $maxItemsByPackets)])->toEqual($valuesToCheck[$index]);
        }
    }
});
