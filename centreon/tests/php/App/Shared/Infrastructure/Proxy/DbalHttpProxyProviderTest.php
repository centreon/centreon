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

use App\Shared\Infrastructure\Proxy\DbalHttpProxyProvider;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalHttpProxyProviderTest extends KernelTestCase
{
    private DbalHttpProxyProvider $provider;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var DbalHttpProxyProvider $provider */
        $provider = self::getContainer()->get(DbalHttpProxyProvider::class);
        $this->provider = $provider;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->connection->executeStatement("DELETE FROM options WHERE `key` LIKE 'proxy%'");
    }

    public function testNoProxyConfigured(): void
    {
        self::assertNull($this->provider->getProxyUrl());
    }

    public function testProxyBuiltFromTheOptions(): void
    {
        $this->connection->insert('options', ['key' => 'proxy_url', 'value' => 'proxy.internal']);
        $this->connection->insert('options', ['key' => 'proxy_port', 'value' => '3128']);
        $this->connection->insert('options', ['key' => 'proxy_user', 'value' => 'user']);
        $this->connection->insert('options', ['key' => 'proxy_password', 'value' => 'pass']);

        self::assertSame('http://user:pass@proxy.internal:3128', $this->provider->getProxyUrl());
    }
}
