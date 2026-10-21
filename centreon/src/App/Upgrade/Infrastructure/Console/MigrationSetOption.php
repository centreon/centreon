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

namespace App\Upgrade\Infrastructure\Console;

use App\Upgrade\Domain\MigrationSet;
use Symfony\Component\Console\Exception\InvalidOptionException;

final class MigrationSetOption
{
    public const string SET_DESCRIPTION = 'A core migration set: "realtime" or "configuration"';
    public const string MODULE_DESCRIPTION = 'The migration set of a module, e.g. "centreon-open-tickets"';

    /**
     * @throws InvalidOptionException when both are given
     * @throws \InvalidArgumentException when the set or the module name is invalid
     */
    public static function resolve(?string $set, ?string $module): ?MigrationSet
    {
        if ($set !== null && $module !== null) {
            throw new InvalidOptionException('Choose either --set or --module.');
        }

        return match (true) {
            $set !== null => MigrationSet::core($set),
            $module !== null => MigrationSet::module($module),
            default => null,
        };
    }

    /**
     * @throws InvalidOptionException when none or both are given
     * @throws \InvalidArgumentException when the set or the module name is invalid
     */
    public static function require(?string $set, ?string $module): MigrationSet
    {
        return self::resolve($set, $module) ?? throw new InvalidOptionException('Choose a migration set with --set or --module.');
    }
}
