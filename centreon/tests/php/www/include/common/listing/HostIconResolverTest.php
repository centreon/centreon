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
require_once __DIR__ . '/../../../../../../www/include/common/listing/HostIconResolver.php';

/**
 * Icon source over a fixed map. $asks collects every batch it is called with,
 * so a test can assert what the walk asked for and not only what it returned.
 *
 * @param array<int, string> $icons
 * @param list<int[]> $asks
 *
 * @return callable(int[]): array<int, string>
 */
function iconsFrom(array $icons, array &$asks = []): callable
{
    return static function (array $nodes) use ($icons, &$asks): array {
        $asks[] = $nodes;

        return array_intersect_key($icons, array_flip($nodes));
    };
}

/**
 * @param array<int, int[]> $relations
 *
 * @return callable(int[]): array<int, int[]>
 */
function hostTemplatesFrom(array $relations): callable
{
    return static function (array $nodes) use ($relations): array {
        $templates = [];
        foreach ($nodes as $node) {
            if (isset($relations[$node])) {
                $templates[$node] = $relations[$node];
            }
        }

        return $templates;
    };
}

it('prefers the icon the object carries itself', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([10 => 'own.png', 42 => 'template.png']),
        hostTemplatesFrom([10 => [42]])
    );

    expect($resolved)->toBe([10 => 'own.png']);
});

it('inherits from a later template when the first carries no icon', function (): void {
    // The common case: a generic template first, an icon-bearing pack second.
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([42 => 'pack.png']),
        hostTemplatesFrom([10 => [41, 42]])
    );

    expect($resolved)->toBe([10 => 'pack.png']);
});

it('exhausts the first template chain before looking at the second', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([43 => 'deep.png', 42 => 'second.png']),
        hostTemplatesFrom([10 => [41, 42], 41 => [43]])
    );

    expect($resolved)->toBe([10 => 'deep.png']);
});

it('takes the nearest icon over an ancestor one', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([41 => 'near.png', 43 => 'far.png']),
        hostTemplatesFrom([10 => [41], 41 => [43]])
    );

    expect($resolved)->toBe([10 => 'near.png']);
});

it('leaves an object out when nothing in its chain carries an icon', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([]),
        hostTemplatesFrom([10 => [41], 41 => [42]])
    );

    expect($resolved)->toBe([]);
});

it('terminates on a cyclic template relation', function (): void {
    // Reachable through CLAPI or an import; an unguarded walk would never return.
    $resolved = HostIconResolver::walk(
        [10],
        iconsFrom([]),
        hostTemplatesFrom([10 => [41], 41 => [10, 41]])
    );

    expect($resolved)->toBe([]);
});

it('resolves a batch independently for each object', function (): void {
    $resolved = HostIconResolver::walk(
        [10, 20],
        iconsFrom([42 => 'a.png', 52 => 'b.png']),
        hostTemplatesFrom([10 => [41, 42], 20 => [51, 52]])
    );

    expect($resolved)->toBe([10 => 'a.png', 20 => 'b.png']);
});

it('returns nothing when asked for nothing', function (): void {
    expect(HostIconResolver::walk([], iconsFrom([1 => 'x.png']), hostTemplatesFrom([])))->toBe([]);
});

it('keeps one object of a batch from consuming another\'s inheritance', function (): void {
    // Both objects reach 42 through 41. The visited set is per object, so the
    // second must not be denied a node the first already walked through.
    $resolved = HostIconResolver::walk(
        [10, 20],
        iconsFrom([42 => 'shared.png']),
        hostTemplatesFrom([10 => [41], 20 => [41], 41 => [42]])
    );

    expect($resolved)->toBe([10 => 'shared.png', 20 => 'shared.png']);
});

it('gives up on a chain deeper than the node cap', function (): void {
    // A linear chain, each template carrying the next. The walk spends one step
    // per node, so the assertions sit on the exact boundary: the last reachable
    // node resolves, the one past it does not. A chain far beyond the cap would
    // fail whatever the loop condition, and would let an off-by-one through.
    $relations = [];
    for ($id = 10; $id < 80; $id++) {
        $relations[$id] = [$id + 1];
    }

    $lastReachable = 10 + 49;

    expect(HostIconResolver::walk([10], iconsFrom([$lastReachable => 'last.png']), hostTemplatesFrom($relations)))
        ->toBe([10 => 'last.png']);

    $truncated = null;
    expect(HostIconResolver::walk(
        [10],
        iconsFrom([$lastReachable + 1 => 'past.png']),
        hostTemplatesFrom($relations),
        $truncated
    ))->toBe([]);

    // Reported, not silently indistinguishable from "no icon anywhere in the
    // chain": resolve() turns this into a warning, because only a cycle or
    // abnormal data ever reaches the cap.
    expect($truncated)->toBe([10]);
});

it('asks the icon source about a shared template only once', function (): void {
    // The icons are read from the database one batch per step, so a template
    // sitting in every row chain must not be queried once per row.
    $asks = [];
    $resolved = HostIconResolver::walk(
        [10, 20, 30],
        iconsFrom([41 => 'shared.png'], $asks),
        hostTemplatesFrom([10 => [41], 20 => [41], 30 => [41]])
    );

    expect($resolved)->toBe([10 => 'shared.png', 20 => 'shared.png', 30 => 'shared.png']);
    // Two asks: the requested objects, then the single shared template.
    expect($asks)->toBe([[10, 20, 30], [41]]);
});

it('never asks the icon source for an empty batch', function (): void {
    // fetchDirectIcons() short-circuits on an empty list, but the walk must not
    // rely on that: an empty IN (...) is a SQL syntax error.
    $asks = [];
    HostIconResolver::walk([10], iconsFrom([10 => 'own.png'], $asks), hostTemplatesFrom([]));

    foreach ($asks as $batch) {
        expect($batch)->not->toBe([]);
    }
});
