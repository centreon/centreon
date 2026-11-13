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

namespace Tests\App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Doctrine\MigrationFactoryProvider;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MigrationFactoryProviderKernelTest extends KernelTestCase
{
    public function testDoesNotRunOnTheStaticConnectionOfTheTests(): void
    {
        $connections = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ConnectionRegistry::class, $connections);
        $shared = $connections->getConnection('default');
        self::assertInstanceOf(Connection::class, $shared);

        $set = MigrationSet::module('rollback');
        $provider = new MigrationFactoryProvider($connections, new NullLogger(), '26.12.0');
        $factory = $provider->create($set, (new MigrationSetRegistry('/nonexistent', __DIR__ . '/Fixtures/modules', new NullLogger()))->configuration($set), $set->migrationStep(), initialize: false);

        try {
            self::assertNotSame($shared->getNativeConnection(), $factory->getConnection()->getNativeConnection());
        } finally {
            $provider->release($factory);
        }
    }

    public function testEachWebSetRunsOnTheDatabaseOfItsData(): void
    {
        $connections = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ConnectionRegistry::class, $connections);
        $registry = new MigrationSetRegistry('/nonexistent', '/nonexistent', new NullLogger());

        $expected = [
            WebMigrationSetEnum::Realtime->value => ['dbcstg', 'hostCentstorage'],
            WebMigrationSetEnum::Configuration->value => ['db', 'hostCentreon'],
        ];
        foreach (MigrationSet::webSets() as $set) {
            $connection = $connections->getConnection($registry->configuration($set)->connection);
            self::assertInstanceOf(Connection::class, $connection);
            [$database, $host] = $expected[$set->name];
            self::assertSame($_ENV[$database], $connection->getParams()['dbname'] ?? null, $set->name);
            self::assertSame($_ENV[$host], $connection->getParams()['host'] ?? null, $set->name);
        }
    }
}
