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

namespace Tests\Core\Proxy\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Proxy\Domain\Model\Proxy;

beforeEach(function (): void {
    $this->createProxy = fn(array $arguments = []): Proxy => new Proxy(...[
        'url' => 'localhost',
        'port' => 0,
        'login' => 'login',
        'password' => 'password',
        ...$arguments,
    ]);
});

it('should throw an exception when the URL property is empty', function(): void {
    ($this->createProxy)(['url' => '    ']);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Proxy:url')->getMessage()
);

it('should throw an exception when the port property is negative', function(): void {
    ($this->createProxy)(['port' => -1]);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::min(-1, 0, 'Proxy:port')->getMessage()
);

it('should throw an exception when the login property is empty', function(): void {
    ($this->createProxy)(['login' => '   ']);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Proxy:login')->getMessage()
);

it('should throw an exception when the password property is empty', function(): void {
    ($this->createProxy)(['password' => '   ']);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Proxy:password')->getMessage()
);

it('should be generated correctly as a character string', function(): void {
    $proxy = new Proxy('localhost');
    expect((string) $proxy)->toBe('http://localhost');
    $proxy = new Proxy('localhost', 80);
    expect((string) $proxy)->toBe('http://localhost:80');
    $proxy = new Proxy('localhost', 80, 'login');
    expect((string) $proxy)->toBe('http://login:@localhost:80');
    $proxy = new Proxy('localhost', 80, 'login', 'password');
    expect((string) $proxy)->toBe('http://login:password@localhost:80');
    $proxy = new Proxy('localhost', 80, null, 'password');
    expect((string) $proxy)->toBe('http://localhost:80');
    $proxy = new Proxy('localhost', null, null, 'password');
    expect((string) $proxy)->toBe('http://localhost');

    $proxy = new Proxy('https://localhost');
    expect((string) $proxy)->toBe('https://localhost');
    $proxy = new Proxy('https://localhost', 80);
    expect((string) $proxy)->toBe('https://localhost:80');
    $proxy = new Proxy('https://localhost', 80, 'login');
    expect((string) $proxy)->toBe('https://login:@localhost:80');
    $proxy = new Proxy('https://localhost', 80, 'login', 'password');
    expect((string) $proxy)->toBe('https://login:password@localhost:80');
    $proxy = new Proxy('https://localhost', 80, null, 'password');
    expect((string) $proxy)->toBe('https://localhost:80');
    $proxy = new Proxy('https://localhost', null, null, 'password');
    expect((string) $proxy)->toBe('https://localhost');
});
