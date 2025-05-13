<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Common\Domain\Exception;

use Core\Common\Domain\Exception\ExceptionFormatter;
use Core\Common\Domain\Exception\RepositoryException;

it('test format native exception without previous', function () {
    $exception = new \LogicException('logic_exception_message', 99);
    $format = ExceptionFormatter::format($exception);
    expect($format)->toBeArray()
        ->and($format)->toHaveKey('type')
        ->and($format['type'])->toBe('LogicException')
        ->and($format)->toHaveKey('message')
        ->and($format['message'])->toBe('logic_exception_message')
        ->and($format)->toHaveKey('file')
        ->and($format['file'])->toBe(__FILE__)
        ->and($format)->toHaveKey('line')
        ->and($format['line'])->toBe(__LINE__ - 10)
        ->and($format)->toHaveKey('code')
        ->and($format['code'])->toBe(99)
        ->and($format)->toHaveKey('class')
        ->and($format['class'])->toBe('P\\Tests\\php\\Core\\Common\\Domain\\Exception\\ExceptionFormatterTest')
        ->and($format)->toHaveKey('method')
        ->and($format['method'])->toBe('Tests\\Core\\Common\\Domain\\Exception\\{closure}')
        ->and($format)->toHaveKey('previous')
        ->and($format['previous'])->toBeNull();
});

it('test format business logic exception without previous', function () {
    $exception = new RepositoryException('repository_exception_message');
    $format = ExceptionFormatter::format($exception);
    expect($format)->toBeArray()
        ->and($format)->toHaveKey('type')
        ->and($format['type'])->toBe('Core\\Common\\Domain\\Exception\\RepositoryException')
        ->and($format)->toHaveKey('message')
        ->and($format['message'])->toBe('repository_exception_message')
        ->and($format)->toHaveKey('file')
        ->and($format['file'])->toBe(__FILE__)
        ->and($format)->toHaveKey('line')
        ->and($format['line'])->toBe(__LINE__ - 10)
        ->and($format)->toHaveKey('code')
        ->and($format['code'])->toBe(1)
        ->and($format)->toHaveKey('class')
        ->and($format['class'])->toBe('P\\Tests\\php\\Core\\Common\\Domain\\Exception\\ExceptionFormatterTest')
        ->and($format)->toHaveKey('method')
        ->and($format['method'])->toBe('Tests\\Core\\Common\\Domain\\Exception\\{closure}')
        ->and($format)->toHaveKey('previous')
        ->and($format['previous'])->toBeNull();
});

it('test format business logic exception without previous with context', function () {
    $exception = new RepositoryException('repository_exception_message', ['contact' => 1, 'name' => 'John']);
    $format = ExceptionFormatter::format($exception);
    expect($format)->toBeArray()
        ->and($format)->toHaveKey('type')
        ->and($format['type'])->toBe('Core\\Common\\Domain\\Exception\\RepositoryException')
        ->and($format)->toHaveKey('message')
        ->and($format['message'])->toBe('repository_exception_message')
        ->and($format)->toHaveKey('file')
        ->and($format['file'])->toBe(__FILE__)
        ->and($format)->toHaveKey('line')
        ->and($format['line'])->toBe(__LINE__ - 10)
        ->and($format)->toHaveKey('code')
        ->and($format['code'])->toBe(1)
        ->and($format)->toHaveKey('class')
        ->and($format['class'])->toBe('P\\Tests\\php\\Core\\Common\\Domain\\Exception\\ExceptionFormatterTest')
        ->and($format)->toHaveKey('method')
        ->and($format['method'])->toBe('Tests\\Core\\Common\\Domain\\Exception\\{closure}')
        ->and($format)->toHaveKey('previous')
        ->and($format['previous'])->toBeNull();
});

it('test format business logic exception with previous', function () {
    $exception = new RepositoryException(
        'repository_exception_message', previous: new \LogicException(
        'logic_exception_message', 99
    ));
    $format = ExceptionFormatter::format($exception);
    expect($format)->toBeArray()
        ->and($format)->toHaveKey('type')
        ->and($format['type'])->toBe('Core\\Common\\Domain\\Exception\\RepositoryException')
        ->and($format)->toHaveKey('message')
        ->and($format['message'])->toBe('repository_exception_message')
        ->and($format)->toHaveKey('file')
        ->and($format['file'])->toBe(__FILE__)
        ->and($format)->toHaveKey('line')
        ->and($format['line'])->toBe(__LINE__ - 13)
        ->and($format)->toHaveKey('code')
        ->and($format['code'])->toBe(1)
        ->and($format)->toHaveKey('class')
        ->and($format['class'])->toBe('P\\Tests\\php\\Core\\Common\\Domain\\Exception\\ExceptionFormatterTest')
        ->and($format)->toHaveKey('method')
        ->and($format['method'])->toBe('Tests\\Core\\Common\\Domain\\Exception\\{closure}')
        ->and($format)->toHaveKey('previous')
        ->and($format['previous'])->toBeArray()
        ->and($format['previous'])->toHaveKey('type')
        ->and($format['previous']['type'])->toBe('LogicException')
        ->and($format['previous'])->toHaveKey('message')
        ->and($format['previous']['message'])->toBe('logic_exception_message')
        ->and($format['previous'])->toHaveKey('file')
        ->and($format['previous']['file'])->toBe(__FILE__)
        ->and($format['previous'])->toHaveKey('line')
        ->and($format['previous']['line'])->toBe(__LINE__ - 28)
        ->and($format['previous'])->toHaveKey('code')
        ->and($format['previous']['code'])->toBe(99)
        ->and($format['previous'])->toHaveKey('class')
        ->and($format['previous']['class'])->toBe(
            'P\\Tests\\php\\Core\\Common\\Domain\\Exception\\ExceptionFormatterTest'
        )
        ->and($format['previous'])->toHaveKey('method')
        ->and($format['previous']['method'])->toBe('Tests\\Core\\Common\\Domain\\Exception\\{closure}')
        ->and($format['previous'])->toHaveKey('previous')
        ->and($format['previous']['previous'])->toBeNull();
});
