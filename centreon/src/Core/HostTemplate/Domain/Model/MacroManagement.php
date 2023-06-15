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

namespace Core\HostTemplate\Domain\Model;

use Assert\AssertionFailedException;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostMacro\Domain\Model\HostMacroDifference;

/**
 * This class provide methods to help manipulate macros linked to a host template.
 */
class MacroManagement
{
    /**
     * Return two arrays:
     *  - the first is an array of the direct macros
     *  - the second is an array of the inherited macros
     * Both use the macro's name as key.
     *
     * @param HostMacro[] $macros
     * @param int[] $inheritanceLine
     * @param int $childId
     *
     * @return array{
     *      array<string,HostMacro>,
     *      array<string,HostMacro>
     * }
     */
    public static function resolveInheritanceForHostMacro(array $macros, array $inheritanceLine, int $childId): array
    {
        /** @var array<string,HostMacro> $directMacros */
        $directMacros = [];
        foreach ($macros as $macro) {
            if ($macro->getHostId() === $childId) {
                $directMacros[$macro->getName()] = $macro;
            }
        }

        /** @var array<string,HostMacro> $inheritedMacros */
        $inheritedMacros = [];
        foreach ($inheritanceLine as $parentId) {
            foreach ($macros as $macro) {
                if (
                    ! isset($inheritedMacros[$macro->getName()])
                    && $macro->getHostId() === $parentId
                ) {
                    $inheritedMacros[$macro->getName()] = $macro;
                }
            }
        }

        return [$directMacros, $inheritedMacros];
    }

    /**
     * Return an array with the macro's name as key.
     *
     * @param CommandMacro[] $macros
     *
     * @return array<string,CommandMacro>
     */
    public static function resolveInheritanceForCommandMacro(array $macros): array
    {
        $commandMacros = [];

        foreach ($macros as $macro) {
            if (! isset($commandMacros[$macro->getName()])) {
                $commandMacros[$macro->getName()] = $macro;
            }
        }

        return $commandMacros;
    }

    /**
     * Set the order of the direct macros.
     *
     * Note: Order of macros seems to be only used in UI legacy configuration forms for display purposes.
     * It doesn't impact macros management.
     *
     * @param HostMacroDifference $macrosDiff
     * @param array<string,HostMacro> $macros
     * @param array<string,HostMacro> $directMacros
     *
     * @throws AssertionFailedException
     */
    public static function setOrder(HostMacroDifference &$macrosDiff, array $macros, array $directMacros): void
    {
        $order = 0;
        foreach ($macros as $macroName => $macro) {
            if (
                isset($macrosDiff->addedMacros[$macroName])
                || isset($macrosDiff->updatedMacros[$macroName])
            ) {
                $macro->setOrder($order);
                ++$order;

                continue;
            }
            if (isset($macrosDiff->unchangedMacros[$macroName])) {
                if (isset($directMacros[$macroName])) {
                    if ($directMacros[$macroName]->getOrder() !== $order) {
                        // macro is the same but its order has changed
                        $macro->setOrder($order);

                        unset($macrosDiff->unchangedMacros[$macroName]);
                        $macrosDiff->updatedMacros[$macroName] = $macro;
                    }
                    ++$order;
                }
            }
        }
    }
}
