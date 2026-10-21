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

use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Infrastructure\Doctrine\DdlMigration;
use App\Upgrade\Infrastructure\Doctrine\DmlMigration;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Doctrine silently ignores a migration declared outside the namespace of its set.
 */
final class MigrationConventionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMigrationFiles(): iterable
    {
        $root = \dirname(__DIR__, 7);
        $core = new MigrationSetRegistry($root . '/centreon', '/nonexistent');

        foreach (glob($root . '/centreon/migrations/*/Version*.php') ?: [] as $file) {
            yield mb_substr($file, mb_strlen($root) + 1) => [$file, $core->configuration(MigrationSet::core(basename(\dirname($file))))->namespace];
        }

        // Also keeps the data set from being empty while no migration is shipped.
        $moduleMigrations = [
            ...glob($root . '/centreon-*/www/modules/*/migrations/Version*.php') ?: [],
            ...glob(__DIR__ . '/Fixtures/modules/*/migrations/Version*.php') ?: [],
        ];
        foreach ($moduleMigrations as $file) {
            $moduleDir = \dirname($file, 2);
            $modules = new MigrationSetRegistry('/nonexistent', \dirname($moduleDir));

            yield mb_substr($file, mb_strlen($root) + 1) => [$file, $modules->configuration(MigrationSet::module(basename($moduleDir)))->namespace];
        }
    }

    #[DataProvider('provideMigrationFiles')]
    public function testMigrationsLiveInTheSetNamespaceAndExtendADdlOrDmlBaseClass(string $file, string $namespace): void
    {
        preg_match('/^namespace ([^;]+);/m', (string) file_get_contents($file), $matches);
        self::assertSame($namespace, $matches[1] ?? null);

        $class = $namespace . '\\' . basename($file, '.php');

        require_once $file;

        self::assertTrue(
            is_subclass_of($class, DdlMigration::class) || is_subclass_of($class, DmlMigration::class),
            \sprintf('%s must extend %s or %s.', $class, DdlMigration::class, DmlMigration::class),
        );
    }
}
