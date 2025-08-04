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

/**
 * @param $value integer
 * @return bool : the compliance state of the rule
 */
function minimalValue(int $value): bool
{
    $value = filter_var(
        $value,
        FILTER_VALIDATE_INT
    );

    return (bool) (is_int($value) && $value >= 1);
}

/**
 * BAsic check if the LDAP filter syntax is valid according to RFC4515
 *
 * @param string $filterValue
 * @return bool
 */
function checkLdapFilterSyntax(string $filterValue): bool
{
    if ($filterValue === '') {
        return false;
    }

    if (! preg_match('/=%s\)/', $filterValue)) {
        return false;
    }

    // check for parentheses
    if (substr_count($filterValue, '(') !== substr_count($filterValue, ')')
        || ! str_starts_with($filterValue, '(')
        || ! str_ends_with($filterValue, ')')) {
        return false;
    }

    // reject multiple top-level filters
    if (preg_match('/^\([^()]*\)(.+)$/', $filterValue)) {
        return false;
    }

    $cleanFilter = preg_replace('/\\\\./', 'X', $filterValue);

    // filter should have at least one valid comparison
    if (! preg_match('/([a-zA-Z0-9][a-zA-Z0-9\.\-_]*|[0-9]+(?:\.[0-9]+)*)(?:=\*?[^=()]*\*?|>=|<=|~=|:(?:[^:=]*)?:=)[^=()]*/i', $cleanFilter)) {
        return false;
    }

    // first char after opening parenthesis should be &, |, !, or a valid attribute name starter
    if (! preg_match('/^\((&|\||!|[a-zA-Z0-9])/', $cleanFilter)) {
        return false;
    }

    // logical operators should be followed by an opening parenthesis
    if (preg_match('/\((&|\||!)[^(]/', $cleanFilter)) {
        return false;
    }

    // ! operator should be followed by exactly one filter
    if (preg_match('/^\(!\(/', $cleanFilter)) {
        if (! preg_match('/^\(!\([^()]*(?:\([^()]*\)[^()]*)*\)\)$/', $cleanFilter)) {
            return false;
        }
    }

    // reject double colons
    return ! (preg_match('/::/', $cleanFilter));
}
