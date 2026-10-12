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
use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
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

        if ($changes->mode === MacroChangeModeEnum::Replace) {
            return $this->changesResolver->resolve($changes->changes, $current, $inherited);
        }

        $pointedAt = array_map(fn (HostMacroChange $change): HostMacroChange => $this->pointAtOwnMacroOfSameName($change, $current), $changes->changes);
        $resolved = $this->changesResolver->resolve($pointedAt, $current, $inherited);

        return $this->mergeWithUntouched($current, $pointedAt, $resolved);
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
        $unknownIds = array_values(array_diff(
            array_map(static fn (HostMacroId $id): int => $id->value, $ids),
            $ownIds,
        ));
        if ($unknownIds !== []) {
            throw new HostMacroNotFoundException($unknownIds);
        }

        $removedIds = array_map(static fn (HostMacroId $id): int => $id->value, $ids);

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
     * A new macro named like one the host owns is a write to that macro, not a second macro.
     *
     * @param list<HostMacro> $current
     */
    private function pointAtOwnMacroOfSameName(HostMacroChange $change, array $current): HostMacroChange
    {
        if (! $change->isNew()) {
            return $change;
        }

        $own = array_find($current, static fn (HostMacro $macro): bool => $macro->name->value === $change->name->value);
        if (! $own instanceof HostMacro) {
            return $change;
        }

        return new HostMacroChange($change->name, $change->value, $change->isPassword, $own->id);
    }

    /**
     * The macros a write did not point at are kept as they are; the ones it wrote follow. The order
     * carries no meaning, a host holds its macros as a set.
     *
     * @param list<HostMacro> $current
     * @param list<HostMacroChange> $changes
     * @param list<HostMacro> $resolved
     *
     * @return list<HostMacro>
     */
    private function mergeWithUntouched(array $current, array $changes, array $resolved): array
    {
        $ownIds = array_map(static fn (HostMacro $macro): int => (int) $macro->id?->value, $current);
        $pointedAtIds = [];
        foreach ($changes as $change) {
            if (! $change->id instanceof HostMacroId) {
                continue;
            }
            if ($change->parent instanceof HostMacroParentEnum) {
                continue;
            }
            if (in_array($change->id->value, $ownIds, true)) {
                $pointedAtIds[] = $change->id->value;
            }
        }

        $kept = array_filter(
            $current,
            static fn (HostMacro $macro): bool => ! in_array($macro->id?->value, $pointedAtIds, true),
        );

        return array_values([...$kept, ...$resolved]);
    }
}
