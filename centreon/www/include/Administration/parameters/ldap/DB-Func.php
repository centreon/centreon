<?php

/*
* Copyright 2005-2019 Centreon
* Centreon is developed by : Julien Mathis and Romain Le Merlus under
* GPL Licence 2.0.
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License as published by the Free Software
* Foundation ; either version 2 of the License.
*
* This program is distributed in the hope that it will be useful, but WITHOUT ANY
* WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
* PARTICULAR PURPOSE. See the GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License along with
* this program; if not, see <http://www.gnu.org/licenses>.
*
* Linking this program statically or dynamically with other modules is making a
* combined work based on this program. Thus, the terms and conditions of the GNU
* General Public License cover the whole combination.
*
* As a special exception, the copyright holders of this program give Centreon
* permission to link this program with independent modules to produce an executable,
* regardless of the license terms of these independent modules, and to copy and
* distribute the resulting executable under terms of Centreon choice, provided that
* Centreon also meet, for each linked independent module, the terms  and conditions
* of the license of that module. An independent module is a module which is not
* derived from this program. If you modify this program, you may extend this
* exception to your version of the program, but you are not obliged to do so. If you
* do not wish to do so, delete this exception statement from your version.
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

    if (is_int($value) && $value >= 1) {
        return true;
    }

    return false;
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
    if (substr_count($filterValue, '(') !== substr_count($filterValue, ')') ||
        $filterValue[0] !== '(' ||
        $filterValue[strlen($filterValue) - 1] !== ')') {
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
    if (preg_match('/::/', $cleanFilter)) {
        return false;
    }

    return true;
}
