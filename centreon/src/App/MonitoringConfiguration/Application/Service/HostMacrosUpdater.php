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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Command\MacroChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\MacroChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\ReservedHostMacroNameException;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Shared\Domain\VaultInterface;

/**
 * Turns what a partial update says about the custom macros into the direct macros the host must own,
 * against the host as the update leaves it: what the host inherits depends on the templates and the
 * check command it ends up with.
 *
 * The rules the input checked earlier are checked again here, as the authority: a macro cannot be named
 * like a reserved one, nor carry a vault reference as its value. Nothing is written to the vault here.
 */
final readonly class HostMacrosUpdater
{
    public function __construct(
        private InheritedHostMacrosResolver $inheritedMacrosResolver,
        private HostMacroChangesResolver $changesResolver,
        private StandardMacroRepository $standardMacroRepository,
        private VaultInterface $vault,
    ) {
    }

    /**
     * @throws HostMacroNotFoundException when a change refers to a macro the host neither owns nor inherits
     * @throws DuplicateHostMacroNameException when the host would end up with two macros of the same name
     *
     * @return list<HostMacro> the direct macros the host must own, passwords in the form they were submitted
     */
    public function applyTo(Host $host, MacroChanges $changes): array
    {
        $current = array_values($host->checkOptions->macros);

        if ($changes->mode === MacroChangeModeEnum::Remove) {
            return $this->remove($current, $changes->removedIds);
        }

        $this->assertSubmittedMacrosAreAllowed($changes->changes);

        $inherited = $this->inheritedMacrosResolver->resolve($host->templateIds, $host->checkOptions->checkCommandId);

        $resolved = $this->changesResolver->resolve($changes->changes, $current, $inherited);

        if ($changes->mode === MacroChangeModeEnum::Replace) {
            return $this->assertNamesAreUnique($resolved);
        }

        return $this->assertNamesAreUnique($this->mergeWithUntouched($current, $changes->changes, $resolved, $inherited));
    }

    /**
     * A host holds one macro per name. Two of the same would not be refused further down: only the first
     * is kept, and the other is deleted with no error, so a rename onto a name the host already uses would
     * lose one of the two macros.
     *
     * @param list<HostMacro> $macros
     *
     * @throws DuplicateHostMacroNameException
     *
     * @return list<HostMacro>
     */
    private function assertNamesAreUnique(array $macros): array
    {
        $names = array_map(static fn (HostMacro $macro): string => $macro->name->value, $macros);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new DuplicateHostMacroNameException(array_map(static fn (int|string $name): string => (string) $name, $duplicates));
        }

        return $macros;
    }

    /**
     * @param list<HostMacro> $current
     * @param list<HostMacroId> $ids
     *
     * @return list<HostMacro>
     */
    private function remove(array $current, array $ids): array
    {
        $ownIds = array_map(static fn (HostMacro $macro): int => (int) $macro->id?->value, $current);
        $removedIds = array_map(static fn (HostMacroId $id): int => $id->value, $ids);
        $unknownIds = array_values(array_diff($removedIds, $ownIds));
        if ($unknownIds !== []) {
            throw new HostMacroNotFoundException($unknownIds);
        }

        return array_values(array_filter(
            $current,
            static fn (HostMacro $macro): bool => ! in_array($macro->id?->value, $removedIds, true),
        ));
    }

    /**
     * @param list<HostMacroChange> $changes
     */
    private function assertSubmittedMacrosAreAllowed(array $changes): void
    {
        $reservedNames = $this->reservedNames();
        $reserved = [];
        $references = [];
        foreach ($changes as $change) {
            if (in_array($change->name->toStorageName(), $reservedNames, true)) {
                $reserved[] = $change->name->value;
            }

            if ($change->value !== null && $this->vault->isVaultPath($change->value)) {
                $references[] = $change->name->value;
            }
        }

        if ($reserved !== []) {
            throw new ReservedHostMacroNameException($reserved);
        }

        if ($references !== []) {
            throw new HostMacroValueIsAVaultReferenceException($references);
        }
    }

    /**
     * @return list<string>
     */
    private function reservedNames(): array
    {
        $names = [];
        /** @var StandardMacro $reservedMacro */
        foreach ($this->standardMacroRepository->findAll() as $reservedMacro) {
            $names[] = $reservedMacro->name->value;
        }

        return $names;
    }

    /**
     * The macros a write did not point at are kept as they are; the ones it wrote follow. A write that
     * points at an inherited macro replaces the macro the host owns under the name of that inherited macro,
     * not under the name it is given: a macro the host already owns under the new name is not replaced,
     * which the check of the names refuses. The order carries no meaning, a host holds its macros as a set.
     *
     * @param list<HostMacro> $current
     * @param list<HostMacroChange> $changes
     * @param list<HostMacro> $resolved
     *
     * @return list<HostMacro>
     */
    private function mergeWithUntouched(array $current, array $changes, array $resolved, InheritedHostMacros $inherited): array
    {
        $ownIds = array_map(static fn (HostMacro $macro): int => (int) $macro->id?->value, $current);
        $pointedAtIds = [];
        $overriddenNames = [];
        foreach ($changes as $change) {
            if (! $change->id instanceof HostMacroId) {
                continue;
            }
            if ($change->parent instanceof HostMacroParentEnum) {
                $source = $inherited->findBySource($change->parent, $change->id);
                if ($source instanceof HostMacro) {
                    $overriddenNames[] = $source->name->value;
                }

                continue;
            }
            if (in_array($change->id->value, $ownIds, true)) {
                $pointedAtIds[] = $change->id->value;
            }
        }

        $kept = array_filter(
            $current,
            static fn (HostMacro $macro): bool => ! in_array($macro->id?->value, $pointedAtIds, true)
                && ! in_array($macro->name->value, $overriddenNames, true),
        );

        return array_values([...$kept, ...$resolved]);
    }
}
