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

use App\MonitoringConfiguration\Domain\Model\HostAddressResolution;
use App\MonitoringConfiguration\Domain\Model\ResolvableAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostAddressResolutionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function notAnIpv4Provider(): iterable
    {
        // What legacy gethostbyname() returns on failure: the input itself.
        yield 'echoed hostname' => ['srv01.example.com'];

        yield 'ipv6' => ['2001:db8::1'];

        yield 'out of range octet' => ['192.0.2.256'];

        yield 'empty' => [''];
    }

    public function testItAcceptsAnIpv4OrNoResolution(): void
    {
        $address = new ResolvableAddress('srv01.example.com');

        self::assertSame('192.0.2.10', new HostAddressResolution($address, '192.0.2.10')->ipv4);
        self::assertNull(new HostAddressResolution($address, null)->ipv4);
    }

    #[DataProvider('notAnIpv4Provider')]
    public function testItRejectsAResolvedValueThatIsNotAnIpv4(string $ipv4): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostAddressResolution(new ResolvableAddress('srv01.example.com'), $ipv4);
    }
}
