<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Common\Infrastructure\RequestParameters\Normalizer;

use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToIntegerNormalizer;

// Testing nullability

it(
    'should success for value NULL (when allowed)',
    fn() => expect((new BoolToIntegerNormalizer(nullable: true))->normalize(null))->toBe(null)
);

// Expected success values

foreach (
    [
        // truthy
        [true, 1234],
        [1, 1234],
        ['true', 1234],
        ['TRUE', 1234],
        [1234, 1234],
        // falsy
        [false, 4321],
        [0, 4321],
        ['false', 4321],
        ['FALSE', 4321],
        [4321, 4321],
    ] as [$tested, $expected]
) {
    it(
        'should success for value ' . var_export($tested, true),
        fn() => expect((new BoolToIntegerNormalizer(4321, 1234))->normalize($tested))->toBe($expected)
    );
}

// Expected failure values

foreach (
    [
        null,
        -1,
        2,
        'FooBar',
        'TrUe',
        'fAlSe',
    ] as $tested
) {
    it(
        'should fail for value ' . var_export($tested, true),
        fn() => (new BoolToIntegerNormalizer())->normalize($tested)
    )->throws(\TypeError::class);
}