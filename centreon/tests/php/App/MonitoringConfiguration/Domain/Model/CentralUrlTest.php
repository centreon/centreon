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

use App\MonitoringConfiguration\Domain\Model\CentralUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CentralUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validUrlProvider(): iterable
    {
        yield 'https hostname' => ['https://central.example.com', 'https://central.example.com'];

        yield 'http hostname' => ['http://central.example.com', 'http://central.example.com'];

        yield 'hostname with port' => ['https://central.example.com:8443', 'https://central.example.com:8443'];

        yield 'multi-segment base URI' => [
            'https://central.example.com/base/path',
            'https://central.example.com/base/path',
        ];

        yield 'IPv4 with port and base URI' => ['http://10.0.0.1:8443/centreon', 'http://10.0.0.1:8443/centreon'];

        yield 'bracketed IPv6' => ['https://[2001:db8::1]/platform', 'https://[2001:db8::1]/platform'];

        yield 'bracketed IPv6 with port' => ['https://[2001:db8::1]:8443', 'https://[2001:db8::1]:8443'];

        yield 'bracketed IPv4-mapped IPv6' => ['https://[::ffff:10.0.0.1]', 'https://[::ffff:10.0.0.1]'];

        yield 'highest port' => ['https://central.example.com:65535', 'https://central.example.com:65535'];

        yield 'trailing slash is removed' => [
            'https://central.example.com/platform/',
            'https://central.example.com/platform',
        ];

        yield 'surrounding whitespace is trimmed' => ['  https://central.example.com  ', 'https://central.example.com'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrlProvider(): iterable
    {
        yield 'empty' => [''];

        yield 'no scheme' => ['central.example.com'];

        yield 'scheme-relative' => ['//central.example.com'];

        yield 'unsupported scheme' => ['ftp://central.example.com'];

        yield 'file scheme' => ['file:///etc/passwd'];

        // What CentralAddress canonicalization exists to prevent: curl cannot parse this authority.
        yield 'unbracketed IPv6' => ['https://2001:db8::1'];

        // A well-formed address, rejected by the pattern rather than by the IPv6 branch: without
        // brackets the first colon is read as the port separator, and "db8" is not a port.
        yield 'unbracketed IPv6 in long form with a path' => ['https://2001:db8:0:0:0:0:0:1/index.html'];

        // Bracket contents are only checked for their alphabet by the pattern.
        yield 'bracketed non-address' => ['https://[::::]'];

        yield 'bracketed IPv6 with too many groups' => ['https://[2001:db8:0:0:0:0:0:0:1]'];

        // The brackets are reserved to IP-literals, so a plain IPv4 must not wear them.
        yield 'bracketed IPv4' => ['https://[10.0.0.1]'];

        // The pattern only counts the port digits.
        yield 'port zero' => ['https://central.example.com:0'];

        yield 'port above the maximum' => ['https://central.example.com:99999'];

        yield 'empty path segment' => ['https://central.example.com//platform'];

        yield 'query string' => ['https://central.example.com/path?foo=bar'];

        yield 'fragment' => ['https://central.example.com/path#anchor'];

        yield 'inner whitespace' => ['https://central.example.com/pa th'];

        yield 'single-dot path segment' => ['https://central.example.com/.'];

        yield 'dot segment inside the path' => ['https://central.example.com/platform/../admin'];

        // The value is interpolated unquoted into `curl … | bash`, so shell metacharacters are
        // rejected rather than escaped.
        yield 'command separator' => ['https://central.example.com;id'];

        yield 'pipe' => ['https://central.example.com|id'];

        yield 'background operator' => ['https://central.example.com&id'];

        yield 'command substitution' => ['https://central.example.com/$(id)'];

        yield 'backtick substitution' => ['https://central.example.com/`id`'];

        yield 'single quote' => ["https://central.example.com/'"];

        yield 'embedded newline' => ["https://central.example.com\nid"];

        // Normalization does not cover this one: rtrim() runs on "/" after trim(), so the newline
        // is back in the value by the time the pattern sees it.
        yield 'trailing newline restored by the slash trim' => ["https://central.example.com/centreon\n/"];
    }

    #[DataProvider('validUrlProvider')]
    public function testAcceptsValidUrl(string $rawValue, string $expectedValue): void
    {
        $url = new CentralUrl($rawValue);

        self::assertSame($expectedValue, $url->value);
    }

    #[DataProvider('invalidUrlProvider')]
    public function testRejectsInvalidUrl(string $rawValue): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CentralUrl($rawValue);
    }
}
