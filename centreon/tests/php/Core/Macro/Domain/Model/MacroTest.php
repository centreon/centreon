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

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Macro\Domain\Model\Macro;

it('should return properly set host macro instance', function (): void {
    $macro = new Macro(1, 'macroName', 'macroValue');
    $macro->setIsPassword(true);
    $macro->setDescription('macroDescription');

    expect($macro->getOwnerId())->toBe(1)
        ->and($macro->getName())->toBe('MACRONAME')
        ->and($macro->getValue())->toBe('macroValue');
});

it('should throw an exception when host macro name is empty', function (): void {
    new Macro(1, '', 'macroValue');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Macro::name')->getMessage()
);

it('should throw an exception when host macro name is too long', function (): void {
    new Macro(1, str_repeat('a', Macro::MAX_NAME_LENGTH + 1), 'macroValue');
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('A', Macro::MAX_NAME_LENGTH + 1),
        Macro::MAX_NAME_LENGTH + 1,
        Macro::MAX_NAME_LENGTH,
        'Macro::name'
    )->getMessage()
);

it('should throw an exception when host macro value is too long', function (): void {
    new Macro(1, 'macroName', str_repeat('a', Macro::MAX_VALUE_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Macro::MAX_VALUE_LENGTH + 1),
        Macro::MAX_VALUE_LENGTH + 1,
        Macro::MAX_VALUE_LENGTH,
        'Macro::value'
    )->getMessage()
);

it('should throw an exception when host macro description is too long', function (): void {
    $macro = new Macro(1, 'macroName', 'macroValue');
    $macro->setDescription(str_repeat('a', Macro::MAX_DESCRIPTION_LENGTH + 1));
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Macro::MAX_DESCRIPTION_LENGTH + 1),
        Macro::MAX_DESCRIPTION_LENGTH + 1,
        Macro::MAX_DESCRIPTION_LENGTH,
        'Macro::description'
    )->getMessage()
);

it('should resolve macro inheritance', function (): void {
    $templateId = 1;
    $templateInheritanceLine= [2, 3, 4];
    $macros = [
        $macroA = new Macro(1, 'nameA', 'valueA'),
        $macroB2 = new Macro(1, 'nameB', 'valueB-edited'),
        $macroB1 = new Macro(4, 'nameB', 'valueB-original'),
        $macroC = new Macro(2, 'nameC', 'valueC'),
        $macroD = new Macro(3, 'nameD', 'valueD'),
        $macroE2 = new Macro(3, 'nameE', 'valueE-edited'),
        $macroE1 = new Macro(4, 'nameE', 'valueE-original'),
    ];

    [$directMacros, $inheritedMacros]
        = Macro::resolveInheritance($macros, $templateInheritanceLine, $templateId);

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
