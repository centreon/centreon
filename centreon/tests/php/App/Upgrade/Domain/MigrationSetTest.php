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

namespace Tests\App\Upgrade\Domain;

use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationSetTest extends TestCase
{
    public function testModuleSetNamesRoundTrip(): void
    {
        $name = MigrationSet::forModule('centreon-open-tickets');

        self::assertSame('module:centreon-open-tickets', $name);
        self::assertSame('centreon-open-tickets', MigrationSet::moduleOf($name));
        self::assertNull(MigrationSet::moduleOf(MigrationSet::CONFIGURATION));
    }

    public function testModuleTables(): void
    {
        self::assertSame('doctrine_migration_versions_centreon_open_tickets', MigrationSet::moduleTable('centreon-open-tickets'));
        self::assertSame('doctrine_migration_versions_mymodule', MigrationSet::moduleTable('MyModule'));
        self::assertNull(MigrationSet::moduleTable('../../etc'));
        self::assertNull(MigrationSet::moduleTable(str_repeat('a', 37)), 'MySQL identifiers are limited to 64 characters');
        self::assertNotNull(MigrationSet::moduleTable(str_repeat('a', 36)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDefinitions(): iterable
    {
        yield 'namespace with a leading backslash' => ['\Centreon\Migrations', 'doctrine_migration_versions'];

        yield 'namespace with a dash' => ['Centreon-Migrations', 'doctrine_migration_versions'];

        yield 'table with a dash' => ['Centreon\Migrations', 'doctrine-migration-versions'];

        yield 'table with an injection' => ['Centreon\Migrations', 'versions; DROP TABLE host'];
    }

    #[DataProvider('provideInvalidDefinitions')]
    public function testRejectsInvalidDefinitions(string $namespace, string $table): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationSet('configuration', 'default', $namespace, '/migrations', $table);
    }
}
