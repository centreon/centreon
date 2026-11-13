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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\InheritedServiceMacros;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueRequiredException;

/**
 * Turns the macros submitted on a service write into the own macros the service must hold, resolving each one by
 * id + parent rather than by name.
 *
 * - Two submitted macros of the same name, compared upper-cased, are refused: a service holds one macro per name,
 *   and keeping one of them would silently drop the other. Overriding an inherited macro by name is not a
 *   duplicate.
 * - A new macro (no id) becomes an own macro.
 * - An own macro keeps its id: a rename and/or new value updates it in place; a null value keeps the stored
 *   password.
 * - An inherited macro that is changed becomes a new own macro; a null value copies the inherited password's raw
 *   stored form, left for the caller to move under the service's own vault entry.
 * - Whatever ends up equivalent to the inherited macro of its name is dropped: the service relies on inheritance
 *   instead, so echoing back an untouched inherited macro never materialises a copy.
 *
 * A macro is referenced by id + parent (by name for a command macro, see below), so the same reference sent twice
 * makes no sense: only the last one sent is applied, as on hosts, and names are compared after that. A new macro
 * (no id) refers to nothing and is never dropped this way.
 *
 * A password macro stays a password: a change cannot set is_password to false on a macro whose source is a
 * password, nor override by name an inherited password with a plain macro, whether it is new or renamed. Its
 * secret would otherwise be stored and echoed back in clear.
 *
 * A check-command macro is resolved by name, never by id: whatever id is sent with `parent: command`, the change
 * refers to the macro the service inherits under its name. A command macro is never a password and always carries
 * an empty value, so its id holds nothing a write needs, and on_demand_macro_command ids are both incomplete (rows
 * missing for some commands) and unstable (rewritten on every command save); an unknown one is never an error.
 */
final readonly class ServiceMacroChangesResolver
{
    /**
     * @param list<ServiceMacroChange> $changes the macros as submitted, in the order to store them
     * @param list<ServiceMacro> $current the service's stored own macros (empty on creation)
     * @param InheritedServiceMacros $inherited what the service inherits from the template and the check command
     *                                          it is written with
     *
     * @throws DuplicateServiceMacroNameException when two submitted macros have the same name
     * @throws ServiceMacroNotFoundException when a change refers to a macro the service neither owns nor inherits
     * @throws ServiceMacroPasswordUnmaskedException when a change turns a password macro into a plain one
     * @throws ServiceMacroValueRequiredException when a change keeps the stored value of a macro that is not a stored password
     *
     * @return list<ServiceMacro> the own macros the service must hold
     */
    public function resolve(array $changes, array $current, InheritedServiceMacros $inherited): array
    {
        $changes = $this->lastChangePerReference($changes);
        $this->assertNamesAreUnique($changes);

        $unknownIds = [];
        $unmasked = [];
        $valueRequired = [];
        $resolved = [];
        foreach ($changes as $change) {
            $source = $this->findSource($change, $current, $inherited);

            if (! $this->isResolvedByName($change) && ! $source instanceof ServiceMacro) {
                $unknownIds[] = (int) $change->id?->value;

                continue;
            }

            if ($this->unmasksPassword($change, $source, $inherited)) {
                $unmasked[] = $change->name->value;

                continue;
            }

            if ($change->keepsStoredValue() && ! $source?->isPassword) {
                $valueRequired[] = $change->name->value;

                continue;
            }

            $resolved[] = $this->apply($change, $source);
        }

        if ($unknownIds !== []) {
            throw new ServiceMacroNotFoundException($unknownIds);
        }

        if ($unmasked !== []) {
            throw new ServiceMacroPasswordUnmaskedException($unmasked);
        }

        if ($valueRequired !== []) {
            throw new ServiceMacroValueRequiredException($valueRequired);
        }

        return $inherited->withoutRedundant($resolved);
    }

    /**
     * @param list<ServiceMacroChange> $changes
     *
     * @throws DuplicateServiceMacroNameException
     */
    private function assertNamesAreUnique(array $changes): void
    {
        $names = array_map(static fn (ServiceMacroChange $change): string => $change->name->value, $changes);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new DuplicateServiceMacroNameException(array_map(static fn (int|string $name): string => (string) $name, $duplicates));
        }
    }

    /**
     * Keeps, for each id + parent reference, only the last change sent for it, in submission order.
     *
     * @param list<ServiceMacroChange> $changes
     *
     * @return list<ServiceMacroChange>
     */
    private function lastChangePerReference(array $changes): array
    {
        $lastIndexes = [];
        foreach ($changes as $index => $change) {
            if (! $change->isNew()) {
                $lastIndexes[$this->reference($change)] = $index;
            }
        }

        return array_values(array_filter(
            $changes,
            fn (ServiceMacroChange $change, int $index): bool => $change->isNew()
                || $lastIndexes[$this->reference($change)] === $index,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * A command macro is referenced by name, like it is resolved (see the class docblock).
     */
    private function reference(ServiceMacroChange $change): string
    {
        if ($change->parent === ServiceMacroParentEnum::Command) {
            return ServiceMacroParentEnum::Command->value . ':' . $change->name->value;
        }

        return ($change->parent->value ?? 'own') . ':' . $change->id?->value;
    }

    /**
     * A new macro has no source to find, and a command macro is found by name: neither can refer to an unknown
     * macro.
     */
    private function isResolvedByName(ServiceMacroChange $change): bool
    {
        if ($change->isNew()) {
            return true;
        }

        return $change->parent === ServiceMacroParentEnum::Command;
    }

    /**
     * Whether a plain macro would replace a password: the macro it changes, or the inherited one it overrides by
     * name. An own macro resent under its own name introduces no override, so a plain one already shadowing an
     * inherited password (legacy data) can still be echoed back.
     */
    private function unmasksPassword(ServiceMacroChange $change, ?ServiceMacro $source, InheritedServiceMacros $inherited): bool
    {
        if ($change->isPassword) {
            return false;
        }

        if ($source?->isPassword) {
            return true;
        }

        $keepsOwnName = $source instanceof ServiceMacro
            && ! $source->isInherited()
            && $source->name->value === $change->name->value;

        return ! $keepsOwnName && (bool) $inherited->findByName($change->name)?->isPassword;
    }

    /**
     * @param list<ServiceMacro> $current
     */
    private function findSource(ServiceMacroChange $change, array $current, InheritedServiceMacros $inherited): ?ServiceMacro
    {
        if (! $change->id instanceof ServiceMacroId) {
            return null;
        }

        if ($change->parent === ServiceMacroParentEnum::Command) {
            return $inherited->findByName($change->name);
        }

        if (! $change->parent instanceof ServiceMacroParentEnum) {
            $id = $change->id;

            return array_find($current, static fn (ServiceMacro $macro): bool => $macro->isIdentifiedBy(null, $id));
        }

        return $inherited->findBySource($change->parent, $change->id);
    }

    private function apply(ServiceMacroChange $change, ?ServiceMacro $source): ServiceMacro
    {
        // keepsStoredValue() is only reached with a stored password source (checked by the caller).
        $value = $change->value ?? (string) $source?->value;

        if (! $source instanceof ServiceMacro || $source->isInherited()) {
            return new ServiceMacro($change->name, $value, $change->isPassword);
        }

        return $source->rename($change->name)->withValue($value, $change->isPassword);
    }
}
