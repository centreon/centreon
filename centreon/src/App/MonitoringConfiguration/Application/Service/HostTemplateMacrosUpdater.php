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

use App\MonitoringConfiguration\Application\Command\HostTemplateMacroChanges;
use App\MonitoringConfiguration\Application\Command\MacroChangeModeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Service\HostTemplateMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Shared\Domain\Collection;
use App\Shared\Domain\VaultInterface;

/**
 * Turns what a partial update says about the macros into the macros the template must own, against the
 * template as the update leaves it: what it inherits depends on the parents and the check command it
 * ends up with. Its own linked service templates do not feed it, as on the detail.
 *
 * A macro named like a standard one is accepted. A vault reference as a value is refused: no password
 * is ever echoed back, so a client has no legitimate one to send. Nothing is written to the vault here.
 */
final readonly class HostTemplateMacrosUpdater
{
    public function __construct(
        private InheritedHostMacrosResolver $inheritedMacrosResolver,
        private HostTemplateMacroChangesResolver $changesResolver,
        private VaultInterface $vault,
    ) {
    }

    /**
     * @throws HostTemplateMacroNotFoundException when a change refers to a macro the template neither owns nor inherits
     * @throws HostTemplateMacroValueIsAVaultReferenceException
     *
     * @return Collection<HostTemplateMacro> the macros the template must own, passwords as submitted
     */
    public function resolve(HostTemplate $template, HostTemplateMacroChanges $changes): Collection
    {
        $current = array_values($template->checkOptions->macros->toArray());

        if ($changes->mode === MacroChangeModeEnum::Remove) {
            return new Collection($this->remove($current, $changes->removedIds), HostTemplateMacro::class);
        }

        $this->assertNoVaultReference($changes->changes);

        $inherited = $this->inheritedMacrosResolver->resolve($template->parentTemplateIds, $template->checkOptions->checkCommandId);
        $resolved = $this->changesResolver->resolve($changes->changes, $current, $inherited);

        if ($changes->mode === MacroChangeModeEnum::Replace) {
            return new Collection($resolved, HostTemplateMacro::class);
        }

        return new Collection($this->mergeWithUntouched($current, $changes->changes, $resolved, $inherited), HostTemplateMacro::class);
    }

    /**
     * @param list<HostTemplateMacro> $current
     * @param list<HostTemplateMacroId> $ids
     *
     * @return list<HostTemplateMacro>
     */
    private function remove(array $current, array $ids): array
    {
        $ownIds = array_map(static fn (HostTemplateMacro $macro): int => (int) $macro->id?->value, $current);
        $removedIds = array_map(static fn (HostTemplateMacroId $id): int => $id->value, $ids);
        $unknownIds = array_values(array_unique(array_diff($removedIds, $ownIds)));
        if ($unknownIds !== []) {
            throw new HostTemplateMacroNotFoundException($unknownIds);
        }

        return array_values(array_filter(
            $current,
            static fn (HostTemplateMacro $macro): bool => ! in_array($macro->id?->value, $removedIds, true),
        ));
    }

    /**
     * @param list<HostTemplateMacroChange> $changes
     */
    private function assertNoVaultReference(array $changes): void
    {
        $references = [];
        foreach ($changes as $change) {
            if ($change->value !== null && $this->vault->isVaultPath($change->value)) {
                $references[] = $change->name->value;
            }
        }

        if ($references !== []) {
            throw new HostTemplateMacroValueIsAVaultReferenceException($references);
        }
    }

    /**
     * The macros a write did not point at are kept; the ones it wrote follow. A write pointing at an
     * inherited macro replaces the macro the template owns under that inherited macro's name.
     *
     * @param list<HostTemplateMacro> $current
     * @param list<HostTemplateMacroChange> $changes
     * @param list<HostTemplateMacro> $resolved
     *
     * @return list<HostTemplateMacro>
     */
    private function mergeWithUntouched(array $current, array $changes, array $resolved, InheritedHostMacros $inherited): array
    {
        $ownIds = array_map(static fn (HostTemplateMacro $macro): int => (int) $macro->id?->value, $current);
        $pointedAtIds = [];
        $overriddenNames = [];
        foreach ($changes as $change) {
            if ($change->id === null) {
                continue;
            }
            if ($change->parent instanceof HostMacroParentEnum) {
                $source = $change->parent === HostMacroParentEnum::Command
                    ? $inherited->findByName(new HostMacroName($change->name->value))
                    : $inherited->findBySource($change->parent, new HostMacroId($change->id));
                if ($source instanceof HostMacro) {
                    $overriddenNames[] = $source->name->value;
                }

                continue;
            }
            if (in_array($change->id, $ownIds, true)) {
                $pointedAtIds[] = $change->id;
            }
        }

        $kept = array_filter(
            $current,
            static fn (HostTemplateMacro $macro): bool => ! in_array($macro->id?->value, $pointedAtIds, true)
                && ! in_array($macro->name->value, $overriddenNames, true),
        );

        return array_values([...$kept, ...$resolved]);
    }
}
