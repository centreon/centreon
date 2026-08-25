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

// The listing helpers live outside the Composer classmap.
require_once __DIR__ . '/../../../../../../www/include/common/listing/AjaxListingHelper.php';

it('leaves a search term with no wildcard untouched', function (): void {
    expect(AjaxListingHelper::escapeLikeWildcards('web-01'))->toBe('web-01');
});

it('escapes the wildcards a user cannot mean literally', function (): void {
    // Bound parameters keep the query safe, but they do not stop % and _ from
    // being read as pattern syntax: searching foo_bar would match fooXbar.
    expect(AjaxListingHelper::escapeLikeWildcards('foo_bar'))->toBe('foo\\_bar');
    expect(AjaxListingHelper::escapeLikeWildcards('50%'))->toBe('50\\%');
});

it('escapes the backslash before the wildcards it adds', function (): void {
    // The load-bearing part of the implementation: the passes run in order over
    // the whole subject, so escaping the backslash last would escape the ones
    // the wildcard passes just added and yield a literal backslash followed by a
    // live wildcard. Reordering the arrays must fail this test.
    expect(AjaxListingHelper::escapeLikeWildcards('a\\b'))->toBe('a\\\\b');
    expect(AjaxListingHelper::escapeLikeWildcards('100\\%'))->toBe('100\\\\\\%');
});

it('builds one bound placeholder per id', function (): void {
    $clause = AjaxListingHelper::buildIntInClause([7, 8], 'acl_gid');

    expect($clause['clause'])->toBe(':acl_gid0, :acl_gid1');
    expect($clause['parameters'])->toHaveCount(2);
});

it('numbers the placeholders from zero whatever the keys are', function (): void {
    // Callers pass array_keys() output and filtered lists, so the incoming keys
    // are not always 0..n. The placeholder names must not inherit them, or two
    // fragments of the same query could collide on a name.
    $clause = AjaxListingHelper::buildIntInClause([3 => 41, 9 => 42], 'tpl_hid');

    expect($clause['clause'])->toBe(':tpl_hid0, :tpl_hid1');
});

it('yields an empty clause for an empty list, which callers must not bind', function (): void {
    // Pinning the documented contract rather than endorsing it: an empty list
    // gives `IN ()`, a syntax error, so every call site guards on the list being
    // non-empty. A future change making this throw would be an improvement, and
    // this test is where it would be noticed.
    $clause = AjaxListingHelper::buildIntInClause([], 'acl_gid');

    expect($clause['clause'])->toBe('');
    expect($clause['parameters'])->toBe([]);
});
