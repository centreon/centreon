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

namespace Tests\App\Shared\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RealtimeConnectionConfigurationTest extends KernelTestCase
{
    private const string STORAGE_HOST = 'storage.example.test';

    private mixed $previousHost;

    protected function setUp(): void
    {
        $this->previousHost = $_ENV['hostCentstorage'] ?? null;
        $_ENV['hostCentstorage'] = self::STORAGE_HOST;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousHost === null) {
            unset($_ENV['hostCentstorage']);
        } else {
            $_ENV['hostCentstorage'] = $this->previousHost;
        }
    }

    public function testRealtimeConnectionTargetsTheStorageHost(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        self::assertSame(self::STORAGE_HOST, $connection->getParams()['host'] ?? null);
    }
}
