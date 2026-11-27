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

namespace Centreon\Infrastructure\RequestParameters;

/**
 * Makes a user provided regular expression usable by the SQL REGEXP operator.
 *
 * @package Centreon\Infrastructure\RequestParameters
 */
class RegularExpressionSanitizer
{
    /** A curly brace only opens a quantifier when it describes a {min} or a {min,max} interval. */
    private const INTERVAL_QUANTIFIER = '/^\{\d+(?:,\d*)?\}/';

    /**
     * Makes a user provided regular expression acceptable to the SQL REGEXP operator.
     *
     * Today that means one thing: escaping the curly braces that do not delimit a {min,max}
     * interval. PCRE tolerates such braces and reads them as literal characters, whereas the
     * engine MySQL uses rejects the whole pattern, so searching for a resource whose name
     * contains braces makes the query fail (error 3692 "Incorrect description of a {min,max}
     * interval"). Escaped braces are literal for both engines, so the sanitized pattern keeps
     * the exact same meaning.
     *
     * Braces are the only divergence measured between the two engines; further cases belong
     * here rather than at the call sites.
     *
     * @param string $regularExpression
     *
     * @return string
     */
    public static function sanitize(string $regularExpression): string
    {
        $sanitized = '';

        // Curly braces and backslashes are ASCII, so a multibyte character can never be mistaken
        // for one of them: iterating over the bytes is safe here.
        for ($index = 0, $length = strlen($regularExpression); $index < $length; ++$index) {
            $character = $regularExpression[$index];

            if ($character === '\\' && $index + 1 < $length) {
                // An escape sequence is kept as it is.
                $sanitized .= $character . $regularExpression[$index + 1];
                ++$index;
            } elseif (
                $character === '{'
                && preg_match(self::INTERVAL_QUANTIFIER, substr($regularExpression, $index), $matches) === 1
            ) {
                // A valid interval is kept as it is.
                $sanitized .= $matches[0];
                $index += strlen($matches[0]) - 1;
            } elseif ($character === '{' || $character === '}') {
                $sanitized .= '\\' . $character;
            } else {
                $sanitized .= $character;
            }
        }

        return $sanitized;
    }
}
