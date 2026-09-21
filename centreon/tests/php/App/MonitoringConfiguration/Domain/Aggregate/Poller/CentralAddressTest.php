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

        // A bare IPv6 literal is normalized to its bracketed form: that is what goes to the
        // database and what must appear right after "scheme://".
        yield 'bare IPv6 address' => ['2001:db8::1', '[2001:db8::1]'];

        yield 'bare IPv6 address with base path' => ['2001:db8::1/platform', '[2001:db8::1]/platform'];

        yield 'bracketed IPv6 address' => ['[2001:db8::1]', '[2001:db8::1]'];

        yield 'bracketed IPv6 address with port' => ['[2001:db8::1]:8443', '[2001:db8::1]:8443'];

        yield 'bracketed IPv6 address with port and base path' => [
            '[2001:db8::1]:8443/platform',
            '[2001:db8::1]:8443/platform',
        ];

        yield 'bracketed IPv6 address in upper case' => ['[2001:DB8::1]', '[2001:DB8::1]'];

        // The IPv4-mapped form is an IPv6 address, dots included.
        yield 'bare IPv4-mapped IPv6 address' => ['::ffff:10.0.0.1', '[::ffff:10.0.0.1]'];

        yield 'bracketed IPv4-mapped IPv6 address with port' => ['[::ffff:10.0.0.1]:8443', '[::ffff:10.0.0.1]:8443'];

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

        // Bracket contents are only checked for their alphabet by IP_LITERAL_PATTERN.
        yield 'bracketed non-address' => ['[::::]'];

        yield 'bracketed IPv6 with too many groups' => ['[2001:db8:0:0:0:0:0:0:1]'];

        // The brackets are reserved to IP-literals, so an IPv4 must not wear them.
        yield 'bracketed IPv4' => ['[10.0.0.1]'];

        yield 'bracketed IPv6 with port out of range' => ['[2001:db8::1]:70000'];

        yield 'bracketed IPv6 with port zero' => ['[2001:db8::1]:0'];

        yield 'single-dot path segment' => ['central.example.com/.'];

        yield 'double-dot path segment' => ['central.example.com/..'];

        yield 'dot segment inside base path' => ['central.example.com/platform/../admin'];

        yield 'leading dot segment' => ['central.example.com/./platform'];

        yield 'longer than 255 characters' => [str_repeat('a', 250) . '.example.com'];
    }

    /**
     * The canonical value is covered by testAcceptsValidAddress; what matters here is that the
     * host stays bare for the consumers dialing the central over another protocol.
     *
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function authorityPartsProvider(): iterable
    {
        yield 'hostname' => ['central.example.com', 'central.example.com', null];

        yield 'hostname with port' => ['central.example.com:8443', 'central.example.com', null];

        yield 'hostname with base path' => [
            'orga.euwest1.example.com/platform',
            'orga.euwest1.example.com',
            'platform',
        ];

        yield 'hostname with port and multi-segment base path' => [
            'central.example.com:8443/base/path',
            'central.example.com',
            'base/path',
        ];

        // A bare IPv6 address must not have its last group mistaken for a port.
        yield 'bare IPv6 address' => ['2001:db8::1', '2001:db8::1', null];

        yield 'bare IPv6 address with base path' => ['2001:db8::1/platform', '2001:db8::1', 'platform'];

        // The brackets belong to the authority, not to the host.
        yield 'bracketed IPv6 address' => ['[2001:db8::1]', '2001:db8::1', null];

        yield 'bracketed IPv6 address with port and base path' => [
            '[2001:db8::1]:8443/platform',
            '2001:db8::1',
            'platform',
        ];

        yield 'IPv4 with base path' => ['10.0.0.1/centreon', '10.0.0.1', 'centreon'];

        yield 'trailing slash is removed' => ['central.example.com/platform/', 'central.example.com', 'platform'];
    }

    #[DataProvider('validAddressProvider')]
    public function testAcceptsValidAddress(string $rawValue, string $expectedValue): void
    {
        $address = new CentralAddress($rawValue);

        self::assertSame($expectedValue, $address->value);
    }

    #[DataProvider('authorityPartsProvider')]
    public function testExposesTheAuthorityParts(
        string $rawValue,
        string $expectedHost,
        ?string $expectedBasePath,
    ): void {
        $address = new CentralAddress($rawValue);

        self::assertSame($expectedHost, $address->host);
        self::assertSame($expectedBasePath, $address->basePath);
    }

    #[DataProvider('invalidAddressProvider')]
    public function testRejectsInvalidAddress(string $rawValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CentralAddress($rawValue);
    }
}
