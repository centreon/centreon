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

namespace Tests\Core\HostTemplate\Domain\Model;

use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;

it('should resolve command macro inheritance', function (): void {
    $macros = [
        $macroA = new CommandMacro(1, CommandMacroType::Host, 'macroA'),
        $macroB = new CommandMacro(1, CommandMacroType::Host, 'macroB'),
        $macroC1 = new CommandMacro(1, CommandMacroType::Host, 'macroC'),
        $macroC2 = new CommandMacro(1, CommandMacroType::Host, 'macroC'),
    ];

    $commandMacros = MacroManager::resolveInheritanceForCommandMacro($macros);

    expect($commandMacros)->toBe([
        $macroA->getName() => $macroA,
        $macroB->getName() => $macroB,
        $macroC1->getName() => $macroC1,
    ]);
});

it('should set macros order property', function (): void {
    $macroA = new Macro(1, 'nameA', 'valueA');
    $macroA->setOrder(0);
    $macroB = new Macro(1, 'nameB', 'valueB');
    $macroB->setOrder(1);
    $macroC = new Macro(1, 'nameC', 'valueC');
    $macroC->setOrder(2);
    $macroD = new Macro(1, 'nameD', 'valueD');
    $macroD->setOrder(3);
    $macroE = new Macro(2, 'nameE', 'valueE');
    $macroF = new Macro(1, 'nameF', 'valueF');

    $directMacros = [
        $macroA->getName() => $macroA,
        $macroB->getName() => $macroB,
        $macroC->getName() => $macroC,
        $macroD->getName() => $macroD,
    ];
    $macros = [
        $macroA->getName() => $macroA,
        $macroB->getName() => $macroB,
        $macroD->getName() => $macroD,
        $macroE->getName() => $macroE,
        $macroF->getName() => $macroF,
    ];

    $macrosDiff = new MacroDifference();
    $macrosDiff->addedMacros = [
        $macroF->getName() => $macroF,
    ];
    $macrosDiff->updatedMacros = [
        $macroB->getName() => $macroB,
    ];
    $macrosDiff->removedMacros = [
        $macroC->getName() => $macroC,
    ];
    $macrosDiff->unchangedMacros = [
        $macroA->getName() => $macroA,
        $macroD->getName() => $macroD,
        $macroE->getName() => $macroE,
    ];

    MacroManager::setOrder($macrosDiff, $macros, $directMacros);

    expect($macroA->getOrder())->toBe(0)
        ->and($macroB->getOrder())->toBe(1)
        ->and($macroD->getOrder())->toBe(2)
        ->and($macroF->getOrder())->toBe(3);
});
