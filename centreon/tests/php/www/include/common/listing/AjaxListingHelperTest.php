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

// www/include is outside the Composer classmap, and the heavy require_once calls of
// this file live inside boot(), so including it here has no side effect.
require_once __DIR__ . '/../../../../../../www/include/common/listing/AjaxListingHelper.php';

/**
 * Call the private static wildcard escaper.
 */
function escapeLikeWildcards(string $search): string
{
    $method = new ReflectionMethod(AjaxListingHelper::class, 'escapeLikeWildcards');

    return $method->invoke(null, $search);
}

/**
 * Build the helper without touching the database: getDefaultLimit() caches into
 * $defaultLimit, so seeding it short-circuits the only query getParams() would run.
 */
function helperWithDefaultLimit(int $defaultLimit = 30): AjaxListingHelper
{
    $reflection = new ReflectionClass(AjaxListingHelper::class);
    /** @var AjaxListingHelper $helper */
    $helper = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('defaultLimit')->setValue($helper, $defaultLimit);

    return $helper;
}

// phpunit.xml sets backupGlobals="false", so the request state written below would
// otherwise carry into whatever test runs next.
afterEach(function (): void {
    $_GET = [];
});

/**
 * The term is bound as a query parameter and never echoed, so it is deliberately not
 * HTML-encoded: htmlspecialchars() turned a search for the characters command lines are
 * made of into a term matching no stored row. Only the LIKE wildcards are escaped.
 */
it('leaves the characters command lines are made of untouched', function (string $term): void {
    expect(escapeLikeWildcards($term))->toBe($term);
})->with(['2>&1', '--arg="x"', "O'Brien", 'A&B', '/usr/lib/centreon-connector/perl', '']);

it('escapes the LIKE wildcards so a literal filters on itself', function (
    string $term,
    string $expected,
): void {
    expect(escapeLikeWildcards($term))->toBe($expected);
})->with([
    ['100%', '100\\%'],
    ['a_b', 'a\\_b'],
    ['back\\slash', 'back\\\\slash'],
    // Backslashes are escaped first, so the ones added for % and _ are not re-escaped.
    ['a\\%b', 'a\\\\\\%b'],
    ['%_\\', '\\%\\_\\\\'],
]);

it('clamps a crafted page index to its ceiling', function (
    mixed $num,
    int $expected,
): void {
    $_GET = ['num' => $num];

    expect(helperWithDefaultLimit()->getParams()['num'])->toBe($expected);
})->with([
    'negative'      => [-5, 0],
    'not a number'  => ['abc', 0],
    'empty'         => ['', 0],
    'in range'      => [7, 7],
    // The ceiling keeps num * limit an int: filter_var accepts up to PHP_INT_MAX,
    // and past roughly 3e14 the product becomes a float the int-typed query
    // parameter rejects, answering a crafted request with a 500.
    'above ceiling' => [999999999, 100000],
]);

it('clamps the page size to the configured default or the hard ceiling', function (
    mixed $limit,
    int $expected,
): void {
    $_GET = ['limit' => $limit];

    expect(helperWithDefaultLimit()->getParams()['limit'])->toBe($expected);
})->with([
    'zero'          => [0, 30],
    'negative'      => [-1, 30],
    'not a number'  => ['abc', 30],
    'in range'      => [50, 50],
    'above ceiling' => [5000, 1000],
]);

it('falls back on the configured default when no page size is requested', function (): void {
    $_GET = [];

    $params = helperWithDefaultLimit(42)->getParams();

    expect($params['limit'])->toBe(42)
        ->and($params['num'])->toBe(0)
        ->and($params['search'])->toBe('');
});
