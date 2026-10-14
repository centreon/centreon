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

use Core\Macro\Domain\Model\Macro;

require_once __DIR__ . '/../../../../../www/class/config-generate/host.class.php';

beforeEach(function (): void {
    // The constructor boots the kernel and the backend, which the macro helpers do not need.
    $this->host = (new ReflectionClass(Host::class))->newInstanceWithoutConstructor();

    $this->callPrivate = fn (string $method, mixed ...$arguments): mixed => (new ReflectionMethod(Host::class, $method))
        ->invoke($this->host, ...$arguments);

    /**
     * @param Macro[] $macros
     *
     * @return string[]
     */
    $this->names = static fn (array $macros): array => array_map(
        static fn (Macro $macro): string => $macro->getName(),
        $macros
    );

    // Macros in the order they are returned by the database.
    $this->macros = [
        new Macro(1, 3, 'A', 'a'),
        new Macro(2, 1, 'B', 'b'),
        new Macro(3, 2, 'C', 'c'),
        new Macro(4, 1, 'D', 'd'),
        new Macro(5, 3, 'E', 'e'),
    ];
});

it('collects the macros of the given owners in their original order', function (): void {
    $index = ($this->callPrivate)('indexMacrosByOwnerId', $this->macros);

    $macros = ($this->callPrivate)('collectMacrosOfOwners', $index, [1, 3]);

    expect(($this->names)($macros))->toBe(['A', 'B', 'D', 'E']);
});

it('ignores duplicate and unknown owners when collecting macros', function (): void {
    $index = ($this->callPrivate)('indexMacrosByOwnerId', $this->macros);

    $macros = ($this->callPrivate)('collectMacrosOfOwners', $index, [1, 3, 1, 99]);

    expect(($this->names)($macros))->toBe(['A', 'B', 'D', 'E']);
});

it('collects no macro from an empty index', function (): void {
    $index = ($this->callPrivate)('indexMacrosByOwnerId', []);

    expect(($this->callPrivate)('collectMacrosOfOwners', $index, [1, 2]))->toBe([]);
});

it('splits host macros between direct and template macros', function (): void {
    // Host 100 inherits from templates 20 then 10, host 999 is unrelated.
    $index = ($this->callPrivate)('indexMacrosByOwnerId', [
        new Macro(1, 10, 'FROM_TPL_10', 'a'),
        new Macro(2, 100, 'DIRECT', 'b'),
        new Macro(3, 999, 'UNRELATED', 'c'),
        new Macro(4, 20, 'FROM_TPL_20', 'd'),
    ]);

    [$directMacros, $templateMacros] = ($this->callPrivate)('resolveHostMacrosFromCache', 100, [20, 10], $index, true);

    expect(($this->names)($directMacros))->toBe(['DIRECT'])
        ->and(($this->names)($templateMacros))->toBe(['FROM_TPL_10', 'FROM_TPL_20'])
        ->and(array_map(
            static fn (Macro $macro): bool => $macro->shouldBeEncrypted(),
            [...$directMacros, ...$templateMacros]
        ))->each->toBeTrue();
});

it('splits service macros between direct and template macros for each service', function (): void {
    // Services 200 and 201 share template 1, service 300 is not linked to the host.
    $index = ($this->callPrivate)('indexMacrosByOwnerId', [
        new Macro(1, 1, 'FROM_TPL', 'a'),
        new Macro(2, 200, 'DIRECT_200', 'b'),
        new Macro(3, 300, 'UNRELATED', 'c'),
        new Macro(4, 201, 'DIRECT_201', 'd'),
    ]);

    [$serviceMacros, $templateMacros] = ($this->callPrivate)(
        'resolveServiceMacrosFromCache',
        [200, 201],
        [200 => [1], 201 => [1]],
        $index,
        false
    );

    expect(($this->names)($serviceMacros))->toBe(['DIRECT_200', 'DIRECT_201'])
        ->and(($this->names)($templateMacros))->toBe(['FROM_TPL', 'FROM_TPL'])
        ->and(array_map(
            static fn (Macro $macro): bool => $macro->shouldBeEncrypted(),
            [...$serviceMacros, ...$templateMacros]
        ))->each->toBeFalse();
});
