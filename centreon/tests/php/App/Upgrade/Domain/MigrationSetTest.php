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
    public function testTheCoreSetsRunRealtimeFirst(): void
    {
        self::assertSame(
            [MigrationSet::REALTIME, MigrationSet::CONFIGURATION],
            array_map(static fn (MigrationSet $set): string => $set->name, MigrationSet::coreSets()),
        );
        self::assertFalse(MigrationSet::core(MigrationSet::CONFIGURATION)->isModule());
    }

    public function testRejectsAnUnknownCoreSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MigrationSet::core('module:centreon-open-tickets');
    }

    public function testAModuleSetIsNamedAfterItsModule(): void
    {
        $set = MigrationSet::module('centreon-open-tickets');

        self::assertTrue($set->isModule());
        self::assertSame('centreon-open-tickets', $set->module);
        self::assertSame('module:centreon-open-tickets', $set->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsupportedModuleNames(): iterable
    {
        yield 'path traversal' => ['../../etc'];

        yield 'uppercase, which would share a metadata table with its lowercase twin' => ['Centreon-Map'];

        yield 'underscore, which would share a metadata table with its dashed twin' => ['centreon_map'];

        yield 'leading digit, which is not a valid namespace' => ['4centreon'];

        yield 'trailing dash' => ['centreon-'];

        yield 'too long for its metadata table name' => [str_repeat('a', 37)];
    }

    #[DataProvider('provideUnsupportedModuleNames')]
    public function testRejectsUnsupportedModuleNames(string $module): void
    {
        self::assertFalse(MigrationSet::isModuleName($module));

        $this->expectException(\InvalidArgumentException::class);

        MigrationSet::module($module);
    }

    public function testEachModuleHasItsOwnMetadataTable(): void
    {
        self::assertSame('doctrine_migration_versions_centreon_open_tickets', MigrationSet::module('centreon-open-tickets')->table());
        self::assertSame(64, \mb_strlen(MigrationSet::module(str_repeat('a', 36))->table()), 'MySQL identifiers are limited to 64 characters');
    }

    public function testTheCoreSetsShareOneMetadataTable(): void
    {
        self::assertSame(['doctrine_migration_versions', 'doctrine_migration_versions'], array_map(static fn (MigrationSet $set): string => $set->table(), MigrationSet::coreSets()));
    }

    public function testTheRefusalOfAModuleNameStatesTheWholeRule(): void
    {
        // Made of lowercase letters, digits and dashes, yet refused.
        $this->expectExceptionMessage('Unsupported module name "3d-maps": lowercase letters and digits, starting with a letter, in words separated by single dashes, at most 36 characters.');

        MigrationSet::module('3d-maps');
    }

    public function testAcceptsTheLongestModuleName(): void
    {
        self::assertTrue(MigrationSet::isModuleName(str_repeat('a', 36)));
        self::assertTrue(MigrationSet::isModuleName('centreon-map4'));
    }
}
