<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Macro\Domain\Model;

use Core\CommandMacro\Domain\Model\CommandMacro;

/**
 * Compare $afterMacros to $directMacros, $inheritedHostMacros and $inheritedCommandMacros
 * to determine witch afterMacros were added, updated, deleted or are unchanged.
 *
 * Require and return arrays with macro names as keys.
 */
final class MacroDifference
{
    /** @var array<string,Macro> */
    public array $removedMacros = [];

    /** @var array<string,Macro> */
    public array $addedMacros = [];

    /** @var array<string,Macro> */
    public array $unchangedMacros = [];

    /** @var array<string,Macro> */
    public array $updatedMacros = [];

    /**
     * @param array<string,Macro> $directMacros
     * @param array<string,Macro> $inheritedHostMacros
     * @param array<string,CommandMacro> $inheritedCommandMacros
     * @param array<string,Macro> $afterMacros
     */
    public function compute(
        array $directMacros,
        array $inheritedHostMacros,
        array $inheritedCommandMacros,
        array $afterMacros,
    ): void {

        foreach ($afterMacros as $macro) {
            if ($macro->getId() !== null) {
                // Retrieve existing password value if not changed and id is provided
                foreach ($directMacros as $existingMacro) {
                    if ($macro->getId() !== $existingMacro->getId()) {

                        continue;
                    }

                    if ($macro->isPassword() === true && $macro->getValue() === '') {
                        $macro->setValue($existingMacro->getValue());
                    }

                    break;
                }

                foreach ($inheritedHostMacros as $existingMacro) {
                    if ($macro->getId() !== $existingMacro->getId()) {

                        continue;
                    }

                    if ($macro->isPassword() === true && $macro->getValue() === '') {
                        $macro->setValue($existingMacro->getValue());
                    }

                    break;
                }
            }

            // Keep computing by name for now as id matching is not reliable until all macros processes are migrated and id became mandatory in API
            $this->computeByName(
                $directMacros,
                $inheritedHostMacros,
                $inheritedCommandMacros,
                $macro
            );
        }

        $extraRemovedMacros = array_diff_key(
            $directMacros,
            $this->addedMacros,
            $this->updatedMacros,
            $this->unchangedMacros
        );
        $this->removedMacros = array_merge($this->removedMacros, $extraRemovedMacros);
    }

    /**
     * @param array<string,Macro> $directMacros
     * @param array<string,Macro> $inheritedHostMacros
     * @param array<string,CommandMacro> $inheritedCommandMacros
     * @param Macro $computedMacro
     */
    private function computeByName(
        array $directMacros,
        array $inheritedHostMacros,
        array $inheritedCommandMacros,
        Macro $computedMacro,
    ): void {
        $macroName = $computedMacro->getName();

        // Check macros based on name correspondance
        $directMacroMatch = $directMacros[$macroName] ?? null;
        $inheritedMacroMatch = $inheritedHostMacros[$macroName] ?? null;
        $commandMacroMatch = $inheritedCommandMacros[$macroName] ?? null;

        if ($directMacroMatch && $inheritedMacroMatch) {
            $macro = $this->addMacroId($computedMacro, $directMacroMatch->getId());
            if ($this->isIdenticalToInheritedMacro($macro, $inheritedMacroMatch)) {
                $this->removedMacros[$macroName] = $macro;
            } elseif (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                $this->updatedMacros[$macroName] = $macro;
            } else {
                $this->unchangedMacros[$macroName] = $macro;
            }

            return;
        }

        if ($directMacroMatch && $commandMacroMatch) {
            $macro = $this->addMacroId($computedMacro, $directMacroMatch->getId());
            if ($this->isIdenticalToCommandMacro($macro)) {
                $this->removedMacros[$macroName] = $macro;
            } elseif (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                $this->updatedMacros[$macroName] = $macro;
            } else {
                $this->unchangedMacros[$macroName] = $macro;
            }

            return;
        }

        if ($directMacroMatch) {
            $macro = $this->addMacroId($computedMacro, $directMacroMatch->getId());
            if (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                $this->updatedMacros[$macroName] = $macro;
            } else {
                $this->unchangedMacros[$macroName] = $macro;
            }

            return;
        }

        if ($inheritedMacroMatch) {
            if (! $this->isIdenticalToInheritedMacro($computedMacro, $inheritedMacroMatch)) {
                $this->addedMacros[$macroName] = $computedMacro;
            } else {
                $this->unchangedMacros[$macroName] = $computedMacro;
            }

            return;
        }

        if ($commandMacroMatch) {
            if (! $this->isIdenticalToCommandMacro($computedMacro)) {
                $this->addedMacros[$macroName] = $computedMacro;
            } else {
                $this->unchangedMacros[$macroName] = $computedMacro;
            }

            return;
        }

        // Macro doesn't match any previously known macros
        $this->addedMacros[$macroName] = $computedMacro;
    }

    private function isIdenticalToInheritedMacro(Macro $macro, Macro $existingMacro): bool
    {
        return
            $macro->getName() === $existingMacro->getName()
            && $macro->getValue() === $existingMacro->getValue()
            && $macro->isPassword() === $existingMacro->isPassword();
    }

    private function isIdenticalToCommandMacro(Macro $macro): bool
    {
        return $macro->getValue() === '' && $macro->isPassword() === false;
    }

    private function isIdenticalToDirectMacro(Macro $macro, Macro $existingMacro): bool
    {
        return $macro->getName() === $existingMacro->getName()
            && $macro->getValue() === $existingMacro->getValue()
            && $macro->isPassword() === $existingMacro->isPassword()
            && $macro->getDescription() === $existingMacro->getDescription();
    }

    private function addMacroId(Macro $oldMacro, ?int $id): Macro
    {
        $macro = new Macro(
            id: $id,
            ownerId: $oldMacro->getOwnerId(),
            name: $oldMacro->getName(),
            value: $oldMacro->getValue(),
        );
        $macro->setIsPassword($oldMacro->isPassword());
        $macro->setDescription($oldMacro->getDescription());

        return $macro;
    }
}
