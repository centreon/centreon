<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Poller;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CentralAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAddressProvider(): iterable
    {
        yield 'hostname' => ['central.example.com', 'central.example.com'];

        yield 'IPv4 address' => ['10.0.0.1', '10.0.0.1'];

        yield 'IPv6 address' => ['2001:db8::1', '2001:db8::1'];

        yield 'hostname with port' => ['central.example.com:8443', 'central.example.com:8443'];

        yield 'hostname with base path' => ['orga.euwest1.example.com/platform', 'orga.euwest1.example.com/platform'];

        yield 'hostname with port and multi-segment base path' => [
            'central.example.com:8443/base/path',
            'central.example.com:8443/base/path',
        ];

        yield 'IPv4 with base path' => ['10.0.0.1/centreon', '10.0.0.1/centreon'];

        yield 'surrounding whitespace is trimmed' => ['  central.example.com  ', 'central.example.com'];

        yield 'trailing slash is removed' => ['central.example.com/platform/', 'central.example.com/platform'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddressProvider(): iterable
    {
        yield 'empty' => [''];

        yield 'protocol scheme' => ['https://central.example.com'];

        yield 'query string' => ['central.example.com/path?foo=bar'];

        yield 'fragment' => ['central.example.com/path#anchor'];

        yield 'invalid host characters' => ['central_bad!.example.com/path'];

        yield 'empty path segment' => ['central.example.com//path'];

        yield 'invalid path characters' => ['central.example.com/pa th'];

        yield 'port out of range' => ['central.example.com:70000'];

        yield 'longer than 255 characters' => [str_repeat('a', 250) . '.example.com'];
    }

    #[DataProvider('validAddressProvider')]
    public function testAcceptsValidAddress(string $rawValue, string $expectedValue): void
    {
        $address = new CentralAddress($rawValue);

        self::assertSame($expectedValue, $address->value);
    }

    #[DataProvider('invalidAddressProvider')]
    public function testRejectsInvalidAddress(string $rawValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CentralAddress($rawValue);
    }
}
