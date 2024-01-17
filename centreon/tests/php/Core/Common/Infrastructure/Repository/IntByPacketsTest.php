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

namespace Tests\Core\Common\Infrastructure\Repository;

use Core\Common\Infrastructure\Repository\IntByPackets;

it('should divide the values according to the maxItemsByPackets limitation only', function(): void {
    $maxItemsByPackets = 50;
    $values = range(1, 1000);
    $valuesByPackets = new IntByPackets($values, $maxItemsByPackets);
    foreach ($valuesByPackets as $iterationNumber => $valuesToCheck) {
        expect(count($valuesToCheck))->toBeLessThanOrEqual($maxItemsByPackets);
        for($index = 0; $index < $maxItemsByPackets && array_key_exists($index, $valuesToCheck); $index++) {
            expect($values[$index + ($iterationNumber * $maxItemsByPackets)])->toEqual($valuesToCheck[$index]);
        }
    }
});
