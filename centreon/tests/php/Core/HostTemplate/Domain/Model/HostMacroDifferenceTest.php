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
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostTemplate\Domain\Model\HostMacroDifference;

beforeEach(function (): void {

    // DIRECT MACROS - not changed
    $this->hostMacroA = new HostMacro(1, 'nameA', 'valueA');
    $this->hostMacroB = new HostMacro(1, 'nameB', 'valueB');
    // DIRECT MACROS - deleted
    $this->hostMacroC = new HostMacro(1, 'nameC', 'valueC');
    // DIRECT MACROS - added
    $this->hostMacroD = new HostMacro(1, 'nameD', 'valueD');
    // DIRECT MACROS - value changed => updated
    $this->hostMacroE = new HostMacro(1, 'nameE', 'valueE');
    $this->hostMacroE_edit = new HostMacro(1, 'nameE', 'valueE_edit');
    // DIRECT MACROS - description changed => updated
    $this->hostMacroF = new HostMacro(1, 'nameF', 'valueF');
    $this->hostMacroF->setDescription('descriptionF');
    $this->hostMacroF_edit = new HostMacro(1, 'nameF', 'valueF');
    $this->hostMacroF_edit->setDescription('descriptionF_edit');
    // DIRECT MACROS - isPassword changed => updated
    $this->hostMacroG = new HostMacro(1, 'nameG', 'valueG');
    $this->hostMacroG_edit = new HostMacro(1, 'nameG', 'valueG');
    $this->hostMacroG_edit->setIsPassword(true);

    // INHERITED MACROS - not changed
    $this->hostMacroH = new HostMacro(2, 'nameH', 'valueH');
    $this->hostMacroH_edit = new HostMacro(1, 'nameH', 'valueH');
    // INHERITED MACROS - value changed => added
    $this->hostMacroI = new HostMacro(2, 'nameI', 'valueI');
    $this->hostMacroI_edit = new HostMacro(1, 'nameI', 'valueI_edit');
    // INHERITED MACROS - isPassword changed => added
    $this->hostMacroJ = new HostMacro(2, 'nameJ', 'valueJ');
    $this->hostMacroJ_edit = new HostMacro(1, 'nameJ', 'valueJ');
    $this->hostMacroJ_edit->setIsPassword(true);
    // INHERITED MACROS - set description on unchanged inherited macro => common
    $this->hostMacroK = new HostMacro(2, 'nameK', 'valueK');
    $this->hostMacroK_edit = new HostMacro(1, 'nameK', 'valueK');
    $this->hostMacroK_edit->setDescription('descriptionK');
    // INHERITED MACROS - value reverted to inherted => deleted
    $this->hostMacroL_inherited = new HostMacro(2, 'nameL', 'valueL_inherit');
    $this->hostMacroL = new HostMacro(1, 'nameL', 'valueL');
    $this->hostMacroL_edit = new HostMacro(1, 'nameL', 'valueL_inherit');

    // COMMAND MACROS - value is set => added
    $this->commandMacroM = new CommandMacro(1, CommandMacroType::Host, 'nameM');
    $this->hostMacroM = new HostMacro(1, 'nameM', 'valueM');
    // COMMAND MACROS - isPassword is set => added
    $this->commandMacroN = new CommandMacro(1, CommandMacroType::Host, 'nameN');
    $this->hostMacroN = new HostMacro(1, 'nameN', '');
    $this->hostMacroN->setIsPassword(true);
    // COMMAND MACROS - value is reverted/let to empty => deleted
    $this->commandMacroO = new CommandMacro(1, CommandMacroType::Host, 'nameO');
    $this->hostMacroO = new HostMacro(1, 'nameO', 'valueO');
    $this->hostMacroO_edit = new HostMacro(1, 'nameO', '');

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
        $this->hostMacroC->getName() => $this->hostMacroC,
        $this->hostMacroL->getName() => $this->hostMacroL,
        $this->hostMacroO->getName() => $this->hostMacroO,
    ];
    $updatedMacros = [
        $this->hostMacroE_edit->getName() => $this->hostMacroE_edit,
        $this->hostMacroF_edit->getName() => $this->hostMacroF_edit,
        $this->hostMacroG_edit->getName() => $this->hostMacroG_edit,
    ];
    $commonMacros = [
        $this->hostMacroA->getName() => $this->hostMacroA,
        $this->hostMacroB->getName() => $this->hostMacroB,
        $this->hostMacroH_edit->getName() => $this->hostMacroH_edit,
        $this->hostMacroK_edit->getName() => $this->hostMacroK_edit,
    ];

    $macrosDiff = new HostMacroDifference($directMacros, $inheritedMacros, $commandMacros, $afterMacros);
    expect($macrosDiff->getAdded())->toBe($addedMacros)
        ->and($macrosDiff->getUpdated())->toBe($updatedMacros)
        ->and($macrosDiff->getRemoved())->tobe($removedMacros)
        ->and($macrosDiff->getCommon())->toBe($commonMacros);
 });
