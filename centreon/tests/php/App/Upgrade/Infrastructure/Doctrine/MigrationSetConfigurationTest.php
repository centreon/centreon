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

use App\Upgrade\Infrastructure\Doctrine\MigrationSetConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationSetConfigurationTest extends TestCase
{
    public function testKeepsAValidDefinition(): void
    {
        $configuration = new MigrationSetConfiguration('realtime', 'Centreon\\Migrations\\Realtime', '/migrations/realtime', 'doctrine_migration_versions');

        self::assertSame('realtime', $configuration->connection);
        self::assertSame('Centreon\\Migrations\\Realtime', $configuration->namespace);
        self::assertSame('/migrations/realtime', $configuration->directory);
        self::assertSame('doctrine_migration_versions', $configuration->table);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideInvalidDefinitions(): iterable
    {
        yield 'no connection' => ['', 'Centreon\Migrations', '/migrations', 'doctrine_migration_versions'];

        yield 'namespace with a leading backslash' => ['default', '\Centreon\Migrations', '/migrations', 'doctrine_migration_versions'];

        yield 'namespace with a dash' => ['default', 'Centreon-Migrations', '/migrations', 'doctrine_migration_versions'];

        yield 'no directory' => ['default', 'Centreon\Migrations', '', 'doctrine_migration_versions'];

        yield 'table with a dash' => ['default', 'Centreon\Migrations', '/migrations', 'doctrine-migration-versions'];

        yield 'table with an injection' => ['default', 'Centreon\Migrations', '/migrations', 'versions; DROP TABLE host'];
    }

    #[DataProvider('provideInvalidDefinitions')]
    public function testRejectsInvalidDefinitions(string $connection, string $namespace, string $directory, string $table): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationSetConfiguration($connection, $namespace, $directory, $table);
    }
}
