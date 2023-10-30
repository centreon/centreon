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

namespace Tests\Core\Common\Application\Type;

use Core\Common\Application\Type\NoValue;

it(
    'should return the same value if not a NoValue object',
    fn(mixed $value) => expect(
        \Core\Common\Application\Type\NoValue::coalesce($value, uniqid('random-default', true))
    )->toBe($value)
)->with([
    [null],
    [123],
    [123.456],
    [true],
    [false],
    ['a-string'],
    [['an' => 'array']],
]);

it(
    'should return the default value if it is a NoValue object',
    fn() => expect(
        NoValue::coalesce(new \Core\Common\Application\Type\NoValue(), $default = uniqid('random-default', true))
    )->toBe($default)
);
