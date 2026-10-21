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

namespace Tests\App\Upgrade\Infrastructure\Legacy;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use App\Upgrade\Infrastructure\Legacy\LegacyConnectionFactory;
use PHPUnit\Framework\TestCase;

final class LegacyConnectionFactoryTest extends TestCase
{
    public function testTheRealtimeConnectionTargetsTheStorageHost(): void
    {
        $factory = new LegacyConnectionFactory(
            new ConnectionConfig(
                host: 'configuration.invalid',
                user: $this->env('user'),
                password: $this->env('password'),
                databaseNameConfiguration: $this->env('db'),
                databaseNameRealTime: $this->env('dbcstg'),
                port: (int) $this->env('port'),
            ),
            $this->env('hostCentstorage'),
        );

        $connection = $factory->createRealtimeConnection();

        self::assertSame($this->env('hostCentstorage'), $connection->getConnectionConfig()->getHost());
        self::assertSame($this->env('dbcstg'), $connection->fetchOne('SELECT DATABASE()'));
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }
}
