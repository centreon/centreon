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

namespace App\Upgrade\Domain;

use Webmozart\Assert\Assert;

final readonly class MigrationSet
{
    public const string REALTIME = 'realtime';
    public const string CONFIGURATION = 'configuration';

    /** The core sets, in execution order. */
    public const array CORE = [self::REALTIME, self::CONFIGURATION];
    public const string CORE_TABLE = 'doctrine_migration_versions';
    private const string MODULE_PREFIX = 'module:';

    // Also keeps module names from escaping the modules directory.
    private const string MODULE_NAME_PATTERN = '/^[A-Za-z0-9]+([-_][A-Za-z0-9]+)*$/D';

    public function __construct(
        public string $name,
        public string $connection,
        public string $namespace,
        public string $directory,
        public string $table,
    ) {
        Assert::stringNotEmpty($name);
        Assert::stringNotEmpty($connection);
        Assert::regex($namespace, '/^[A-Za-z_]\w*(\\\\[A-Za-z_]\w*)*$/D', 'Invalid migration namespace "%s".');
        Assert::stringNotEmpty($directory);
        Assert::regex($table, '/^[a-z_][a-z0-9_]*$/D', 'Invalid migration table name "%s".');
    }

    public static function forModule(string $module): string
    {
        return self::MODULE_PREFIX . $module;
    }

    /**
     * Static: also called by the legacy module Remover.
     *
     * @return string|null the metadata table of the module, null when the module name is not supported
     */
    public static function moduleTable(string $module): ?string
    {
        $table = self::CORE_TABLE . '_' . mb_strtolower(str_replace('-', '_', $module));

        // MySQL identifiers are limited to 64 characters.
        return preg_match(self::MODULE_NAME_PATTERN, $module) === 1 && \mb_strlen($table) <= 64 ? $table : null;
    }

    /**
     * @return string|null the module name when the set belongs to a module
     */
    public static function moduleOf(string $name): ?string
    {
        return str_starts_with($name, self::MODULE_PREFIX) ? mb_substr($name, \mb_strlen(self::MODULE_PREFIX)) : null;
    }
}
