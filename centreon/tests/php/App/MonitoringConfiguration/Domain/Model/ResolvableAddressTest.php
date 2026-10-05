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

namespace Tests\App\MonitoringConfiguration\Domain\Model;

use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolvableAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validAddressProvider(): iterable
    {
        yield 'ipv4' => ['192.0.2.10'];

        yield 'single label' => ['localhost'];

        yield 'fully qualified hostname' => ['srv01.example.com'];

        yield 'mixed case' => ['SRV01.Example.COM'];

        yield 'punycode label' => ['xn--caf-dma.example.com'];

        // only the top-level label must carry a letter
        yield 'numeric inner labels' => ['1.2.3.4.nip.io'];

        // RFC 1123 lets a label start with a digit
        yield 'label starting with a digit' => ['3com.net'];

        yield 'inner hyphen' => ['my-host.example.com'];

        yield '63 character label' => [str_repeat('a', 63) . '.com'];

        yield '253 character name' => [str_repeat('a.', 125) . 'abc'];
    }

    #[DataProvider('validAddressProvider')]
    public function testItAcceptsAnIpv4OrAnRfcHostname(string $address): void
    {
        self::assertSame($address, new ResolvableAddress($address)->value);
    }

    public function testItTrimsTheAddress(): void
    {
        self::assertSame('localhost', new ResolvableAddress(' localhost ')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddressProvider(): iterable
    {
        // RFC 3696 §2: an all-numeric top-level label is no hostname
        yield 'three octets' => ['127.0.1'];

        yield 'single number' => ['1234'];

        yield 'out of range octet' => ['192.0.2.256'];

        yield 'ipv6' => ['2001:db8::1'];

        // RFC 1123 §2.1: letters, digits and hyphens only
        yield 'underscore' => ['srv_01'];

        yield 'leading underscore' => ['_ldap'];

        yield 'trailing dot' => ['srv.example.com.'];

        yield 'leading hyphen' => ['-foo'];

        yield 'trailing hyphen' => ['foo-'];

        yield 'empty label' => ['a..b'];

        yield 'url' => ['http://10.0.0.8'];

        yield 'non ascii label' => ['café.example.com'];

        yield 'empty' => [''];

        // RFC 1035 §2.3.4: 63 characters per label, 253 overall
        yield '64 character label' => [str_repeat('a', 64) . '.com'];

        yield '254 character name' => [str_repeat('a.', 126) . 'ab'];
    }

    #[DataProvider('invalidAddressProvider')]
    public function testItRejectsAnythingElse(string $address): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ResolvableAddress($address);
    }
}
