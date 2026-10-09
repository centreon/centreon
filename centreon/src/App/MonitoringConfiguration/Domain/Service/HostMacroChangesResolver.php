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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Exception\HostMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostMacroValueRequiredException;

/**
 * Turns the macros submitted on a host write into the direct macros the host must own, resolving
 * each one by id + parent rather than by name.
 *
 * - A new macro (no id) becomes a direct macro.
 * - A direct macro keeps its id: a rename and/or new value updates it in place; a null value keeps
 *   the stored password.
 * - An inherited macro that is changed becomes a new direct macro; a null value copies the
 *   inherited password's raw stored form, left for the caller to move under the host's own vault
 *   entry.
 * - Whatever ends up equivalent to the inherited macro of its name is dropped: the host relies
 *   on inheritance instead, so echoing back an untouched inherited macro never materialises a copy.
 *
 * A macro is referenced by id + parent, so the same reference sent twice makes no sense: only the
 * last one sent is applied. A new macro (no id) refers to nothing and is never dropped this way.
 *
 * A check-command macro is resolved by name, never by id: whatever id is sent with
 * `parent: command`, the change refers to the macro the host inherits under its name. A command
 * macro is never a password and always carries an empty value, so its id holds nothing a write
 * needs, and on_demand_macro_command ids are both incomplete (rows missing for some commands) and
 * unstable (rewritten on every command save); an unknown one is never an error. So a command-only
 * name submitted with an empty value stays inherited, while the same name defined by a template
 * in the inheritance line wins over the command, so an empty value then genuinely overrides it.
 */
final readonly class HostMacroChangesResolver
{
    /**
     * @param list<HostMacroChange> $changes the macros as submitted, in the order to store them
     * @param list<HostMacro> $current the host's stored direct macros (empty on creation)
     *
     * @throws HostMacroNotFoundException when a change refers to a macro the host neither owns nor inherits
     * @throws HostMacroValueRequiredException when a change keeps the stored value of a macro that is not a stored password
     *
     * @return list<HostMacro> the direct macros the host must own
     */
    public function resolve(array $changes, array $current, InheritedHostMacros $inherited): array
    {
        $unknownIds = [];
        $valueRequired = [];
        $resolved = [];
        foreach ($this->lastChangePerReference($changes) as $change) {
            $source = $this->findSource($change, $current, $inherited);

            if (! $this->isResolvedByName($change) && ! $source instanceof HostMacro) {
                $unknownIds[] = (int) $change->id?->value;

                continue;
            }

            if ($change->keepsStoredValue() && ! $source?->isPassword) {
                $valueRequired[] = $change->name->value;

                continue;
            }

            $resolved[] = $this->apply($change, $source);
        }

        if ($unknownIds !== []) {
            throw new HostMacroNotFoundException($unknownIds);
        }

        if ($valueRequired !== []) {
            throw new HostMacroValueRequiredException($valueRequired);
        }

        return $inherited->withoutRedundant($resolved);
    }

    /**
     * Keeps, for each id + parent reference, only the last change sent for it, in submission order.
     *
     * @param list<HostMacroChange> $changes
     *
     * @return list<HostMacroChange>
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
            fn (HostMacroChange $change, int $index): bool => $change->isNew()
                || $lastIndexes[$this->reference($change)] === $index,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function reference(HostMacroChange $change): string
    {
        return ($change->parent->value ?? 'direct') . ':' . $change->id?->value;
    }

    /**
     * A new macro has no source to find, and a command macro is found by name: neither can refer to
     * an unknown macro.
     */
    private function isResolvedByName(HostMacroChange $change): bool
    {
        if ($change->isNew()) {
            return true;
        }

        return $change->parent === HostMacroParentEnum::Command;
    }

    /**
     * @param list<HostMacro> $current
     */
    private function findSource(HostMacroChange $change, array $current, InheritedHostMacros $inherited): ?HostMacro
    {
        if (! $change->id instanceof HostMacroId) {
            return null;
        }

        if ($change->parent === HostMacroParentEnum::Command) {
            return $inherited->findByName($change->name);
        }

        if (! $change->parent instanceof HostMacroParentEnum) {
            foreach ($current as $macro) {
                if ($macro->isIdentifiedBy(null, $change->id)) {
                    return $macro;
                }
            }

            return null;
        }

        return $inherited->findBySource($change->parent, $change->id);
    }

    private function apply(HostMacroChange $change, ?HostMacro $source): HostMacro
    {
        // keepsStoredValue() is only reached with a stored password source (checked by the caller).
        $value = $change->value ?? (string) $source?->value;

        if (! $source instanceof HostMacro || $source->isInherited()) {
            return new HostMacro($change->name, $value, $change->isPassword);
        }

        return $source->rename($change->name)->withValue($value, $change->isPassword);
    }
}
