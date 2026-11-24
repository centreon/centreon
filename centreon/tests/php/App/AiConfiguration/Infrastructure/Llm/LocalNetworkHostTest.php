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

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Infrastructure\Llm\LocalNetworkHost;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalNetworkHostTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hosts(): iterable
    {
        yield 'localhost' => ['http://localhost:11434', true];

        yield 'localhost subdomain' => ['http://foo.localhost', true];

        yield 'uppercase localhost' => ['http://LOCALHOST', true];

        yield 'loopback' => ['http://127.0.0.1:11434', true];

        yield 'IPv6 loopback' => ['http://[::1]', true];

        yield 'private IPv4' => ['http://192.168.1.10', true];

        yield 'IPv6 unique local' => ['http://[fd00::1]', true];

        yield 'link-local metadata service' => ['http://169.254.169.254', false];

        yield 'IPv6 link-local' => ['http://[fe80::1]', false];

        yield 'IPv4-mapped link-local' => ['http://[::ffff:169.254.169.254]', false];

        yield 'public host' => ['https://api.mistral.ai', false];

        yield 'no host' => ['file:///etc/hosts', false];
    }

    #[DataProvider('hosts')]
    public function testOnlyLoopbackAndPrivateHostsBypassTheProxy(string $url, bool $isLocal): void
    {
        self::assertSame($isLocal, LocalNetworkHost::isLocal($url));
    }
}
