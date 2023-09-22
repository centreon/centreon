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

namespace Tests\Core\Common\Infrastructure;

use Core\Common\Infrastructure\FeatureFlags;

it(
    'should be ok for On-Prem',
    fn(string $json, array $expected) => expect((new FeatureFlags(false, $json))->getAll())
        ->toBe($expected)
)->with([
    ['not-a-valid-json', []],
    ['{"foo": 0}', ['foo' => false]],
    ['{"foo": 1}', ['foo' => true]],
    ['{"foo": 2}', ['foo' => false]],
    ['{"foo": 3}', ['foo' => true]],
]);

it(
    'should be ok for Cloud',
    fn(string $json, array $expected) => expect((new FeatureFlags(true, $json))->getAll())
        ->toBe($expected)
)->with([
    ['not-a-valid-json', []],
    ['{"foo": 0}', ['foo' => false]],
    ['{"foo": 1}', ['foo' => false]],
    ['{"foo": 2}', ['foo' => true]],
    ['{"foo": 3}', ['foo' => true]],
]);

it(
    'should be false by default for not existing features',
    fn() => expect((new FeatureFlags(false, '{}'))->isEnabled('not-existing'))
        ->toBe(false)
);

it(
    'should ignore wrong feature bitmask value',
    fn(string $json) => expect((new FeatureFlags(false, $json))->getAll())->toBe([])
)->with([
    ['{"foo": null}'],
    ['{"foo": true}'],
    ['{"foo": 3.14}'],
    ['{"foo": "abc"}'],
    ['{"foo": [1, 2, 3]}'],
    ['{"foo": {"bar": 123}}'],
]);

it(
    'should ignore wrong feature name considered as integer',
    fn(int $int) => expect((new FeatureFlags(false, '{"' . $int . '": 1, "i' . $int . '": 1}'))->getAll())
        ->toBe(['i' . $int => true])
)->with([
    [0],
    [42],
    [-66],
]);
