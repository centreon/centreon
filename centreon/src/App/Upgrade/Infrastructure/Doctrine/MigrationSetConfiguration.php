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

namespace App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\MigrationSet;
use Webmozart\Assert\Assert;

final readonly class MigrationSetConfiguration
{
    public const string CORE_TABLE = 'doctrine_migration_versions';

    public function __construct(
        public string $connection,
        public string $namespace,
        public string $directory,
        public string $table,
    ) {
        Assert::stringNotEmpty($connection);
        Assert::regex($namespace, '/^[A-Za-z_]\w*(\\\\[A-Za-z_]\w*)*$/D', 'Invalid migration namespace %s.');
        Assert::stringNotEmpty($directory);
        Assert::regex($table, '/^[a-z_][a-z0-9_]*$/D', 'Invalid migration table name %s.');
    }

    /**
     * Static: also called by the legacy module code.
     *
     * @return string|null the metadata table of the module, null when the module name is not supported
     */
    public static function moduleTable(string $module): ?string
    {
        return MigrationSet::isModuleName($module) ? self::CORE_TABLE . '_' . str_replace('-', '_', $module) : null;
    }
}
