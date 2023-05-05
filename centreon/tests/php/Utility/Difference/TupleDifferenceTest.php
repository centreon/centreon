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

use Utility\Difference\TupleDifference;

it(
    'should compute getAdded()',
    fn($before, $after, $expected) => expect((new TupleDifference($before, $after))->getAdded())
        ->toBe($expected)
)
    ->with(
        [
            [[[1, 'a'], [1, 'b'], [3, 42]], [[2, 'a'], [3, 'b'], [4, 42]], [[2, 'a'], [3, 'b'], [4, 42]]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'b'], [4, 42]], [1 => [4, 42]]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'a'], [1, 'b'], [3, 42]], []],
            [[[1, 'a'], [1, 'b'], [3, 42]], [], []],
            [[[1, 'a']], [['a', 1]], [['a', 1]]],
            [[[5], ['b']], [[6], ['b']], [[6]]],
        ]
    );

it(
    'should compute getRemoved()',
    fn($before, $after, $expected) => expect((new TupleDifference($before, $after))->getRemoved())
        ->toBe($expected)
)
    ->with(
        [
            [[[1, 'a'], [1, 'b'], [3, 42]], [[2, 'a'], [3, 'b'], [4, 42]], [[1, 'a'], [1, 'b'], [3, 42]]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'b'], [4, 42]], [[1, 'a'], 2 => [3, 42]]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'a'], [1, 'b'], [3, 42]], []],
            [[[1, 'a'], [1, 'b'], [3, 42]], [], [[1, 'a'], [1, 'b'], [3, 42]]],
            [[[1, 'a']], [['a', 1]], [[1, 'a']]],
            [[[5], ['b']], [[6], ['b']], [[5]]],
        ]
    );

it(
    'should compute getCommon()',
    fn($before, $after, $expected) => expect((new TupleDifference($before, $after))->getCommon())
        ->toBe($expected)
)
    ->with(
        [
            [[[1, 'a'], [1, 'b'], [3, 42]], [[2, 'a'], [3, 'b'], [4, 42]], []],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'b'], [4, 42]], [1 => [1, 'b']]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [[1, 'a'], [1, 'b'], [3, 42]], [[1, 'a'], [1, 'b'], [3, 42]]],
            [[[1, 'a'], [1, 'b'], [3, 42]], [], []],
            [[[1, 'a']], [['a', 1]], []],
            [[[5], ['b']], [[6], ['b']], [1 => ['b']]],
        ]
    );
