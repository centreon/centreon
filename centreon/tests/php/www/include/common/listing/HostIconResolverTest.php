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
        [10 => 'own.png', 42 => 'template.png'],
        hostTemplatesFrom([10 => [42]])
    );

    expect($resolved)->toBe([10 => 'own.png']);
});

it('inherits from a later template when the first carries no icon', function (): void {
    // The common case: a generic template first, an icon-bearing pack second.
    $resolved = HostIconResolver::walk(
        [10],
        [42 => 'pack.png'],
        hostTemplatesFrom([10 => [41, 42]])
    );

    expect($resolved)->toBe([10 => 'pack.png']);
});

it('exhausts the first template chain before looking at the second', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        [43 => 'deep.png', 42 => 'second.png'],
        hostTemplatesFrom([10 => [41, 42], 41 => [43]])
    );

    expect($resolved)->toBe([10 => 'deep.png']);
});

it('takes the nearest icon over an ancestor one', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        [41 => 'near.png', 43 => 'far.png'],
        hostTemplatesFrom([10 => [41], 41 => [43]])
    );

    expect($resolved)->toBe([10 => 'near.png']);
});

it('leaves an object out when nothing in its chain carries an icon', function (): void {
    $resolved = HostIconResolver::walk(
        [10],
        [],
        hostTemplatesFrom([10 => [41], 41 => [42]])
    );

    expect($resolved)->toBe([]);
});

it('terminates on a cyclic template relation', function (): void {
    // Reachable through CLAPI or an import; an unguarded walk would never return.
    $resolved = HostIconResolver::walk(
        [10],
        [],
        hostTemplatesFrom([10 => [41], 41 => [10, 41]])
    );

    expect($resolved)->toBe([]);
});

it('resolves a batch independently for each object', function (): void {
    $resolved = HostIconResolver::walk(
        [10, 20],
        [42 => 'a.png', 52 => 'b.png'],
        hostTemplatesFrom([10 => [41, 42], 20 => [51, 52]])
    );

    expect($resolved)->toBe([10 => 'a.png', 20 => 'b.png']);
});

it('returns nothing when asked for nothing', function (): void {
    expect(HostIconResolver::walk([], ['1' => 'x.png'], hostTemplatesFrom([])))->toBe([]);
});
