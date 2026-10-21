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

/**
 * PDO MySQL runs every statement of a string: the base class checks would only see the first one.
 */
final class SqlStatement
{
    public static function isSingle(string $sql): bool
    {
        // A single pass: the earliest literal or comment wins, so that a quote inside another one is not an opening quote.
        $code = preg_replace('/' . implode('|', [
            "'(?:[^'\\\\]|\\\\.)*'",
            '"(?:[^"\\\\]|\\\\.)*"',
            '`[^`]*`',
            '(?:--(?=\s|$)|#)[^\n]*',
            // MySQL runs the body of /*! */ and /*+ */: it stays code.
            '\/\*(?![!+]).*?\*\/',
        ]) . '/s', '', $sql);

        return preg_match('/;\s*\S/', (string) $code) !== 1;
    }
}
