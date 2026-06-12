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

namespace Tests\App\Shared\Infrastructure\Proxy;

use App\Shared\Infrastructure\Proxy\ProxyUrl;
use PHPUnit\Framework\TestCase;

final class ProxyUrlTest extends TestCase
{
    public function testNoProxyConfigured(): void
    {
        self::assertNull(ProxyUrl::fromOptions([]));
        self::assertNull(ProxyUrl::fromOptions(['proxy_url' => null]));
        self::assertNull(ProxyUrl::fromOptions(['proxy_url' => '  ']));
    }

    public function testPlainProxy(): void
    {
        self::assertSame(
            'http://proxy.internal',
            ProxyUrl::fromOptions(['proxy_url' => 'proxy.internal'])
        );
    }

    public function testProxyWithPort(): void
    {
        self::assertSame(
            'http://proxy.internal:3128',
            ProxyUrl::fromOptions(['proxy_url' => 'proxy.internal', 'proxy_port' => '3128'])
        );
    }

    public function testProtocolOfTheUrlIsKept(): void
    {
        self::assertSame(
            'https://proxy.internal:3128',
            ProxyUrl::fromOptions(['proxy_url' => 'https://proxy.internal', 'proxy_port' => '3128'])
        );
    }

    public function testCredentialsAreUrlEncoded(): void
    {
        self::assertSame(
            'http://user%40corp:p%40ss%3Aword@proxy.internal:3128',
            ProxyUrl::fromOptions([
                'proxy_url' => 'proxy.internal',
                'proxy_port' => '3128',
                'proxy_user' => 'user@corp',
                'proxy_password' => 'p@ss:word',
            ])
        );
    }

    public function testUserWithoutPassword(): void
    {
        self::assertSame(
            'http://user@proxy.internal',
            ProxyUrl::fromOptions(['proxy_url' => 'proxy.internal', 'proxy_user' => 'user'])
        );
    }

    public function testVaultStoredPasswordSkipsCredentials(): void
    {
        self::assertSame(
            'http://proxy.internal',
            ProxyUrl::fromOptions([
                'proxy_url' => 'proxy.internal',
                'proxy_user' => 'user',
                'proxy_password' => 'secret::hashicorp_vault::configuration/proxy::password',
            ])
        );
    }
}
