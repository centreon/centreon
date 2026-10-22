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

use App\Upgrade\Infrastructure\Doctrine\DoctrineMigrationRunner;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\App\Upgrade\Infrastructure\Double\SingleConnectionRegistry;

final class MigrationFailureTest extends TestCase
{
    public function testTheErrorOfAMigrationIsKeptWhenTheDatabaseCannotTellWhichOneFailed(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $runner = new DoctrineMigrationRunner(new MigrationSetRegistry('/nonexistent', '/nonexistent'), new SingleConnectionRegistry($connection), new NullLogger());
        $factory = DependencyFactory::fromConnection(new ConfigurationArray([]), new ExistingConnection($connection));
        // The database went away along with the migration.
        $factory->setService(MetadataStorage::class, new class () implements MetadataStorage {
            public function ensureInitialized(): void
            {
            }

            public function getExecutedMigrations(): ExecutedMigrationsList
            {
                throw new \RuntimeException('Too many connections');
            }

            public function complete(ExecutionResult $result): void
            {
            }

            public function reset(): void
            {
            }
        });

        self::assertNull((new \ReflectionMethod($runner, 'failedVersion'))->invoke($runner, $factory, new MigrationPlanList([], Direction::UP)));
    }
}
