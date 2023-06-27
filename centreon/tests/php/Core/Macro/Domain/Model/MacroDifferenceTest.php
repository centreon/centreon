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

namespace Tests\Core\HostMacro\Domain\Model;

use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;

beforeEach(function (): void {

    // DIRECT MACROS - not changed
    $this->hostMacroA = new Macro(1, 'NAMEA', 'valueA');
    $this->hostMacroB = new Macro(1, 'NAMEB', 'valueB');
    // DIRECT MACROS - deleted
    $this->hostMacroC = new Macro(1, 'NAMEC', 'valueC');
    // DIRECT MACROS - added
    $this->hostMacroD = new Macro(1, 'NAMED', 'valueD');
    // DIRECT MACROS - value changed => updated
    $this->hostMacroE = new Macro(1, 'NAMEE', 'valueE');
    $this->hostMacroE_edit = new Macro(1, 'NAMEE', 'valueE_edit');
    // DIRECT MACROS - description changed => updated
    $this->hostMacroF = new Macro(1, 'NAMEF', 'valueF');
    $this->hostMacroF->setDescription('descriptionF');
    $this->hostMacroF_edit = new Macro(1, 'NAMEF', 'valueF');
    $this->hostMacroF_edit->setDescription('descriptionF_edit');
    // DIRECT MACROS - isPassword changed => updated
    $this->hostMacroG = new Macro(1, 'NAMEG', 'valueG');
    $this->hostMacroG_edit = new Macro(1, 'NAMEG', 'valueG');
    $this->hostMacroG_edit->setIsPassword(true);

    // INHERITED MACROS - not changed
    $this->hostMacroH = new Macro(2, 'NAMEH', 'valueH');
    $this->hostMacroH_edit = new Macro(1, 'NAMEH', 'valueH');
    // INHERITED MACROS - value changed => added
    $this->hostMacroI = new Macro(2, 'NAMEI', 'valueI');
    $this->hostMacroI_edit = new Macro(1, 'NAMEI', 'valueI_edit');
    // INHERITED MACROS - isPassword changed => added
    $this->hostMacroJ = new Macro(2, 'NAMEJ', 'valueJ');
    $this->hostMacroJ_edit = new Macro(1, 'NAMEJ', 'valueJ');
    $this->hostMacroJ_edit->setIsPassword(true);
    // INHERITED MACROS - set description on unchanged inherited macro => common
    $this->hostMacroK = new Macro(2, 'NAMEK', 'valueK');
    $this->hostMacroK_edit = new Macro(1, 'NAMEK', 'valueK');
    $this->hostMacroK_edit->setDescription('descriptionK');
    // INHERITED MACROS - value reverted to inherted => deleted
    $this->hostMacroL_inherited = new Macro(2, 'NAMEL', 'valueL_inherit');
    $this->hostMacroL = new Macro(1, 'NAMEL', 'valueL');
    $this->hostMacroL_edit = new Macro(1, 'NAMEL', 'valueL_inherit');

    // COMMAND MACROS - value is set => added
    $this->commandMacroM = new CommandMacro(1, CommandMacroType::Host, 'NAMEM');
    $this->hostMacroM = new Macro(1, 'NAMEM', 'valueM');
    // COMMAND MACROS - isPassword is set => added
    $this->commandMacroN = new CommandMacro(1, CommandMacroType::Host, 'NAMEN');
    $this->hostMacroN = new Macro(1, 'NAMEN', '');
    $this->hostMacroN->setIsPassword(true);
    // COMMAND MACROS - value is reverted/let to empty => deleted
    $this->commandMacroO = new CommandMacro(1, CommandMacroType::Host, 'NAMEO');
    $this->hostMacroO = new Macro(1, 'NAMEO', 'valueO');
    $this->hostMacroO_edit = new Macro(1, 'NAMEO', '');

});

