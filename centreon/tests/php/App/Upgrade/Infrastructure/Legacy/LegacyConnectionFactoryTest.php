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

use App\Upgrade\Infrastructure\Legacy\LegacyConnectionFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LegacyConnectionFactoryTest extends KernelTestCase
{
    private const string CONFIGURATION_HOST = 'configuration.example.invalid';

    private mixed $previousConfigurationHost;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->previousConfigurationHost = $_ENV['hostCentreon'] ?? null;
        $_ENV['hostCentreon'] = self::CONFIGURATION_HOST;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousConfigurationHost === null) {
            unset($_ENV['hostCentreon']);
        } else {
            $_ENV['hostCentreon'] = $this->previousConfigurationHost;
        }
    }

    public function testTheRealtimeConnectionTargetsTheStorageHost(): void
    {
        /** @var LegacyConnectionFactory $factory */
        $factory = self::getContainer()->get(LegacyConnectionFactory::class);

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
