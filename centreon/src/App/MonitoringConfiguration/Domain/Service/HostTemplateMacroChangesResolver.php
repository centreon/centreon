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

use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroValueRequiredException;

/**
 * Turns the macros submitted on a template write into the macros the template must own, each one
 * resolved by id + parent rather than by name.
 *
 * - A new macro (no id) becomes an own macro.
 * - An own macro keeps its id: a rename and/or new value updates it in place; a null value keeps
 *   the stored password.
 * - An inherited macro that is changed becomes a new own macro; a null value copies the inherited
 *   password's raw stored form, left for the caller to move under the template's own vault entry.
 * - Whatever ends up equivalent to the inherited macro of its name is dropped: the template relies
 *   on inheritance instead.
 *
 * The same reference sent twice: only the last one is applied. A password macro stays a password.
 * A check-command macro is resolved by name, never by id: command macro ids are incomplete and
 * rewritten on every command save, and such a macro is never a password nor carries a value.
 */
final readonly class HostTemplateMacroChangesResolver
{
    /**
     * @param list<HostTemplateMacroChange> $changes the macros as submitted, in the order to store them
     * @param list<HostMacro> $current the template's stored own macros (empty on creation)
     *
     * @throws HostTemplateMacroNotFoundException when a change refers to a macro the template neither owns nor inherits
     * @throws HostTemplateMacroPasswordUnmaskedException when a change turns a password macro into a plain one
     * @throws HostTemplateMacroValueRequiredException when a change keeps the stored value of a macro that is not a stored password
     *
     * @return list<HostMacro> the macros the template must own
     */
    public function resolve(array $changes, array $current, InheritedHostMacros $inherited): array
    {
        $unknownIds = [];
        $unmasked = [];
        $valueRequired = [];
        $resolved = [];
        foreach ($this->lastChangePerReference($changes) as $change) {
            $own = $this->findOwnSource($change, $current);
            $inheritedSource = $this->findInheritedSource($change, $inherited);
            $sourceIsPassword = $own->isPassword ?? $inheritedSource->isPassword ?? false;

            if (! $this->isResolvedByName($change) && ! $own instanceof HostMacro && ! $inheritedSource instanceof HostMacro) {
                $unknownIds[] = (int) $change->id;

                continue;
            }

            if ($this->unmasksPassword($change, $own, $sourceIsPassword, $inherited)) {
                $unmasked[] = $change->name->value;

                continue;
            }

            if ($change->keepsStoredValue() && ! $sourceIsPassword) {
                $valueRequired[] = $change->name->value;

                continue;
            }

            $resolved[] = $this->apply($change, $own, $inheritedSource);
        }

        if ($unknownIds !== []) {
            throw new HostTemplateMacroNotFoundException($unknownIds);
        }

        if ($unmasked !== []) {
            throw new HostTemplateMacroPasswordUnmaskedException($unmasked);
        }

        if ($valueRequired !== []) {
            throw new HostTemplateMacroValueRequiredException($valueRequired);
        }

        return array_values(array_filter(
            $resolved,
            static function (HostMacro $macro) use ($inherited): bool {
                $shadowed = $inherited->findByName(new HostMacroName($macro->name->value));

                return ! $shadowed instanceof HostMacro
                    || $shadowed->value !== $macro->value
                    || $shadowed->isPassword !== $macro->isPassword;
            },
        ));
    }

    /**
     * @param list<HostTemplateMacroChange> $changes
     *
     * @return list<HostTemplateMacroChange>
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
            fn (HostTemplateMacroChange $change, int $index): bool => $change->isNew()
                || $lastIndexes[$this->reference($change)] === $index,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function reference(HostTemplateMacroChange $change): string
    {
        if ($change->parent === HostMacroParentEnum::Command) {
            return HostMacroParentEnum::Command->value . ':' . $change->name->value;
        }

        return ($change->parent->value ?? 'own') . ':' . $change->id;
    }

    private function isResolvedByName(HostTemplateMacroChange $change): bool
    {
        if ($change->isNew()) {
            return true;
        }

        return $change->parent === HostMacroParentEnum::Command;
    }

    /**
     * An own macro resent under its own name introduces no override, so a plain one already
     * shadowing an inherited password (legacy data) can still be echoed back.
     */
    private function unmasksPassword(
        HostTemplateMacroChange $change,
        ?HostMacro $own,
        bool $sourceIsPassword,
        InheritedHostMacros $inherited,
    ): bool {
        if ($change->isPassword) {
            return false;
        }

        if ($sourceIsPassword) {
            return true;
        }

        $keepsOwnName = $own instanceof HostMacro && $own->name->value === $change->name->value;

        return ! $keepsOwnName && (bool) $inherited->findByName(new HostMacroName($change->name->value))?->isPassword;
    }

    /**
     * @param list<HostMacro> $current
     */
    private function findOwnSource(HostTemplateMacroChange $change, array $current): ?HostMacro
    {
        if ($change->id === null || $change->parent instanceof HostMacroParentEnum) {
            return null;
        }

        return array_find(
            $current,
            static fn (HostMacro $macro): bool => $macro->id?->value === $change->id,
        );
    }

    private function findInheritedSource(HostTemplateMacroChange $change, InheritedHostMacros $inherited): ?HostMacro
    {
        if ($change->id === null || ! $change->parent instanceof HostMacroParentEnum) {
            return null;
        }

        if ($change->parent === HostMacroParentEnum::Command) {
            return $inherited->findByName(new HostMacroName($change->name->value));
        }

        return $inherited->findBySource($change->parent, new HostMacroId($change->id));
    }

    private function apply(HostTemplateMacroChange $change, ?HostMacro $own, ?HostMacro $inheritedSource): HostMacro
    {
        // keepsStoredValue() is only reached with a stored password source (checked by the caller).
        $value = $change->value ?? (string) ($own->value ?? $inheritedSource?->value);

        return new HostMacro(
            $change->name,
            $value,
            $change->isPassword,
            $own?->id instanceof HostMacroId ? $own->id : null,
        );
    }
}