it('should compute macros has expected', function (): void {
    $directMacros = [
        $this->hostMacroA->getName() => $this->hostMacroA,
        $this->hostMacroB->getName() => $this->hostMacroB,
        $this->hostMacroC->getName() => $this->hostMacroC,
        $this->hostMacroE->getName() => $this->hostMacroE,
        $this->hostMacroF->getName() => $this->hostMacroF,
        $this->hostMacroG->getName() => $this->hostMacroG,

        $this->hostMacroL->getName() => $this->hostMacroL,

        $this->hostMacroO->getName() => $this->hostMacroO,
    ];
    $inheritedMacros = [
         $this->hostMacroH->getName() => $this->hostMacroH,
         $this->hostMacroI->getName() => $this->hostMacroI,
         $this->hostMacroJ->getName() => $this->hostMacroJ,
         $this->hostMacroK->getName() => $this->hostMacroK,
         $this->hostMacroL_inherited->getName() => $this->hostMacroL_inherited,
    ];
    $commandMacros = [
        $this->commandMacroM->getName() => $this->commandMacroM,
        $this->commandMacroN->getName() => $this->commandMacroN,
        $this->commandMacroO->getName() => $this->commandMacroO,
    ];
    $afterMacros = [
        $this->hostMacroA->getName() => $this->hostMacroA,
        $this->hostMacroB->getName() => $this->hostMacroB,
        $this->hostMacroD->getName() => $this->hostMacroD,
        $this->hostMacroE_edit->getName() => $this->hostMacroE_edit,
        $this->hostMacroF_edit->getName() => $this->hostMacroF_edit,
        $this->hostMacroG_edit->getName() => $this->hostMacroG_edit,

        $this->hostMacroH_edit->getName() => $this->hostMacroH_edit,
        $this->hostMacroI_edit->getName() => $this->hostMacroI_edit,
        $this->hostMacroJ_edit->getName() => $this->hostMacroJ_edit,
        $this->hostMacroK_edit->getName() => $this->hostMacroK_edit,
        $this->hostMacroL_edit->getName() => $this->hostMacroL_edit,

        $this->hostMacroM->getName() => $this->hostMacroM,
        $this->hostMacroN->getName() => $this->hostMacroN,
        $this->hostMacroO_edit->getName() => $this->hostMacroO_edit,
    ];

    $addedMacros = [
        $this->hostMacroD->getName() => $this->hostMacroD,
        $this->hostMacroI_edit->getName() => $this->hostMacroI_edit,
        $this->hostMacroJ_edit->getName() => $this->hostMacroJ_edit,
        $this->hostMacroM->getName() => $this->hostMacroM,
        $this->hostMacroN->getName() => $this->hostMacroN,
    ];
    $removedMacros = [
        $this->hostMacroL->getName() => $this->hostMacroL,
        $this->hostMacroO->getName() => $this->hostMacroO,
        $this->hostMacroC->getName() => $this->hostMacroC,
    ];
    $updatedMacros = [
        $this->hostMacroE_edit->getName() => $this->hostMacroE_edit,
        $this->hostMacroF_edit->getName() => $this->hostMacroF_edit,
        $this->hostMacroG_edit->getName() => $this->hostMacroG_edit,
    ];
    $unchangedMacros = [
        $this->hostMacroA->getName() => $this->hostMacroA,
        $this->hostMacroB->getName() => $this->hostMacroB,
        $this->hostMacroH_edit->getName() => $this->hostMacroH_edit,
        $this->hostMacroK_edit->getName() => $this->hostMacroK_edit,
    ];

    $macrosDiff = new MacroDifference();
    $macrosDiff->compute($directMacros, $inheritedMacros, $commandMacros, $afterMacros);
    expect($macrosDiff->addedMacros)->toBe($addedMacros)
        ->and($macrosDiff->updatedMacros)->toBe($updatedMacros)
        ->and($macrosDiff->removedMacros)->tobe($removedMacros)
        ->and($macrosDiff->unchangedMacros)->toBe($unchangedMacros);
 });
