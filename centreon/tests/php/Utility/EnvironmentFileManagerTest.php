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

use Utility\EnvironmentFileManager;

it('should add variable and format then correctly', function ($send, $expected): void {
    $env = new EnvironmentFileManager('');

    $env->add($send['key'], $send['value']);
    $var = $env->getAll();
    expect($expected['key'])
        ->toBe(key($var))
        ->and($var[$expected['key']])
        ->toBe($expected['value']);
})->with([
    // The first table contains all the data to be tested, the second contains the data to be expected.

    // Test with TRUE values
    [['key' => 'key', 'value' => true], ['key' => 'key', 'value' => true]],
    [['key' => 'key', 'value' => 'true'], ['key' => 'key', 'value' => true]],
    [['key' => 'IS_VALID', 'value' => 1], ['key' => 'IS_VALID', 'value' => true]],
    [['key' => 'IS_VALID', 'value' => '1'], ['key' => 'IS_VALID', 'value' => true]],

    // Test with FALSE values
    [['key' => 'key', 'value' => false], ['key' => 'key', 'value' => false]],
    [['key' => 'key', 'value' => 'false'], ['key' => 'key', 'value' => false]],
    [['key' => 'IS_VALID', 'value' => 0], ['key' => 'IS_VALID', 'value' => false]],
    [['key' => 'IS_VALID', 'value' => '0'], ['key' => 'IS_VALID', 'value' => false]],

    // Test with numerical values
    [['key' => 'key', 'value' => 0], ['key' => 'key', 'value' => 0]],
    [['key' => 'key', 'value' => 1], ['key' => 'key', 'value' => 1]],
    [['key' => 'key', 'value' => 1.1], ['key' => 'key', 'value' => 1.1]],
    [['key' => 'key', 'value' => '1.3'], ['key' => 'key', 'value' => 1.3]],
    [['key' => 'key', 'value' => '-1.4'], ['key' => 'key', 'value' => -1.4]],
    [['key' => 'key', 'value' => '  1.3  '], ['key' => 'key', 'value' => 1.3]],
    [['key' => 'key', 'value' => '  -1.54  '], ['key' => 'key', 'value' => -1.54]],

    // Test with literal values
    [['key' => 'key', 'value' => 'test'], ['key' => 'key', 'value' => 'test']],
    [['key' => 'IS_VALID', 'value' => 'test'], ['key' => 'IS_VALID', 'value' => 'test']],
    [['key' => 'key', 'value' => ''], ['key' => 'key', 'value' => '']],
    [['key' => 'IS_VALID', 'value' => ''], ['key' => 'IS_VALID', 'value' => '']],

    // Test with no empty key and value
    [['key' => ' key ', 'value' => ' test '], ['key' => 'key', 'value' => 'test']],
    [['key' => '   IS_VALID   ', 'value' => '  test   '], ['key' => 'IS_VALID', 'value' => 'test']],
]);

it('should not add a variable whose key begins with the comment character (#)', function (): void {
    $env = new EnvironmentFileManager('');
    $env->add('#KEY', 'data');
    $variables = $env->getAll();
    expect($variables)->toHaveCount(0);
});