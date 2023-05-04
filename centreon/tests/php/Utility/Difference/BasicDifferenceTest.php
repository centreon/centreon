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

declare(strict_types=1);

namespace Tests\Utility;

use Utility\Difference\BasicDifference;

it(
    'should compute getAdded()',
    fn($before, $after, $expected) => expect((new BasicDifference($before, $after))->getAdded())
        ->toBe($expected)
)
    ->with(
        [
            // Integers
            [[1, 2, 3], [4, 5, 6], [4, 5, 6]],
            [[1, 2, 3], [3, 4], [1 => 4]],
            [[1, 2, 3], [1, 2, 3], []],
            [[1, 2, 3], [], []],
            // Strings
            [['a', 'b', 'c'], ['c', 'd'], [1 => 'd']],
            // Mixed
            [[1, 2, '3'], [3, '4'], [1 => '4']],
        ]
    );

it(
    'should compute getRemoved()',
    fn($before, $after, $expected) => expect((new BasicDifference($before, $after))->getRemoved())
        ->toBe($expected)
)
    ->with(
        [
            // Integers
            [[1, 2, 3], [4, 5, 6], [1, 2, 3]],
            [[1, 2, 3], [3, 4], [1, 2]],
            [[1, 2, 3], [1, 2, 3], []],
            [[1, 2, 3], [], [1, 2, 3]],
            [[1, 2, 3], [1], [1 => 2, 3]],
            // Strings
            [['a', 'b', 'c'], ['c', 'd'], ['a', 'b']],
            // Mixed
            [[1, 2, '3'], [3, '4'], [1, 2]],
            [[1, 2, 3], ['2'], [1, 2 => 3]],
        ]
    );

it(
    'should compute getCommon()',
    fn($before, $after, $expected) => expect((new BasicDifference($before, $after))->getCommon())
        ->toBe($expected)
)
    ->with(
        [
            // Integers
            [[1, 2, 3], [4, 5, 6], []],
            [[1, 2, 3], [3, 4], [2 => 3]],
            [[1, 2, 3], [3, 2, 1], [1, 2, 3]],
            [[1, 2, 3], [], []],
            [[1, 2, 3], [2], [1 => 2]],
            // Strings
            [['a', 'b', 'c'], ['c', 'd'], [2 => 'c']],
            // Mixed
            [[1, 2, '3'], [3, '4'], [2 => '3']],
            [[1, 2, 3], ['2'], [1 => 2]],
        ]
    );
