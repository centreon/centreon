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

use Assert\AssertionFailedException;
use Core\CommandMacro\Domain\Model\CommandMacro;

/**
 * This class provide methods to help manipulate macros linked to a host template.
 */
class MacroManager
{
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
     * @param MacroDifference $macrosDiff
     * @param array<string,Macro> $macros
     * @param array<string,Macro> $directMacros
     *
     * @throws AssertionFailedException
     */
    public static function setOrder(MacroDifference $macrosDiff, array $macros, array $directMacros): void
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
            if (
                isset($macrosDiff->unchangedMacros[$macroName], $directMacros[$macroName])
            ) {
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
