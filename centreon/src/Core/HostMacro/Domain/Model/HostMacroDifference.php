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

namespace Core\HostMacro\Domain\Model;
use Core\CommandMacro\Domain\Model\CommandMacro;

final class HostMacroDifference
{
    /** @var array<string,HostMacro> */
    private array $removedMacros = [];

    /** @var array<string,HostMacro> */
    private array $addedMacros = [];

    /** @var array<string,HostMacro> */
    private array $commonMacros = [];

    /** @var array<string,HostMacro> */
    private array $updatedMacros = [];

    /**
     * Compare $afterMacros to $directMacros, $inheritedHostMacros and $inheritedCommandMacros
     * and determine with ones had been added, updated, deleted.
     *
     * Require and return arrays with macro names as keys.
     *
     * @param array<string,HostMacro> $directMacros
     * @param array<string,HostMacro> $inheritedHostMacros
     * @param array<string,CommandMacro> $inheritedCommandMacros
     * @param array<string,HostMacro> $afterMacros
     */
    public function __construct(array $directMacros, array $inheritedHostMacros, array $inheritedCommandMacros, array $afterMacros)
    {
        foreach ($afterMacros as $macro) {
            $directMacroMatch = $directMacros[$macro->getName()] ?? null;
            $inheritedMacroMatch = $inheritedHostMacros[$macro->getName()] ?? null;
            $commandMacroMatch = $inheritedCommandMacros[$macro->getName()] ?? null;

            if (! $directMacroMatch && ! $inheritedMacroMatch && ! $commandMacroMatch) {
                // macro does not exist
                $this->addedMacros[$macro->getName()] = $macro;

                continue;
            }

            if ($commandMacroMatch) {
                // macro inherited from command
                if ($directMacroMatch && $macro->getValue() === '' && ! $macro->isPassword()) {
                    // macro has revert to command macro settings => will be deleted

                    continue;
                }
                if (! $directMacroMatch && ($macro->getValue() !== '' || $macro->isPassword())) {
                    // macro differs from command macro settings
                    $this->addedMacros[$macro->getName()] = $macro;

                    continue;
                }
            }

            if (
                $directMacroMatch
                && (
                    $directMacroMatch->getValue() !== $macro->getValue()
                    || $directMacroMatch->isPassword() !== $macro->isPassword()
                    || $directMacroMatch->getDescription() !== $macro->getDescription()
                )
            ) {
                if (
                    $inheritedMacroMatch
                    && $inheritedMacroMatch->getValue() === $macro->getValue()
                    && $inheritedMacroMatch->isPassword() === $macro->isPassword()
                    && $inheritedMacroMatch->getDescription() === $macro->getDescription()
                ) {
                    // macro has revert to inherited settings => will be deleted

                    continue;
                }

                // macro has changed from custom settings
                $this->updatedMacros[$macro->getName()] = $macro;

                continue;
            }

            if (
                $inheritedMacroMatch
                && ! $directMacroMatch
                && (
                    $inheritedMacroMatch->getValue() !== $macro->getValue()
                    || $inheritedMacroMatch->isPassword() !== $macro->isPassword()
                )
            ) {
                // macro has changed from inherited settings
                $this->addedMacros[$macro->getName()] = $macro;

                continue;
            }

            // macro is unchanged
            $this->commonMacros[$macro->getName()] = $macro;
        }

        $this->removedMacros = array_diff_key(
            $directMacros,
            $this->addedMacros,
            $this->updatedMacros,
            $this->commonMacros
        );
    }

    /**
     * @return array<string,HostMacro>
     */
    public function getAdded(): array {
        return $this->addedMacros;
    }

    /**
     * @return array<string,HostMacro>
     */
    public function getUpdated(): array {
        return $this->updatedMacros;
    }

    /**
     * @return array<string,HostMacro>
     */
    public function getRemoved(): array {
        return $this->removedMacros;
    }

    /**
     * @return array<string,HostMacro>
     */
    public function getCommon(): array {
        return $this->commonMacros;
    }
}