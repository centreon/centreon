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

require_once './modules/centreon-open-tickets/centreon-open-tickets.conf.php';

const ALLOWED_ACTIONS = ['a', 'd', 'c', 'l', 'dp', 'e', 'ds'];

/**
 * Sanitize a string parameter using HtmlSanitizer (remove tags, sanitize)
 *
 * @param string|null $value
 *
 * @return string
 */
function sanitizeString(?string $value): string
{
    if ($value === null) {
        return '';
    }

    return HtmlSanitizer::createFromString($value)
        ->removeTags()
        ->sanitize()
        ->getString();
}

/**
 * Validate and normalize an integer parameter
 *
 * @param mixed $value
 * @param int|null $default Default value if the input is invalid or empty
 *
 * @return int|null Returns the sanitized integer or null if invalid
 */
function sanitizeInt(mixed $value, ?int $default = null): ?int
{
    if ($value === null || $value === '') {
        return $default;
    }

    $filtered = filter_var($value, FILTER_VALIDATE_INT);

    return $filtered !== false ? (int) $filtered : $default;
}

/**
 * Normalize selection into an **associative** array keyed by rule_id:
 *  input can be:
 *   - [1,2,3]           -> [1=>1, 2=>1, 3=>1]
 *   - ['1'=>'on', ...]  -> [1=>1, ...]
 *   - '3'               -> [3=>1]
 *
 * @param mixed $value
 *
 * @return array<int,int>
 */
function sanitizeSelectAssoc(mixed $value): array
{
    $ids = [];
    if (is_array($value)) {
        foreach ($value as $key => $val) {
            // If the key is numeric, treat it as a value list; else treat key as rule_id
            if (is_int($key) || ctype_digit((string) $key)) {
                $id = filter_var($val, FILTER_VALIDATE_INT);
            } else {
                $id = filter_var($key, FILTER_VALIDATE_INT);
            }
            if ($id !== false) {
                $ids[(int) $id] = 1;
            }
        }
    } elseif ($value !== null && $value !== '') {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id !== false) {
            $ids[(int) $id] = 1;
        }
    }

    return $ids;
}

/**
 * Sanitize duplicateNb map to: [ruleId => int>0]
 *
 * Accepts mixed shapes; drops invalid/<=0 entries
 *
 * @param mixed $value
 *
 * @return array<int,int>
 */
function sanitizeDuplicateMap(mixed $value): array
{
    $map = [];
    if (! is_array($value)) {
        // support single scalar like 3, applied to nothing—ignore
        return $map;
    }
    foreach ($value as $ruleId => $times) {
        $id = filter_var($ruleId, FILTER_VALIDATE_INT);
        $cnt = filter_var($times, FILTER_VALIDATE_INT);
        if ($id !== false && $cnt !== false && (int) $cnt > 0) {
            $map[(int) $id] = (int) $cnt;
        }
    }

    return $map;
}

/**
 * Resolve 'o' action with whitelist
 *
 * @param CentreonOpenTicketsRequest $request
 *
 * @return string
 */
function resolveAction(CentreonOpenTicketsRequest $request): string
{
    $raw = $request->getParam('o') ?: $request->getParam('o1') ?: $request->getParam('o2');
    $o = sanitizeString(is_string($raw) ? $raw : '');

    return in_array($o, ALLOWED_ACTIONS, true) ? $o : 'l';
}

$db = new CentreonDBManager();
$request = new CentreonOpenTicketsRequest();
$rule = new Centreon_OpenTickets_Rule($db);

// Resolve & sanitize inputs
$o = resolveAction($request);
$ruleId = sanitizeInt($request->getParam('rule_id'));
$select = sanitizeSelectAssoc($request->getParam('select'));     // <-- assoc [ruleId => 1]
$duplicateNb = sanitizeDuplicateMap($request->getParam('duplicateNb')); // <-- assoc [ruleId => times]
$p = sanitizeInt($request->getParam('p'), 1);
$num = sanitizeInt($request->getParam('num'));
$limit = sanitizeInt($request->getParam('limit'));
$search = sanitizeString((string) $request->getParam('searchRule'));

try {
    switch ($o) {
        case 'a':
            require_once 'form.php';
            break;
        case 'd':
            $rule->delete($select);
            require_once 'list.php';
            break;
        case 'c':
            require_once 'form.php';
            break;
        case 'l':
            require_once 'list.php';
            break;
        case 'dp':
            $rule->duplicate($select, $duplicateNb);
            require_once 'list.php';
            break;
        case 'e':
            $rule->enable($select);
            require_once 'list.php';
            break;
        case 'ds':
            $rule->disable($select);
            require_once 'list.php';
            break;
        default:
            require_once 'list.php';
            break;
    }
} catch (Exception $e) {
    echo $e->getMessage() . '<br/>';
}
