<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
        foreach ($afterMacros as $macroName => $macro) {
            $directMacroMatch = $directMacros[$macroName] ?? null;
            $inheritedMacroMatch = $inheritedHostMacros[$macroName] ?? null;
            $commandMacroMatch = $inheritedCommandMacros[$macroName] ?? null;

            if ($directMacroMatch && $inheritedMacroMatch) {
                if ($this->isIdenticalToInheritedMacro($macro, $inheritedMacroMatch)) {
                    $this->removedMacros[$macroName] = $macro;
                } elseif (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                    $this->updatedMacros[$macroName] = $macro;
                } else {
                    $this->unchangedMacros[$macroName] = $macro;
                }

                continue;
            }

            if ($directMacroMatch && $commandMacroMatch) {
                if ($this->isIdenticalToCommandMacro($macro)) {
                    $this->removedMacros[$macroName] = $macro;
                } elseif (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                    $this->updatedMacros[$macroName] = $macro;
                } else {
                    $this->unchangedMacros[$macroName] = $macro;
                }

                continue;
            }

            if ($directMacroMatch) {
                if (! $this->isIdenticalToDirectMacro($macro, $directMacroMatch)) {
                    $this->updatedMacros[$macroName] = $macro;
                } else {
                    $this->unchangedMacros[$macroName] = $macro;
                }

                continue;
            }

            if ($inheritedMacroMatch) {
                if (! $this->isIdenticalToInheritedMacro($macro, $inheritedMacroMatch)) {
                    $this->addedMacros[$macroName] = $macro;
                } else {
                    $this->unchangedMacros[$macroName] = $macro;
                }

                continue;
            }

            if ($commandMacroMatch) {
                if (! $this->isIdenticalToCommandMacro($macro)) {
                    $this->addedMacros[$macroName] = $macro;
                } else {
                    $this->unchangedMacros[$macroName] = $macro;
                }

                continue;
            }

            // Macro doesn't match any previously known macros
            $this->addedMacros[$macroName] = $macro;
        }

        $extraRemovedMacros = array_diff_key(
            $directMacros,
            $this->addedMacros,
            $this->updatedMacros,
            $this->unchangedMacros
        );
        $this->removedMacros = array_merge($this->removedMacros, $extraRemovedMacros);
    }

    private function isIdenticalToInheritedMacro(Macro $macro, Macro $existingMacro): bool
    {
        return $macro->getValue() === $existingMacro->getValue()
            && $macro->isPassword() === $existingMacro->isPassword();
    }

    private function isIdenticalToCommandMacro(Macro $macro): bool
    {
        return $macro->getValue() === '' && $macro->isPassword() === false;
    }

    private function isIdenticalToDirectMacro(Macro $macro, Macro $existingMacro): bool
    {
        return $macro->getValue() === $existingMacro->getValue()
            && $macro->isPassword() === $existingMacro->isPassword()
            && $macro->getDescription() === $existingMacro->getDescription();
    }
}
