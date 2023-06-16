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

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\YesNoDefault;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostMacro\Domain\Model\HostMacroDifference;
use Core\HostTemplate\Domain\Model\MacroManager;
use Core\HostTemplate\Domain\Model\NewHostTemplate;

it('should resolve host macro inheritance', function (): void {
    $templateId = 1;
    $templateInheritanceLine= [2, 3, 4];
    $macros = [
        $macroA = new HostMacro(1, 'nameA', 'valueA'),
        $macroB2 = new HostMacro(1, 'nameB', 'valueB-edited'),
        $macroB1 = new HostMacro(4, 'nameB', 'valueB-original'),
        $macroC = new HostMacro(2, 'nameC', 'valueC'),
        $macroD = new HostMacro(3, 'nameD', 'valueD'),
        $macroE2 = new HostMacro(3, 'nameE', 'valueE-edited'),
        $macroE1 = new HostMacro(4, 'nameE', 'valueE-original'),
    ];

    [$directMacros, $inheritedMacros]
        = MacroManager::resolveInheritanceForHostMacro($macros, $templateInheritanceLine, $templateId);

    expect($directMacros)->toBe([
        $macroA->getName() => $macroA,
        $macroB2->getName() => $macroB2,
    ])
    ->and($inheritedMacros)->toBe([
        $macroC->getName() => $macroC,
        $macroD->getName() => $macroD,
        $macroE2->getName() => $macroE2,
        $macroB1->getName() => $macroB1,
    ]);
});

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
    $macroA = new HostMacro(1, 'nameA', 'valueA');
    $macroA->setOrder(0);
    $macroB = new HostMacro(1, 'nameB', 'valueB');
    $macroB->setOrder(1);
    $macroC = new HostMacro(1, 'nameC', 'valueC');
    $macroC->setOrder(2);
    $macroD = new HostMacro(1, 'nameD', 'valueD');
    $macroD->setOrder(3);
    $macroE = new HostMacro(2, 'nameE', 'valueE');
    $macroF = new HostMacro(1, 'nameF', 'valueF');

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

    $macrosDiff = new HostMacroDifference();
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
