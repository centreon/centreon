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

namespace Tests\App\Shared\Domain\Assert;

use App\Shared\Domain\Assert\Assert;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AssertTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validIpOrHostnameProvider(): array
    {
        return [
            'IPv4' => ['192.168.1.1'],
            'IPv6' => ['2001:db8::1'],
            'simple hostname' => ['host01'],
            'FQDN' => ['host.example.com'],
            'underscore hostname (NetBIOS)' => ['host_01'],
            'underscore in FQDN (AD)' => ['netbios_name.local'],
            'hyphenated label' => ['foo-bar.example.com'],
            'single label' => ['localhost'],
            'max-length label (63 chars)' => [str_repeat('a', 63)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIpOrHostnameProvider(): array
    {
        return [
            'empty string' => [''],
            'has space' => ['hello world'],
            'has colon' => ['not:valid'],
            'has at sign' => ['foo@bar'],
            'consecutive dots' => ['a..b'],
            'leading dot' => ['.invalid'],
            'leading hyphen' => ['-bad'],
            'trailing hyphen' => ['bad-'],
            'label too long (64 chars)' => [str_repeat('a', 64)],
            'hostname too long (254 chars)' => [str_repeat('a', 254)],
            // Both "$" of the pattern would match right before this one.
            'trailing newline' => ["host01\n"],
            'trailing newline on an FQDN' => ["host.example.com\n"],
        ];
    }

    #[DataProvider('validIpOrHostnameProvider')]
    public function testIpOrHostnameAcceptsValidValues(string $value): void
    {
        Assert::ipOrHostname($value);
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidIpOrHostnameProvider')]
    public function testIpOrHostnameRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::ipOrHostname($value);
    }
}
