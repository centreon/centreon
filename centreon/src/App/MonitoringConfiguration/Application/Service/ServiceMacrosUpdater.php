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
use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Service\InheritedServiceMacros;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ReservedServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Domain\Service\ServiceMacroChangesResolver;
use App\Shared\Domain\VaultInterface;

/**
 * Turns what a partial update says about the own macros of a service into the own macros it must hold, against the
 * service as the update leaves it: what the service inherits depends on the template and the check command it ends
 * up with.
 *
 * The rules the input checked earlier are checked again here, as the authority: a macro cannot be named like a
 * reserved one, nor carry a vault reference as its value. Nothing is written to the vault here.
 */
final readonly class ServiceMacrosUpdater
{
    public function __construct(
        private InheritedServiceMacrosResolver $inheritedMacrosResolver,
        private ServiceMacroChangesResolver $changesResolver,
        private StandardMacroRepository $standardMacroRepository,
        private VaultInterface $vault,
    ) {
    }

    /**
     * @param list<ServiceMacro> $current the own macros the service holds before the update
     *
     * @throws ReservedServiceMacroNameException
     * @throws ServiceMacroValueIsAVaultReferenceException
     * @throws ServiceMacroNotFoundException when a change refers to a macro the service neither owns nor inherits
     * @throws ServiceMacroPasswordUnmaskedException
     * @throws ServiceMacroValueRequiredException
     * @throws DuplicateServiceMacroNameException when the service would end up with two macros of the same name
     *
     * @return list<ServiceMacro> the own macros the service must hold, passwords in the form they were submitted
     */
    public function applyTo(Service $service, array $current, ServiceMacroChanges $changes): array
    {
        if ($changes->mode === MacroChangeModeEnum::Remove) {
            return $this->remove($current, $changes->removedIds);
        }

        $this->assertSubmittedMacrosAreAllowed($changes->changes);

        $inherited = $this->inheritedMacrosResolver->resolve($service->templateId, $service->checkOptions->checkCommandId);
        $resolved = $this->changesResolver->resolve($changes->changes, $current, $inherited);

        if ($changes->mode === MacroChangeModeEnum::Replace) {
            return $this->assertNamesAreUnique($resolved);
        }

        return $this->assertNamesAreUnique($this->mergeWithUntouched($current, $changes->changes, $resolved, $inherited));
    }

    /**
     * A service holds one macro per name: a rename or a new macro onto a name the service already uses is refused
     * rather than losing one of the two.
     *
     * @param list<ServiceMacro> $macros
     *
     * @throws DuplicateServiceMacroNameException
     *
     * @return list<ServiceMacro>
     */
    private function assertNamesAreUnique(array $macros): array
    {
        $names = array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $macros);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new DuplicateServiceMacroNameException(array_map(static fn (int|string $name): string => (string) $name, $duplicates));
        }

        return $macros;
    }

    /**
     * @param list<ServiceMacro> $current
     * @param list<ServiceMacroId> $ids
     *
     * @throws ServiceMacroNotFoundException when an id is not one of an own macro
     *
     * @return list<ServiceMacro>
     */
    private function remove(array $current, array $ids): array
    {
        $ownIds = array_map(static fn (ServiceMacro $macro): int => (int) $macro->id?->value, $current);
        $removedIds = array_map(static fn (ServiceMacroId $id): int => $id->value, $ids);
        $unknownIds = array_values(array_unique(array_diff($removedIds, $ownIds)));
        if ($unknownIds !== []) {
            throw new ServiceMacroNotFoundException($unknownIds);
        }

        return array_values(array_filter(
            $current,
            static fn (ServiceMacro $macro): bool => ! in_array($macro->id?->value, $removedIds, true),
        ));
    }

    /**
     * @param list<ServiceMacroChange> $changes
     *
     * @throws ReservedServiceMacroNameException
     * @throws ServiceMacroValueIsAVaultReferenceException
     */
    private function assertSubmittedMacrosAreAllowed(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $reservedNames = array_map(
            static fn (StandardMacro $macro): string => $macro->name->value,
            iterator_to_array($this->standardMacroRepository->findAll(), false),
        );
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
            throw new ReservedServiceMacroNameException($reserved);
        }

        if ($references !== []) {
            throw new ServiceMacroValueIsAVaultReferenceException($references);
        }
    }

    /**
     * The own macros a write did not point at are kept as they are; the ones it wrote follow. A write that points at
     * an inherited macro replaces the own macro under the name of that inherited macro, not under the name it is
     * given: an own macro already holding the new name is not replaced, which the check of the names refuses.
     *
     * @param list<ServiceMacro> $current
     * @param list<ServiceMacroChange> $changes
     * @param list<ServiceMacro> $resolved
     *
     * @return list<ServiceMacro>
     */
    private function mergeWithUntouched(array $current, array $changes, array $resolved, InheritedServiceMacros $inherited): array
    {
        $ownIds = array_map(static fn (ServiceMacro $macro): int => (int) $macro->id?->value, $current);
        $pointedAtIds = [];
        $overriddenNames = [];
        foreach ($changes as $change) {
            if (! $change->id instanceof ServiceMacroId) {
                continue;
            }

            if ($change->parent instanceof ServiceMacroParentEnum) {
                $source = $change->parent === ServiceMacroParentEnum::Command
                    ? $inherited->findByName($change->name)
                    : $inherited->findBySource($change->parent, $change->id);
                if ($source instanceof ServiceMacro) {
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
            static fn (ServiceMacro $macro): bool => ! in_array($macro->id?->value, $pointedAtIds, true)
                && ! in_array($macro->name->value, $overriddenNames, true),
        );

        return array_values([...$kept, ...$resolved]);
    }
}
