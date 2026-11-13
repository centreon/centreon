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
 * Analyses migration SQL for the DdlMigration and DmlMigration checks.
 */
final class SqlStatement
{
    private const string TRANSACTION_CONTROL_PATTERN = '/^\s*(?:BEGIN|START|COMMIT|ROLLBACK|SAVEPOINT|RELEASE|XA|LOCK|UNLOCK)\b/i';

    /**
     * PDO MySQL runs every statement of a string.
     *
     * @throws \LogicException when the statement cannot be analysed
     */
    public static function isSingle(string $sql): bool
    {
        return self::matches('/;\s*\S/', self::code($sql)) === false;
    }

    /**
     * The SQL as the server runs it: literals emptied, quoted identifiers masked, comments removed except executable bodies.
     *
     * @throws \LogicException when the statement cannot be analysed
     */
    public static function code(string $sql): string
    {
        // One possessive pass, earliest match first: a quote inside a literal or comment opens nothing, and long literals cannot exhaust the PCRE stack.
        $code = preg_replace_callback('/' . implode('|', [
            "'(?:[^'\\\\]++|\\\\.)*+'",
            '"(?:[^"\\\\]++|\\\\.)*+"',
            '`[^`]*+`',
            '(?:--(?=\s|$)|#)[^\n]*+',
            // MySQL and MariaDB run the body of /*! */, MariaDB also that of /*M! */: it stays code.
            '\/\*(?!!|[Mm]!)(?:[^*]++|\*(?!\/))*+\*\/',
        ]) . '/s', static fn (array $match): string => match ($match[0][0]) {
            "'", '"' => "''",
            '`' => '`x`',
            default => ' ',
        }, $sql);

        // Only executable comments are left: drop their markers and keep their body.
        return self::checked(preg_replace('/\/\*(?:!|[Mm]!)\d*|\*\//', ' ', self::checked($code)));
    }

    /**
     * A USE, creating a temporary table, or a SET of anything but user variables (@name): it outlives the statement on its connection.
     *
     * @param string $code the statement as code() returns it
     *
     * @throws \LogicException when the statement cannot be analysed
     */
    public static function changesSession(string $code): bool
    {
        if (self::matches('/^\s*(?:USE\b|CREATE\s+(?:OR\s+REPLACE\s+)?TEMPORARY\b)/i', $code)) {
            return true;
        }

        // SET STATEMENT ... FOR scopes its settings to its statement.
        $result = preg_match('/^\s*SET\b(?!\s+STATEMENT\b)(.*)$/is', $code, $match);
        if ($result === false) {
            throw self::analysisFailure();
        }
        if ($result === 0) {
            return false;
        }

        $tokens = preg_split('/([(),])/', $match[1], -1, \PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            throw self::analysisFailure();
        }

        // Each assignment starts the statement or follows a comma outside parentheses.
        $assignments = [''];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token === ',' && $depth === 0) {
                $assignments[] = '';

                continue;
            }
            $depth += match ($token) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            $assignments[array_key_last($assignments)] .= $token;
        }

        foreach ($assignments as $assignment) {
            if (! self::matches('/^\s*@(?!@)/', $assignment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A transaction or table lock statement.
     *
     * @param string $code the statement as code() returns it
     *
     * @throws \LogicException when the statement cannot be analysed
     */
    public static function controlsTransaction(string $code): bool
    {
        return self::matches(self::TRANSACTION_CONTROL_PATTERN, $code);
    }

    /**
     * Never reads a PCRE failure as no match: a check would let the statement through.
     *
     * @throws \LogicException
     */
    public static function matches(string $pattern, string $subject): bool
    {
        $result = preg_match($pattern, $subject);
        if ($result === false) {
            throw self::analysisFailure();
        }

        return $result === 1;
    }

    /**
     * @throws \LogicException
     */
    private static function checked(?string $result): string
    {
        return $result ?? throw self::analysisFailure();
    }

    private static function analysisFailure(): \LogicException
    {
        return new \LogicException(sprintf('Cannot analyse the SQL statement (%s): bind its large values as parameters.', preg_last_error_msg()));
    }
}
