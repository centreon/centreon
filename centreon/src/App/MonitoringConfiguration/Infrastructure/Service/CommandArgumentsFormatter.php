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

namespace App\MonitoringConfiguration\Infrastructure\Service;

/**
 * Renders a command's arguments into the single string legacy stores in the `command_command_id_argN`
 * columns (`host.command_command_id_arg1` for the check command, `command_command_id_arg2` for the
 * event handler): bang-joined (`!arg1!arg2`) with newlines, tabs and carriage returns escaped as
 * `#BR#`/`#T#`/`#R#` so a multi-line argument round-trips through one column (CentreonHost::insert()).
 * Shared by the persistence layer and the input validators so both agree on exactly what will be
 * stored — the length that must fit the `TEXT` column is the length of this output, not of the raw
 * arguments.
 */
final class CommandArgumentsFormatter
{
    /** Maximum bytes a MySQL/MariaDB TEXT column can hold. */
    public const MAX_STORAGE_LENGTH = 65535;

    /**
     * @param list<string> $args
     */
    public static function format(array $args): ?string
    {
        if ($args === []) {
            return null;
        }

        return str_replace(
            ["\n", "\t", "\r"],
            ['#BR#', '#T#', '#R#'],
            '!' . implode('!', $args),
        );
    }

    /**
     * Reverses {@see self::format()}: splits the bang-joined, escaped storage string back into
     * the raw arguments.
     *
     * @return list<string>
     */
    public static function parse(?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [];
        }

        // The stored form always starts with '!' (format() prefixes it), so the first exploded
        // segment is an empty string to drop.
        $parts = explode('!', $stored);
        array_shift($parts);

        return array_map(
            static fn (string $part): string => str_replace(['#BR#', '#T#', '#R#'], ["\n", "\t", "\r"], $part),
            $parts,
        );
    }
}
